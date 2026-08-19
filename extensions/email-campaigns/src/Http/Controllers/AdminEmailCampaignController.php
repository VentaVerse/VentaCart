<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Setting;
use App\Support\StoreIdentity;
use Extensions\EmailCampaigns\Audiences\AudienceRegistry;
use Extensions\EmailCampaigns\Http\Requests\EmailCampaignRequest;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Extensions\EmailCampaigns\Services\CampaignRecipients;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AdminEmailCampaignController extends Controller
{
    public function index()
    {
        return view('email-campaigns::index', [
            'campaigns' => EmailCampaign::latest()->paginate(20),
            'audienceLabels' => AudienceRegistry::all(),
        ]);
    }

    public function create(Request $request)
    {
        $template = $request->filled('from_template')
            ? EmailCampaignTemplate::find($request->input('from_template'))
            : null;

        $selectedIds = array_map('intval', (array) old('customer_ids', []));

        return view('email-campaigns::form', [
            'campaign' => null,
            'prefill' => $template,
            'audiences' => AudienceRegistry::all(),
            'groups' => CustomerGroup::orderBy('name')->get(),
            'customers' => $this->customerPickerOptions($selectedIds),
            'tokens' => CampaignRenderer::tokens(),
            'templates' => EmailCampaignTemplate::orderBy('name')->get(),
        ]);
    }

    public function store(EmailCampaignRequest $request)
    {
        $campaign = EmailCampaign::create([
            'store_id' => 1,
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'audience_type' => $request->input('audience_type'),
            'audience_params' => $request->audienceParams(),
            'status' => $request->input('action') === 'send' ? 'queued' : 'draft',
        ]);

        if ($request->input('action') === 'send') {
            SendCampaignJob::dispatch($campaign->id);

            return redirect()->route('admin.email-campaigns.index')
                ->with('success', 'Campaign queued for sending.');
        }

        return redirect()->route('admin.email-campaigns.edit', $campaign)
            ->with('success', 'Draft saved.');
    }

    public function edit(EmailCampaign $campaign)
    {
        $selectedIds = array_map('intval', (array) old('customer_ids', $campaign->audience_params['customer_ids'] ?? []));

        return view('email-campaigns::form', [
            'campaign' => $campaign,
            'prefill' => null,
            'audiences' => AudienceRegistry::all(),
            'groups' => CustomerGroup::orderBy('name')->get(),
            'customers' => $this->customerPickerOptions($selectedIds),
            'tokens' => CampaignRenderer::tokens(),
            'templates' => EmailCampaignTemplate::orderBy('name')->get(),
        ]);
    }

    public function update(EmailCampaignRequest $request, EmailCampaign $campaign)
    {
        // A campaign that has gone out is a record of what happened, not a draft.
        if ($campaign->status === 'sent') {
            return back()->with('error', 'This campaign has already been sent.');
        }

        $campaign->update([
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'audience_type' => $request->input('audience_type'),
            'audience_params' => $request->audienceParams(),
            'status' => $request->input('action') === 'send' ? 'queued' : 'draft',
        ]);

        if ($request->input('action') === 'send') {
            SendCampaignJob::dispatch($campaign->id);

            return redirect()->route('admin.email-campaigns.index')
                ->with('success', 'Campaign queued for sending.');
        }

        return back()->with('success', 'Draft saved.');
    }

    public function destroy(EmailCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.email-campaigns.index')->with('success', 'Campaign deleted.');
    }

    /**
     * A fresh draft carrying the same subject, body and audience. Never a
     * copy of what happened: status, recipients_total and sent_at are the
     * record of a specific send and start clean on the duplicate.
     */
    public function duplicate(EmailCampaign $campaign)
    {
        $copy = $campaign->replicate(['status', 'recipients_total', 'sent_at']);
        $copy->status = 'draft';
        $copy->recipients_total = 0;
        $copy->sent_at = null;
        $copy->subject = 'Copy of ' . $campaign->subject;
        $copy->save();

        return redirect()->route('admin.email-campaigns.edit', $copy)->with('success', 'Campaign duplicated.');
    }

    /** Live counts for the compose screen's audience picker. */
    public function recipientCount(Request $request)
    {
        $request->validate([
            'audience_type' => ['sometimes', Rule::in(array_keys(AudienceRegistry::all()))],
            'group_id' => ['nullable', 'integer'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer'],
        ]);

        $draft = new EmailCampaign([
            'audience_type' => $request->input('audience_type', 'all'),
            'audience_params' => match ($request->input('audience_type')) {
                'group' => ['group_id' => (int) $request->input('group_id')],
                'selected' => ['customer_ids' => array_map('intval', (array) $request->input('customer_ids', []))],
                default => null,
            },
        ]);

        return response()->json([
            'audience_total' => CampaignRecipients::audienceTotal($draft),
            'will_receive' => CampaignRecipients::willReceive($draft),
        ]);
    }

    /**
     * Mails the logged-in admin, who is not a customer and has no newsletter
     * flag to consult, so the consent filter does not apply here.
     *
     * A JSON response rather than a redirect: this form posts from the
     * compose screen without saving the campaign first, so a redirect (even
     * with withInput()) has nothing to carry the unsaved audience selection
     * back with it. The page never navigates; window.vPost() toasts the
     * verdict in place.
     */
    public function test(Request $request)
    {
        $request->validate(['subject' => ['required', 'string'], 'body' => ['nullable', 'string']]);

        $admin = Auth::user();
        $storeName = StoreIdentity::name();

        $sample = [
            'first_name' => $admin->name,
            'last_name' => '',
            'email' => $admin->email,
            'store_name' => $storeName,
            'unsubscribe_url' => '#',
        ];

        $logo = Setting::where('group', 'identity')->where('key', 'logo')->value('value');

        Mail::to($admin->email)->send(new CampaignMail(
            CampaignRenderer::renderWith($request->input('subject'), $sample),
            CampaignRenderer::renderWith($request->input('body') ?? '', $sample),
            $storeName,
            $logo ? storage_url($logo) : null,
        ));

        return response()->json(['ok' => true, 'message' => 'Test sent to ' . $admin->email . '.']);
    }

    /**
     * Options for the "selected" audience's picklist. Not in the Task 6
     * brief's controller listing, but the compose screen's picklist (Step 7)
     * has nothing to render without it. Capped rather than unbounded: a
     * store-wide customer list is a checklist here, not a search index.
     *
     * A customer already targeted by a saved campaign is always included,
     * cap or no cap: the picklist only renders a checkbox for the ids it is
     * given, so leaving a selected id out of this list is how a saved
     * recipient gets silently dropped the next time the campaign is saved.
     *
     * @param array<int, int> $selectedIds
     */
    private function customerPickerOptions(array $selectedIds = [])
    {
        $selected = collect();

        if (! empty($selectedIds)) {
            $selected = Customer::where('store_id', 1)
                ->whereIn('id', $selectedIds)
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email']);
        }

        $pool = Customer::where('store_id', 1)
            ->when($selected->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $selected->pluck('id')))
            ->orderBy('first_name')->orderBy('last_name')
            ->limit(1000)
            ->get(['id', 'first_name', 'last_name', 'email']);

        return $selected->concat($pool)
            ->sortBy([['first_name', 'asc'], ['last_name', 'asc']])
            ->values();
    }
}
