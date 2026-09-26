<?php

namespace Extensions\SmsUnisms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Support\StatusTone;
use Extensions\SmsUnisms\Http\Requests\UpdateUniSmsRequest;
use Extensions\SmsUnisms\SaveSmsTemplates;
use Extensions\SmsUnisms\SmsTemplate;
use Extensions\SmsUnisms\UniSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class UniSmsController extends Controller
{
    public function index()
    {
        $key = UniSms::apiKey();
        $statuses = $this->statuses();

        return view('sms-unisms::index', [
            // Never the key itself. The last four are enough to tell one
            // account from another without putting the secret back on screen
            // every time the page is opened.
            'masked' => $key === '' ? null : str_repeat('*', max(0, strlen($key) - 4)) . substr($key, -4),
            'senderId' => UniSms::senderId(),
            'enabled' => UniSms::enabled(),
            'baseUrl' => UniSms::baseUrl(),
            'readiness' => UniSms::readiness(),
            'statuses' => $statuses->map(fn (OrderStatus $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'tone' => in_array($s->tone, StatusTone::ALL, true) ? $s->tone : StatusTone::NEUTRAL,
            ])->values(),
            // After a refused save the editor comes back as it was posted,
            // not as it was stored, so no typing is lost.
            'templates' => old('templates', SmsTemplate::with('statuses:id')->orderBy('id')->get()->map(fn (SmsTemplate $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'message' => $t->message,
                'status_ids' => $t->statuses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])->all()),
        ]);
    }

    public function update(UpdateUniSmsRequest $request, SaveSmsTemplates $saveTemplates)
    {
        $data = $request->validated();

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

        $saveTemplates(array_values($data['templates'] ?? []));

        // Back to the tab the merchant saved from. The page keeps it in the
        // address bar, not in a field, so switching tabs never arms Save.
        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);

        return redirect()->route('admin.extensions.unisms.index', ($query['tab'] ?? null) === 'messages' ? ['tab' => 'messages'] : [])
            ->with('success', 'UniSMS settings saved.');
    }

    /**
     * The message as a handset would show it, for the editor's preview.
     *
     * Rendered by the same method that sends, with a made-up order, so the
     * line breaks, the count and the links the guard removes are exactly
     * what a customer would get. Nothing is sent and nothing is stored.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:' . UniSms::MAX_LENGTH],
            'status_id' => ['nullable', 'integer'],
        ]);

        $status = isset($data['status_id'])
            ? OrderStatus::where('store_id', (int) config('app.store_id', 1))->whereKey($data['status_id'])->value('name')
            : null;

        $text = UniSms::render((string) ($data['message'] ?? ''), [
            'store_name' => (string) config('app.name'),
            'order_id' => '10482',
            // No status chosen yet: show the placeholder as written rather
            // than a status this template may never be sent for.
            'order_status' => $status ?? '{order_status}',
            'customer_name' => 'Juan Dela Cruz',
            'tracking_number' => 'TRK4829105573',
            'courier_name' => 'Your courier',
            'comment' => 'Thank you for your order.',
        ]);

        return response()->json(['text' => $text] + UniSms::segments($text));
    }

    /**
     * This store's statuses, in the same order the Order Statuses screen
     * shows them, so the picker reads the same way in both places.
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
