<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The first admin account, made by whoever opens the admin first.
 *
 * A fresh install seeds no user, so there is never a known default login.
 * This page exists only while the users table is empty; once an account
 * exists it hands every visit to the sign in page instead.
 */
class FirstAdminController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        if (User::exists()) {
            return redirect()->route('login');
        }

        // The same rules the Users page applies, so the first account is one
        // that page would also accept.
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Two submissions racing each other must not both become the admin:
        // the empty-table check and the insert happen under one lock.
        $user = Cache::lock('admin-first-run', 10)->block(5, function () use ($data) {
            if (User::exists()) {
                return null;
            }

            $user = User::create([
                'name' => $data['username'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => Role::where('slug', 'super-admin')->firstOrFail()->id,
                'is_active' => true,
            ]);
            // Stamped as a sign in would, so the session cap counts from now.
            $user->forceFill(['web_login_at' => now()])->save();

            return $user;
        });

        if ($user === null) {
            return redirect()->route('login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
