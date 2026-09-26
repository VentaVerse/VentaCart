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

    /** The owner's sentence, the starting message of every new template. */
    public const DEFAULT_TEMPLATE = '{store_name}: Order #{order_id} is now {order_status}';

    /** Every placeholder render() fills, in the order the page offers them. */
    public const PLACEHOLDERS = [
        'store_name', 'order_id', 'order_status', 'customer_name',
        'tracking_number', 'courier_name', 'comment',
    ];

    /** The API's own pace: one request every this many seconds. */
    public const SECONDS_PER_REQUEST = 2;

    /** The API's own ceiling on one message. */
    public const MAX_LENGTH = 670;

    /**
     * Generic domain endings common enough that a merchant might type one
     * with no country code attached. Named and listed rather than inferred,
     * so the set the guard treats as a link is visible and deliberate, not
     * an implicit side effect of a regex.
     */
    private const GENERIC_ENDINGS = [
        'com', 'net', 'org', 'info', 'biz', 'shop', 'store', 'online', 'site',
        'xyz', 'app', 'dev', 'link', 'click', 'top', 'live', 'page', 'tech',
        'blog', 'news', 'club', 'website', 'space', 'email', 'asia',
    ];

    /**
     * The text a template becomes for one order.
     *
     * Spaces, line breaks and blank lines are the merchant's and are kept
     * exactly: a handset shows them, and each one is a character. The only
     * thing tidied is a gap the code itself made: a placeholder with nothing
     * behind it (no tracking number yet) and a link the guard removed each
     * go with one neighbouring space, so "Tracking {tracking_number} via"
     * does not become "Tracking  via". The page's preview calls this same
     * method, so what it shows is what is sent.
     *
     * @param  array<string, string>  $values  placeholder name => value
     */
    public static function render(string $template, array $values): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $template);

        $pairs = [];
        foreach ($values as $name => $value) {
            // A value is one line: a comment's own line breaks belong to the
            // email it was written for, not to the merchant's layout.
            $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
            // Empty becomes the same marker a removed link leaves, so
            // stripUrls() takes it out with one neighbouring space.
            $pairs['{' . $name . '}'] = $value === '' ? "\x00" : $value;
        }

        return mb_substr(self::stripUrls(strtr($text, $pairs)), 0, self::MAX_LENGTH);
    }

    /** Characters a handset counts once each, in the 7-bit alphabet every phone speaks. */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** In that alphabet, but sent as two characters each. */
    private const GSM_EXTENDED = "^{}\\[~]|€\f";

    /**
     * How many texts a message is billed as.
     *
     * In the 7-bit alphabet one text holds 160 characters, and a longer
     * message is split into parts of 153 (the rest carries the joining
     * header). One character outside it, an emoji or a curly quote, sends
     * the whole message as Unicode: 70 in one text, 67 per part. A space
     * and a line break are characters like any other.
     *
     * @return array{chars: int, segments: int, unicode: bool}
     */
    public static function segments(string $text): array
    {
        $chars = mb_strlen($text);

        if ($text === '') {
            return ['chars' => 0, 'segments' => 0, 'unicode' => false];
        }

        $units = 0;
        $unicode = false;

        foreach (mb_str_split($text) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $units += 1;
            } elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $units += 2;
            } else {
                $unicode = true;
                break;
            }
        }

        if ($unicode) {
            // UTF-16 code units: an emoji is two.
            $units = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

            return ['chars' => $chars, 'segments' => $units <= 70 ? 1 : (int) ceil($units / 67), 'unicode' => true];
        }

        return ['chars' => $chars, 'segments' => $units <= 160 ? 1 : (int) ceil($units / 153), 'unicode' => false];
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
     * This matches shape, not a list of endings alone: an allowlist of TLDs
     * lets a shortener like bit.ly or an unlisted ending like .shop straight
     * through, and matching "any two-or-more-letter ending" is too wide, as
     * it catches a title glued to a name with no space, such as "Mr.Reyes"
     * or the Philippine "Ma.Cristina". Working token by token
     * (whitespace-delimited) and dropping a whole token, rather than
     * trimming a matched substring out of it, also means a host with an
     * extra label such as newgearday.com.ph/track/ABC123 or an email
     * address such as happy@newgearday.com disappears whole, with no
     * leftover path or local-part fragment.
     *
     * A token is dropped when it:
     *   (a) contains a scheme, http:// or https://, in any letter case;
     *   (b) starts with www. in any letter case;
     *   (c) contains a dotted host followed by a path, meaning labels
     *       joined by dots and then a slash (bit.ly/xyz123,
     *       newgearday.com.ph/track/ABC123): whatever the ending, a slash
     *       after a dotted token is a strong link signal on its own; or
     *   (d) contains a bare dotted host with no path, whose last label is
     *       either exactly two letters (a country code: .ly, .ph, .io,
     *       .co, .me and the rest) or one of GENERIC_ENDINGS. A name like
     *       "Mr.Reyes" fails both: five letters is not a country code, and
     *       "reyes" is not on the list, so it is left alone.
     */
    public static function stripUrls(string $text): string
    {
        $endings = implode('|', self::GENERIC_ENDINGS);

        $text = preg_replace_callback('/\S+/', function (array $match) use ($endings): string {
            $token = $match[0];

            if (preg_match('~https?://~i', $token) === 1) {
                return "\x00";
            }

            if (preg_match('~^www\.~i', $token) === 1) {
                return "\x00";
            }

            if (preg_match('~[a-z0-9-]+(?:\.[a-z0-9-]+)+/~i', $token) === 1) {
                return "\x00";
            }

            if (preg_match('~\b[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:' . $endings . '|[a-z]{2})\b~i', $token) === 1) {
                return "\x00";
            }

            return $token;
        }, $text) ?? $text;

        // Drop each removed link with the space before it (or after it, at
        // the start of a line), and nothing else: the merchant's line breaks
        // are part of the message.
        $text = preg_replace('/(?<=\S)[ \t]+\x00|\x00[ \t]*/', '', $text) ?? $text;

        return trim(str_replace("\x00", '', $text));
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
            $status === 429 => 'Rate limited by UniSMS. It allows one request every ' . self::SECONDS_PER_REQUEST . ' seconds.',
            $status >= 500 => 'UniSMS is having trouble (HTTP ' . $status . '). Try again shortly.',
            default => $said !== '' ? $said : 'UniSMS returned HTTP ' . $status . '.',
        };
    }

    private static function setting(string $key): ?string
    {
        return Setting::where('group', self::GROUP)->where('key', $key)->value('value');
    }
}
