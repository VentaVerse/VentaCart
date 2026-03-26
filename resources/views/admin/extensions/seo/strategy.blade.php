<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>
    @include('admin.extensions.seo.partials.tabs')

    <form method="POST" action="{{ route('admin.extensions.seo.save-strategy') }}">
        @csrf

        {{-- Store Context --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Store Description</h3>
            <p class="text-xs text-gray-500 mb-4">Describe your store in detail. Claude uses this as context for every SEO suggestion it generates.</p>

            <div class="max-w-2xl">
                <textarea name="store_context" rows="5" placeholder="Example: Online music gear store in the Philippines. We sell guitar pedals, cables, amplifiers, and accessories. Free shipping available. Cash on delivery. Installments via Billease. Based in Manila."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $seoSettings['store_context'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- Target Market --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Target Market</h3>
            <p class="text-xs text-gray-500 mb-4">Who are your customers? Where are they? Claude will optimize keywords and language for this audience.</p>

            <div class="max-w-2xl">
                <textarea name="target_market" rows="3" placeholder="Example: Filipino musicians and guitar enthusiasts in the Philippines. Hobbyists and semi-professional players. Age 18-45. English and Taglish speakers."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $seoSettings['target_market'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- Brand Voice --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Brand Voice &amp; Tone</h3>
            <p class="text-xs text-gray-500 mb-4">How should your SEO copy sound? This shapes the meta descriptions and OG tags Claude writes.</p>

            <div class="max-w-2xl">
                <textarea name="brand_voice" rows="3" placeholder="Example: Friendly, knowledgeable, enthusiastic about gear. Use simple English. Avoid overly technical jargon. Mention Philippines when relevant."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $seoSettings['brand_voice'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- Priority Keywords --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Priority Keywords</h3>
            <p class="text-xs text-gray-500 mb-4">Keywords or phrases Claude should try to incorporate when relevant. One per line.</p>

            <div class="max-w-2xl">
                <textarea name="priority_keywords" rows="4" placeholder="Example:&#10;Philippines&#10;guitar pedals Philippines&#10;buy guitar effects online&#10;free shipping&#10;cash on delivery"
                    class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">{{ $seoSettings['priority_keywords'] ?? '' }}</textarea>
            </div>
        </div>

        {{-- Avoid Keywords --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-1 uppercase tracking-wide">Avoid Keywords</h3>
            <p class="text-xs text-gray-500 mb-4">Words or phrases Claude should NOT use in SEO copy. One per line.</p>

            <div class="max-w-2xl">
                <textarea name="avoid_keywords" rows="3" placeholder="Example:&#10;cheap&#10;knockoff&#10;clone"
                    class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">{{ $seoSettings['avoid_keywords'] ?? '' }}</textarea>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Save Strategy
            </button>
        </div>
    </form>
</x-admin-layout>
