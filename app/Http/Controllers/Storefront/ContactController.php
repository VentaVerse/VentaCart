<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Mail\ContactMail;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact');
    }

    public function submit(Request $request, TurnstileService $turnstile)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Captcha verification failed. Please try again.',
            ]);
        }

        $rateLimitKey = 'contact:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'email' => "Too many submissions. Please try again in {$seconds} seconds.",
            ]);
        }
        RateLimiter::hit($rateLimitKey, 600);

        Mail::send(new ContactMail($validated));

        return back()->with('success', 'Thank you for your message. We\'ll get back to you soon!');
    }
}
