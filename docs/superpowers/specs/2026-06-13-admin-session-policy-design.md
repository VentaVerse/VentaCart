# Admin & Customer Session Policy — Design Spec

- **Date:** 2026-06-13
- **Status:** Draft for review
- **Problem:** Admin (and customers) who tick "Remember me" stay authenticated for ~5 years (Laravel's default remember token), so admins effectively never log out. For an admin panel that's a real exposure risk. Customers want the opposite — convenient persistence.

## Policy (the two guards get deliberately different rules)

| | Admin (`web`) | Customer (`customer`) |
|---|---|---|
| Remember-me duration | **24h** | **5 days** |
| Session model | **Absolute 24h cap** (hard re-login) | **Sliding** (inactivity window) |
| Concurrent sessions | **Single active session** | Multi-device (unchanged) |
| Idle session timeout | 120 min (unchanged) | 120 min (unchanged) |

**Admin net effect:** one device at a time, signed in for **at most 24h from login regardless of activity**, then must re-authenticate. A lost/stolen device is exposed ≤24h, and logging in elsewhere kicks the old session immediately.

**Customer net effect:** unchanged convenience — stay signed in across devices; logged out only after **5 days of inactivity** (resets each return).

## Mechanisms

### 1. Remember durations (both guards)
Laravel's `SessionGuard::setRememberDuration($minutes)` overrides the ~5-year default. Set before the login attempt:
- Admin login → `Auth::guard('web')->setRememberDuration(config('auth.admin.remember_minutes', 1440))`
- Customer login → `Auth::guard('customer')->setRememberDuration(config('auth.customer.remember_minutes', 7200))`

Durations live in **config** (env-overridable), not hardcoded.

### 2. Admin absolute 24h cap
The sliding session + sliding remember mean neither natively caps total logged-in time. So we add an explicit cap:
- On successful admin login, store `auth.web_login_at = now()` in the **session**.
- New middleware `EnforceAdminSessionCap` on the admin route group: if `now - web_login_at > config('auth.admin.absolute_cap_minutes', 1440)`, `Auth::guard('web')->logout()`, invalidate + regenerate the session, redirect to admin login with a "session expired — please sign in again" message.
- Because it's keyed on the **login timestamp** (not last activity), it's a true hard cap regardless of how active the admin is.

### 3. Admin single active session
On successful admin login, after `Auth::attempt` + session regeneration:
1. **Prune** every other `sessions` row for this user id (keep the current session id).
2. **Rotate** the user's `remember_token` so any other device's remember cookie can no longer re-authenticate.
3. **Re-issue this device's** recaller cookie with the new token (only if "remember" was checked).

> ⚠️ **Sequencing gotcha:** the rotate-then-reissue order matters. `Auth::attempt(..., true)` first sets our recaller cookie with the *old* token; we then rotate the token (killing other devices) and must re-queue *our* cookie with the new token, or we'd lock ourselves out on the next request. Implementation re-issues via `Auth::guard('web')->login($user, true)` after the rotation (single extra login event, acceptable) or a direct `cookie()->queue(...)` of the recaller value `"{id}|{newToken}|{passwordHash}"`.

The kicked device, on its next request, finds its session row gone and its remember cookie invalid → redirected to login. Optional: flash "You were signed out because your account signed in on another device."

### 4. Customer — sliding 5-day, no cap, no single-session
Only change is the remember duration (step 1). No absolute-cap middleware, no session pruning, no token rotation — multi-device persistence preserved.

## Files touched
- `config/auth.php` (or a small dedicated config) — `admin.remember_minutes`, `admin.absolute_cap_minutes`, `customer.remember_minutes`.
- `app/Http/Requests/Auth/LoginRequest.php` (admin) — set remember duration; post-login single-session prune + token rotation + store `web_login_at`.
- `app/Http/Middleware/EnforceAdminSessionCap.php` (new) — the absolute cap.
- `bootstrap/app.php` — register the middleware; apply to the admin route group (`opensesame` prefix).
- `app/Http/Controllers/Storefront/CustomerAuthController.php` — set customer remember duration (5d).

## Testing (feature)
- **Absolute cap:** log in as admin, `travel()` 24h+1min, hit an admin route → redirected to login.
- **Within cap:** travel 23h → still authenticated.
- **Single session:** authenticate session A, then log in as the same admin in session B → A's session row deleted, A redirected to login on next request.
- **Remember durations:** assert the recaller cookie max-age reflects 1440 min (admin) / 7200 min (customer).
- **Customer unaffected:** customer login multi-device stays logged in; no absolute cap applied.

## Out of scope
- **2FA** on admin login — the single highest-value hardening, tracked as a separate follow-up.
- Device-management UI (Option 2) — not needed for a lean store.

## Notes
- DB footprint stays minimal: single-session prunes admins to one self-expiring `sessions` row; no new tables; no per-login audit rows.
- Admin-only behavior is keyed on the `web` guard / admin route group, so the storefront `customer` guard is untouched beyond its remember duration.
