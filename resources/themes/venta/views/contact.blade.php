<x-store-layout title="Contact Us">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-16">
        <div class="account-auth-card" style="max-width: 36rem;">
            <h1 class="account-auth-title text-center">CONTACT US</h1>
            <p class="text-sm text-[var(--color-text)] text-center mb-6">Have a question? We'd love to hear from you.</p>

            @if (session('success'))
                <div class="account-flash-success mb-6">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="account-flash-error mb-6">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('contact.submit') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="account-label">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Subject</label>
                        <select name="subject" required class="account-input w-full">
                            <option value="">Select a topic...</option>
                            <option value="General Inquiry" {{ old('subject') === 'General Inquiry' ? 'selected' : '' }}>General Inquiry</option>
                            <option value="Order Question" {{ old('subject') === 'Order Question' ? 'selected' : '' }}>Order Question</option>
                            <option value="Product Question" {{ old('subject') === 'Product Question' ? 'selected' : '' }}>Product Question</option>
                            <option value="Returns" {{ old('subject') === 'Returns' ? 'selected' : '' }}>Returns</option>
                            <option value="Other" {{ old('subject') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="account-label">Message</label>
                        <textarea name="message" rows="5" required
                                  class="account-input w-full" style="resize: vertical;">{{ old('message') }}</textarea>
                    </div>

                    <x-turnstile />

                    <button type="submit" class="account-btn-primary w-full">SEND MESSAGE</button>
                </div>
            </form>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
