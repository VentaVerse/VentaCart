<?php

namespace Extensions\SmsUnisms;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The UniSMS gateway.
 *
 * One place that knows the credentials, the base URL and what the API expects,
 * so the settings page, the connection test and anything that later wants to
 * send a message all ask the same object rather than each assembling a request
 * of its own.
 *
 * Two things the API insists on, both of which will bite a store that has not
 * been told:
 *
 *  - Recipients must be E.164, with the plus and the country code. A local
 *    09171234567 is refused, and Venta stores exactly that today, so anything
 *    that sends has to normalise first rather than hope.
 *  - A registered sender ID is mandatory under Philippine telco rules. New
 *    accounts get a test one; a custom one has to be applied for before real
 *    traffic. An API key on its own is not enough to send.
 */
class UniSms
{
    public const GROUP = 'sms';

    public const KEY_API = 'unisms_api_key';

    public const KEY_SENDER = 'unisms_sender_id';

    public const KEY_ENABLED = 'unisms_enabled';

    /** Overridable so a test or a staging store can point elsewhere. */
    public const KEY_BASE = 'unisms_base_url';

    public const DEFAULT_BASE = 'https://unismsapi.com/api';

    public const KEY_TEMPLATE = 'unisms_template';

    /** The owner's sentence, used until a merchant writes their own. */
    public const DEFAULT_TEMPLATE = '{store_name}: Order #{order_id} is now {order_status}';

    /** The API's own ceiling on one message. */
    public const MAX_LENGTH = 670;

    public static function template(): string
    {
        $saved = trim((string) Setting::where('group', self::GROUP)->where('key', self::KEY_TEMPLATE)->value('value'));

        return $saved !== '' ? $saved : self::DEFAULT_TEMPLATE;
    }

    /**
     * A text message carries no links.
     *
     * Philippine telcos drop messages containing a URL, so a link does not
     * merely annoy: it loses the message. The guard sits at the send layer as
     * well as in the composer, so a merchant typing a link into the template,
     * the admin test message, or any future caller cannot put a link on a
     * handset.
     *
     * This matches shape, not a list of endings: an allowlist of TLDs lets a
     * shortener like bit.ly or an unlisted ending like .shop straight
     * through. Working token by token (whitespace-delimited) and dropping a
     * whole token, rather than trimming a matched substring out of it, also
     * means a host with an extra label such as newgearday.com.ph/track/ABC123
     * or an email address such as happy@newgearday.com disappears whole,
     * with no leftover path or local-part fragment.
     *
     * A token is dropped when it:
     *   (a) contains a scheme, http:// or https://, in any letter case;
     *   (b) starts with www. in any letter case; or
     *   (c) contains a host shape: one or more label(s) of letters, digits
     *       or hyphens joined by dots, ending in a label of two or more
     *       letters, optionally followed by a path.
     */
    public static function stripUrls(string $text): string
    {
        $text = preg_replace_callback('/\S+/', function (array $match): string {
            $token = $match[0];

            if (preg_match('~https?://~i', $token) === 1) {
                return '';
            }

            if (preg_match('~^www\.~i', $token) === 1) {
                return '';
            }

            if (preg_match('~[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}~i', $token) === 1) {
                return '';
            }

            return $token;
        }, $text) ?? $text;

        return trim(preg_replace('/\s{2,}/', ' ', $text) ?? $text);
    }

    public static function enabled(): bool
    {
        return self::setting(self::KEY_ENABLED) === '1';
    }

    public static function senderId(): string
    {
        return trim((string) self::setting(self::KEY_SENDER));
    }

    public static function baseUrl(): string
    {
        return rtrim(trim((string) (self::setting(self::KEY_BASE) ?: self::DEFAULT_BASE)), '/');
    }

