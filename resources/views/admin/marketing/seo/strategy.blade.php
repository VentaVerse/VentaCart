<x-admin3.seo-shell title="Strategy" active="strategy">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Strategy
                <x-admin3.help text="What the engine knows about the store when it writes. Every generated title and description draws on these." /></h1>
        </div>
    </div>

    <x-admin3.form :action="route('admin.extensions.seo.save-strategy')" class="v3-settle">
        <x-admin3.panel title="Store description">
            <x-admin3.field name="store_context"
                hint="Example: Online music gear store in the Philippines. We sell guitar pedals, cables, amplifiers, and accessories. Free shipping available. Cash on delivery.">
                <textarea name="store_context" rows="5" class="v3-control">{{ old('store_context', $seoSettings['store_context'] ?? '') }}</textarea>
            </x-admin3.field>
        </x-admin3.panel>

        <x-admin3.panel title="Target market">
            <x-admin3.field name="target_market"
                hint="Example: Filipino musicians and guitar enthusiasts. Hobbyists and semi-professional players. Age 18-45. English and Taglish speakers.">
                <textarea name="target_market" rows="3" class="v3-control">{{ old('target_market', $seoSettings['target_market'] ?? '') }}</textarea>
            </x-admin3.field>
        </x-admin3.panel>

        <x-admin3.panel title="Brand voice and tone">
            <x-admin3.field name="brand_voice"
                hint="Example: Friendly, knowledgeable, enthusiastic about gear. Simple English, no jargon. Mention the Philippines when relevant.">
                <textarea name="brand_voice" rows="3" class="v3-control">{{ old('brand_voice', $seoSettings['brand_voice'] ?? '') }}</textarea>
            </x-admin3.field>
        </x-admin3.panel>

        <x-admin3.panel title="Priority keywords">
            <x-admin3.field name="priority_keywords"
                hint="One phrase per line. Each is worked in where it fits, never forced.">
                <textarea name="priority_keywords" rows="4" class="v3-control v3-mono">{{ old('priority_keywords', $seoSettings['priority_keywords'] ?? '') }}</textarea>
            </x-admin3.field>
        </x-admin3.panel>

        <x-admin3.panel title="Avoid keywords">
            <x-admin3.field name="avoid_keywords"
                hint="One phrase per line. None of them appear in generated copy.">
                <textarea name="avoid_keywords" rows="3" class="v3-control v3-mono">{{ old('avoid_keywords', $seoSettings['avoid_keywords'] ?? '') }}</textarea>
            </x-admin3.field>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.seo-shell>
