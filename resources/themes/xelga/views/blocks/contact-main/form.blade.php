{{--
    The message form: core's contact form, with its fields, names, checks,
    captcha and thank-you message. Topics are the merchant's own list; the
    one picked becomes the subject core sends.
--}}
@php
    $s = $block['settings'];
    $topics = $data['topics'][$block['id']] ?? [];
    $old = (string) old('subject', '');
    if ($old !== '' && ! in_array($old, $topics, true)) {
        $topics[] = $old;
    }
    $fid = $block['id'];
    $hints = ['name' => 'Enter your name.', 'email' => 'Enter an email address like name@example.com.', 'subject' => 'Choose a topic.', 'message' => 'Write your message.'];
@endphp
<div class="contact__form" data-block-id="{{ $block['id'] }}" data-block-type="form" @if ($block['disabled']) hidden @endif>
    <form class="form-card" action="{{ $data['action'] }}" method="post" novalidate data-contact-form>
        @csrf
        <h2 class="contact__subtitle" data-setting="heading">{{ $s['heading'] }}</h2>
        @if (session('success'))
            <div class="form-status form-status--ok" role="status" data-form-status><svg class="icon icon--sm" aria-hidden="true"><use href="#i-check"></use></svg><span>{{ session('success') }}</span></div>
        @elseif ($errors->any())
            <div class="form-status form-status--error" role="alert" data-form-status><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->has('cf-turnstile-response') ? $errors->first('cf-turnstile-response') : 'Check the fields marked below, then send again.' }}</span></div>
        @else
            <div class="form-status" role="status" aria-live="polite" data-form-status hidden></div>
        @endif
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="{{ $fid }}-name">Name</label>
                <input class="input" id="{{ $fid }}-name" name="name" type="text" autocomplete="name" required maxlength="255" value="{{ old('name') }}"
                       aria-describedby="{{ $fid }}-name-error" @if ($errors->has('name')) aria-invalid="true" @endif>
                <p class="field__error" id="{{ $fid }}-name-error" data-error-for="name" @if (! $errors->has('name')) hidden @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->has('name') ? $errors->first('name') : $hints['name'] }}</span></p>
            </div>
            <div class="field">
                <label class="field__label" for="{{ $fid }}-email">Email</label>
                <input class="input" id="{{ $fid }}-email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}"
                       aria-describedby="{{ $fid }}-email-error" @if ($errors->has('email')) aria-invalid="true" @endif>
                <p class="field__error" id="{{ $fid }}-email-error" data-error-for="email" @if (! $errors->has('email')) hidden @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->has('email') ? $errors->first('email') : $hints['email'] }}</span></p>
            </div>
            <div class="field field--full">
                <label class="field__label" for="{{ $fid }}-subject">Topic</label>
                @if ($topics === [])
                    <input class="input" id="{{ $fid }}-subject" name="subject" type="text" required maxlength="255" value="{{ $old }}"
                           aria-describedby="{{ $fid }}-subject-error" @if ($errors->has('subject')) aria-invalid="true" @endif>
                @else
                    <select class="select" id="{{ $fid }}-subject" name="subject" required aria-describedby="{{ $fid }}-subject-error" @if ($errors->has('subject')) aria-invalid="true" @endif>
                        <option value="">Choose a topic</option>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic }}" @selected($old === $topic)>{{ $topic }}</option>
                        @endforeach
                    </select>
                @endif
                <p class="field__error" id="{{ $fid }}-subject-error" data-error-for="subject" @if (! $errors->has('subject')) hidden @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->has('subject') ? $errors->first('subject') : $hints['subject'] }}</span></p>
            </div>
            <div class="field field--full">
                <label class="field__label" for="{{ $fid }}-message">Message</label>
                <textarea class="input textarea" id="{{ $fid }}-message" name="message" rows="6" required maxlength="5000"
                          aria-describedby="{{ $s['help'] !== '' ? $fid . '-message-help ' : '' }}{{ $fid }}-message-error" @if ($errors->has('message')) aria-invalid="true" @endif>{{ old('message') }}</textarea>
                @if ($s['help'] !== '')
                    <p class="field__help" id="{{ $fid }}-message-help" data-setting="help">{{ $s['help'] }}</p>
                @endif
                <p class="field__error" id="{{ $fid }}-message-error" data-error-for="message" @if (! $errors->has('message')) hidden @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->has('message') ? $errors->first('message') : $hints['message'] }}</span></p>
            </div>
        </div>
        <x-turnstile />
        <button type="submit" class="btn btn--block" data-setting="button_label">{{ $s['button_label'] !== '' ? $s['button_label'] : 'Send message' }}</button>
    </form>
</div>
