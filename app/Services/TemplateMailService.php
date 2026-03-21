<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TemplateMailService
{
    public static function send(string $slug, string $to, array $data): bool
    {
        $template = EmailTemplate::where('slug', $slug)->where('is_active', true)->first();

        if (!$template) {
            Log::warning("TemplateMailService: template '{$slug}' not found or disabled");
            return false;
        }

        try {
            Mail::to($to)->send(new TemplateMail($template, $data));
            return true;
        } catch (\Throwable $e) {
            Log::error("TemplateMailService: failed to send '{$slug}' to {$to}", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
