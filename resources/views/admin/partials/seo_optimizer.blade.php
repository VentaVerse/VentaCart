{{-- AI SEO optimizer block, v dress, shared by the catalog forms.
     Usage: @include('admin.partials.seo_optimizer', ['entityType' => 'brand', 'entityId' => $brand?->id])
     Renders nothing while the SEO extension is disabled (its route is
     the switch), and only offers the button for a saved record. --}}
@if (Route::has('admin.extensions.seo.optimize') && ($entityId ?? null))
    <div class="v-gap" x-data="{
        loading: false,
        suggestion: null,
        error: null,
        async optimize() {
            this.loading = true;
            this.error = null;
            this.suggestion = null;
            try {
                const res = await fetch('{{ route("admin.extensions.seo.optimize") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ entity_type: '{{ $entityType }}', entity_id: {{ (int) $entityId }} })
                });
                const data = await res.json();
                if (data.ok) {
                    this.suggestion = data.suggestion;
                } else {
                    this.error = data.message || 'Failed to generate suggestion.';
                }
            } catch (e) {
                this.error = 'Request failed. Check the Claude API key under Extensions, SEO, Settings.';
            }
            this.loading = false;
        },
        apply() {
            if (!this.suggestion) return;
            const s = this.suggestion;
            const mt = document.querySelector('[name=meta_title]');
            const md = document.querySelector('[name=meta_description]');
            if (mt && s.suggested_meta_title) mt.value = s.suggested_meta_title;
            if (md && s.suggested_meta_description) md.value = s.suggested_meta_description;
            this.suggestion = null;
        }
    }">
        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="optimize()" :disabled="loading">
            <span x-text="loading ? 'Optimizing…' : 'Optimize with AI'"></span>
        </button>

        <p x-show="error" x-cloak class="v-field__error v-gap-sm" x-text="error"></p>

        <div x-show="suggestion" x-cloak class="v-well v-gap">
            <template x-for="field in ['meta_title', 'meta_description', 'og_title', 'og_description']" :key="field">
                <div class="v-gap-sm">
                    <span class="v-field__label" x-text="field.replace(/_/g, ' ')"></span>
                    <div class="v-grid v-grid--2">
                        <div>
                            <span class="v-cell-quiet v-block">Current</span>
                            <span x-text="suggestion?.['current_' + field] || '(empty)'"></span>
                        </div>
                        <div>
                            <span class="v-tone-act v-block">Suggested</span>
                            <span x-text="suggestion?.['suggested_' + field] || '(empty)'"></span>
                        </div>
                    </div>
                </div>
            </template>
            <div class="v-gap v-actrow">
                <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="apply(); touch()">Apply to form</button>
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="suggestion = null">Dismiss</button>
            </div>
        </div>
    </div>
@endif
