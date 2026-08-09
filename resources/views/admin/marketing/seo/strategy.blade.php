<x-admin.shell title="SEO Strategy">
    <x-admin.page title="Strategy">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.marketing.seo.partials.nav-v2')

            <form method="POST" action="{{ route('admin.extensions.seo.save-strategy') }}" class="min-w-0 flex-1 space-y-5">
                @csrf

                <x-admin.card title="Store Description">
                    <div class="max-w-2xl">
                        <x-admin.textarea name="store_context" rows="5" :value="$seoSettings['store_context'] ?? ''"
                            placeholder="Example: Online music gear store in the Philippines. We sell guitar pedals, cables, amplifiers, and accessories. Free shipping available. Cash on delivery. Installments via Billease. Based in Manila." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Target Market">
                    <div class="max-w-2xl">
                        <x-admin.textarea name="target_market" rows="3" :value="$seoSettings['target_market'] ?? ''"
                            placeholder="Example: Filipino musicians and guitar enthusiasts in the Philippines. Hobbyists and semi-professional players. Age 18-45. English and Taglish speakers." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Brand Voice & Tone">
                    <div class="max-w-2xl">
                        <x-admin.textarea name="brand_voice" rows="3" :value="$seoSettings['brand_voice'] ?? ''"
                            placeholder="Example: Friendly, knowledgeable, enthusiastic about gear. Use simple English. Avoid overly technical jargon. Mention Philippines when relevant." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Priority Keywords">
                    <x-slot name="headerActions">
                        <x-admin.hint label="Priority keywords format" class="v-hint--end">One phrase per line. Each is worked in where it fits, never forced.</x-admin.hint>
                    </x-slot>
                    <div class="max-w-2xl">
                        <x-admin.textarea name="priority_keywords" rows="4" :value="$seoSettings['priority_keywords'] ?? ''"
                            class="font-mono"
                            placeholder="Example:&#10;Philippines&#10;guitar pedals Philippines&#10;buy guitar effects online&#10;free shipping&#10;cash on delivery" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Avoid Keywords">
                    <x-slot name="headerActions">
                        <x-admin.hint label="Avoid keywords format" class="v-hint--end">One phrase per line. None of them appear in generated copy.</x-admin.hint>
                    </x-slot>
                    <div class="max-w-2xl">
                        <x-admin.textarea name="avoid_keywords" rows="3" :value="$seoSettings['avoid_keywords'] ?? ''"
                            class="font-mono"
                            placeholder="Example:&#10;cheap&#10;knockoff&#10;clone" />
                    </div>
                </x-admin.card>

                <div class="flex justify-end">
                    <x-admin.button type="submit" variant="primary">Save Strategy</x-admin.button>
                </div>
            </form>
        </div>
    </x-admin.page>
</x-admin.shell>