    /** The stored key, decrypted, or '' when none is saved. */
    public static function apiKey(): string
    {
        $stored = (string) self::setting(self::KEY_API);

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            // Written before encryption, or by a hand that did not encrypt it.
            return $stored;
        }
    }

    /** Everything needed to send, or the first thing that is missing. */
    public static function readiness(): array
    {
        if (self::apiKey() === '') {
            return ['ready' => false, 'reason' => 'No API key saved.'];
        }

        if (self::senderId() === '') {
            return ['ready' => false, 'reason' => 'No sender ID saved. UniSMS refuses a message without one.'];
        }

        return ['ready' => true, 'reason' => null];
    }

    /**
     * Ask the API who we are.
     *
     * GET /account is the cheapest honest test: it proves the key is accepted
     * without spending a message or needing a real handset to receive one.
     *
     * @return array{ok: bool, status: int|null, body: array|null, error: string|null}
     */
    public static function account(): array
    {
        $key = self::apiKey();

        if ($key === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'No API key saved.'];
        }

        try {
            // Basic auth with the key as the username and no password, which
            // is what the documentation specifies.
            $response = Http::withBasicAuth($key, '')
                ->acceptJson()
                ->timeout(15)
                ->get(self::baseUrl() . '/account');

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
                'error' => $response->successful() ? null : self::reason($response->status(), $response->json()),
            ];
        } catch (\Throwable $e) {
            Log::warning('UniSMS: account check failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'Could not reach UniSMS: ' . $e->getMessage()];
        }
    }

    /**
     * Send one message.
     *
     * @param  string  $recipient  E.164, with the plus and country code.
     * @return array{ok: bool, status: int|null, body: array|null, error: string|null}
     */
    public static function send(string $recipient, string $content, ?string $senderId = null): array
    {
        $ready = self::readiness();

        if (! $ready['ready']) {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => $ready['reason']];
        }

        $recipient = trim($recipient);

        if (! str_starts_with($recipient, '+')) {
            return ['ok' => false, 'status' => null, 'body' => null,
                'error' => 'Recipient must be in E.164 form, starting with + and the country code.'];
        }

        $content = self::stripUrls(trim($content));

        if ($content === '') {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'Nothing to send.'];
        }

        if (mb_strlen($content) > self::MAX_LENGTH) {
            return ['ok' => false, 'status' => null, 'body' => null,
                'error' => 'Message is longer than the ' . self::MAX_LENGTH . ' characters UniSMS accepts.'];
        }

        try {
            $response = Http::withBasicAuth(self::apiKey(), '')
                ->acceptJson()
                ->timeout(20)
                ->post(self::baseUrl() . '/sms', [
                    'recipient' => $recipient,
                    'content' => $content,
                    'sender_id' => $senderId ?: self::senderId(),
                ]);

            if (! $response->successful()) {
                Log::warning('UniSMS: send refused', ['status' => $response->status(), 'body' => $response->json()]);
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->json(),
                'error' => $response->successful() ? null : self::reason($response->status(), $response->json()),
            ];
        } catch (\Throwable $e) {
            Log::error('UniSMS: send failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'Could not reach UniSMS: ' . $e->getMessage()];
        }
    }

    /** Something a person can act on, rather than a status code. */
    private static function reason(int $status, ?array $body): string
    {
        $said = is_array($body)
            ? trim((string) ($body['message'] ?? $body['error'] ?? ($body['errors'] ? json_encode($body['errors']) : '')))
            : '';

        return match (true) {
            $status === 401 || $status === 403 => 'UniSMS rejected the API key.' . ($said ? ' ' . $said : ''),
            $status === 422 => 'UniSMS refused the request.' . ($said ? ' ' . $said : ''),
            $status === 429 => 'Rate limited by UniSMS. It allows one request every two seconds.',
            $status >= 500 => 'UniSMS is having trouble (HTTP ' . $status . '). Try again shortly.',
            default => $said !== '' ? $said : 'UniSMS returned HTTP ' . $status . '.',
        };
    }

    private static function setting(string $key): ?string
    {
        return Setting::where('group', self::GROUP)->where('key', $key)->value('value');
    }
}
