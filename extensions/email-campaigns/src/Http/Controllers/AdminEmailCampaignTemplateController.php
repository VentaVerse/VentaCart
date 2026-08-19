<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Http\Request;

class AdminEmailCampaignTemplateController extends Controller
{
    public function index()
    {
        return view('email-campaigns::templates.index', [
            'templates' => EmailCampaignTemplate::orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('email-campaigns::templates.form', [
            'template' => null,
            'tokens' => CampaignRenderer::tokens(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = 1;

        EmailCampaignTemplate::create($data);

        return redirect()->route('admin.email-campaigns.templates.index')->with('success', 'Template saved.');
    }

    public function edit(EmailCampaignTemplate $template)
    {
        return view('email-campaigns::templates.form', [
            'template' => $template,
            'tokens' => CampaignRenderer::tokens(),
        ]);
    }

    public function update(Request $request, EmailCampaignTemplate $template)
    {
        $template->update($this->validated($request));

        return back()->with('success', 'Template saved.');
    }

    public function destroy(EmailCampaignTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.email-campaigns.templates.index')->with('success', 'Template deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);
    }
}
