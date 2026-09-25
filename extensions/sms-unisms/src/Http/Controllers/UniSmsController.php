<?php

namespace Extensions\SmsUnisms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use App\Models\Setting;
use Extensions\SmsUnisms\SmsStatusMap;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class UniSmsController extends Controller
{
    public function index()
    {
        $key = UniSms::apiKey();

        return view('sms-unisms::index', [
            // Never the key itself. The last four are enough to tell one
            // account from another without putting the secret back on screen
            // every time the page is opened.
            'masked' => $key === '' ? null : str_repeat('*', max(0, strlen($key) - 4)) . substr($key, -4),
            'senderId' => UniSms::senderId(),
            'enabled' => UniSms::enabled(),
            'baseUrl' => UniSms::baseUrl(),
            'template' => UniSms::template(),
            'readiness' => UniSms::readiness(),
            'statuses' => $this->statuses(),
            'texted' => SmsStatusMap::all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'template' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable'],
            'status_ids' => ['nullable', 'array'],
            'status_ids.*' => [
                'integer',
                Rule::exists('order_statuses', 'id')->where('store_id', (int) config('app.store_id', 1)),
            ],
        ]);

        // Blank means "leave the saved one alone", so opening the page and
        // saving a sender ID does not wipe the key behind the asterisks.
        $key = trim((string) ($data['api_key'] ?? ''));

        if ($key !== '') {
            $this->put(UniSms::KEY_API, Crypt::encryptString($key));
        }

        $this->put(UniSms::KEY_SENDER, trim((string) ($data['sender_id'] ?? '')));
        $this->put(UniSms::KEY_ENABLED, $request->boolean('enabled') ? '1' : '0');

        $base = rtrim(trim((string) ($data['base_url'] ?? '')), '/');
        $this->put(UniSms::KEY_BASE, $base !== '' ? $base : UniSms::DEFAULT_BASE);

        // Blank falls back to the seeded default rather than leaving the
        // previous custom sentence in place: UniSms::template() already
        // treats an empty saved value as "use the default".
        $this->put(UniSms::KEY_TEMPLATE, trim((string) ($data['template'] ?? '')));

        SmsStatusMap::put(array_map('intval', $data['status_ids'] ?? []));

        return redirect()->route('admin.extensions.unisms.index')
            ->with('success', 'UniSMS settings saved.');
    }

    /**
     * This store's statuses, in the same order the Order Statuses screen
     * shows them, so the tick list reads the same way in both places.
     */
    private function statuses()
    {
        return OrderStatus::where('store_id', (int) config('app.store_id', 1))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Ask UniSMS who we are.
     *
     * GET /account rather than a real message: it proves the key is accepted
     * without spending credit or needing a handset to watch.
     */
    public function test()
    {
        $result = UniSms::account();

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => 'UniSMS: ' . ($result['error'] ?? 'the account check failed.'),
            ]);
        }

        $body = $result['body'] ?? [];
        $named = $body['name'] ?? $body['account_name'] ?? $body['email'] ?? null;

        return response()->json([
            'ok' => true,
            'message' => 'UniSMS accepted the key' . ($named ? ', account: ' . $named : '') . '.',
        ]);
    }

    /** One real message, to a number the admin types, so nothing is guessed. */
    public function sendTest(Request $request)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:20'],
            'content' => ['required', 'string', 'max:' . UniSms::MAX_LENGTH],
        ]);

        $result = UniSms::send($data['recipient'], $data['content']);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Sent to ' . $data['recipient'] . '.'
                : 'Not sent. ' . ($result['error'] ?? 'UniSMS refused it.'),
        ]);
    }

    private function put(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['group' => UniSms::GROUP, 'key' => $key],
            ['value' => $value]
        );
    }
}
