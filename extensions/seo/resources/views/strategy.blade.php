<x-admin.seo-shell title="Strategy" active="strategy">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Strategy
                <x-admin.help text="What the engine knows about the store when it writes. Every generated title and description draws on these." /></h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.extensions.seo.save-strategy')" class="v-settle">
        <x-admin.panel title="Store description">
            <x-admin.field name="store_context"
                hint="Example: Online music gear store in the Philippines. We sell guitar pedals, cables, amplifiers, and accessories. Free shipping available. Cash on delivery.">
                <textarea name="store_context" rows="5" class="v-control">{{ old('store_context', $seoSettings['store_context'] ?? '') }}</textarea>
            </x-admin.field>
        </x-admin.panel>

        <x-admin.panel title="Target market">
            <x-admin.field name="target_market"
                hint="Example: Filipino musicians and guitar enthusiasts. Hobbyists and semi-professional players. Age 18-45. English and Taglish speakers.">
                <textarea name="target_market" rows="3" class="v-control">{{ old('target_market', $seoSettings['target_market'] ?? '') }}</textarea>
            </x-admin.field>
        </x-admin.panel>

        <x-admin.panel title="Brand voice and tone">
            <x-admin.field name="brand_voice"
                hint="Example: Friendly, knowledgeable, enthusiastic about gear. Simple English, no jargon. Mention the Philippines when relevant.">
                <textarea name="brand_voice" rows="3" class="v-control">{{ old('brand_voice', $seoSettings['brand_voice'] ?? '') }}</textarea>
            </x-admin.field>
        </x-admin.panel>

        <x-admin.panel title="Priority keywords">
            <x-admin.field name="priority_keywords"
                hint="One phrase per line. Each is worked in where it fits, never forced.">
                <textarea name="priority_keywords" rows="4" class="v-control v-mono">{{ old('priority_keywords', $seoSettings['priority_keywords'] ?? '') }}</textarea>
            </x-admin.field>
        </x-admin.panel>

        <x-admin.panel title="Avoid keywords">
            <x-admin.field name="avoid_keywords"
                hint="One phrase per line. None of them appear in generated copy.">
                <textarea name="avoid_keywords" rows="3" class="v-control v-mono">{{ old('avoid_keywords', $seoSettings['avoid_keywords'] ?? '') }}</textarea>
            </x-admin.field>
        </x-admin.panel>
    </x-admin.form>
</x-admin.seo-shell>
