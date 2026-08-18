<x-admin.shell :title="'Edit ' . $column->title">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index', ['tab' => 'footer']) }}">Footer</a>
                <span aria-hidden="true">/</span>
                <span>{{ $column->title }}</span>
            </div>
            <h1 class="v-head__title">{{ $column->title }}
                <span class="v-badge v-badge--quiet">{{ $column->type }}</span>
            </h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.theme.footer.update', $column)" method="PUT" enctype="multipart/form-data" class="v-settle">
        {{-- Row state nests inside the doctrine form (the form tag already
             carries vForm's own x-data). --}}
        <div x-data="{
            links: @js($column->config['links'] ?? []),
            payIcons: @js(collect($column->config['icons'] ?? [])->map(fn ($icon) => is_string($icon) ? ['type' => 'text', 'value' => $icon] : $icon)->values()->toArray()),
        }">
        <x-admin.panel title="Footer column">
            <div class="v-grid v-grid--2">
                <x-admin.field name="title" label="Title" required>
                    <input type="text" name="title" value="{{ old('title', $column->title) }}" required class="v-control">
                </x-admin.field>
                <div class="v-addrow__opts">
                    <label class="v-toggle">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $column->is_enabled))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Shown in the footer
                    </label>
                </div>
            </div>

            <div class="v-gap">
                @if ($column->type === 'links')
                    <div class="v-label v-gap-sm">Links</div>
                    <template x-for="(link, idx) in links" :key="idx">
                        <div class="v-row">
                            <input type="text" :name="'config[links]['+idx+'][label]'" x-model="link.label" aria-label="Link label" placeholder="Label" class="v-control">
                            <input type="text" :name="'config[links]['+idx+'][url]'" x-model="link.url" aria-label="Link URL" placeholder="/slug" class="v-control v-mono">
                            <button type="button" class="v-row__x" @click="links.splice(idx, 1); $nextTick(() => touch())" aria-label="Remove link"><x-admin.icon name="x" /></button>
                        </div>
                    </template>
                    <div class="v-gap-sm">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="links.push({label: '', url: ''}); $nextTick(() => touch())"><x-admin.icon name="plus" /> Add link</button>
                    </div>

                @elseif ($column->type === 'social')
                    <div class="v-label v-gap-sm">Social profiles</div>
                    <template x-for="(social, idx) in links" :key="idx">
                        <div class="v-row">
                            <select :name="'config[links]['+idx+'][platform]'" x-model="social.platform" aria-label="Social platform" class="v-control v-w-name">
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="youtube">YouTube</option>
                                <option value="tiktok">TikTok</option>
                            </select>
                            <input type="text" :name="'config[links]['+idx+'][url]'" x-model="social.url" aria-label="Social profile URL" placeholder="https://…" class="v-control v-mono">
                            <button type="button" class="v-row__x" @click="links.splice(idx, 1); $nextTick(() => touch())" aria-label="Remove social link"><x-admin.icon name="x" /></button>
                        </div>
                    </template>
                    <div class="v-gap-sm">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="links.push({platform: 'facebook', url: ''}); $nextTick(() => touch())"><x-admin.icon name="plus" /> Add social link</button>
                    </div>

                @elseif ($column->type === 'payment_icons')
                    <div class="v-label v-gap-sm">Payment marks</div>
                    <template x-for="(icon, idx) in payIcons" :key="idx">
                        <div class="v-row">
                            <select :name="'config[icons]['+idx+'][type]'" x-model="icon.type" aria-label="Payment mark kind" class="v-control v-w-sort">
                                <option value="text">Text</option>
                                <option value="image">Image</option>
                            </select>
                            <template x-if="icon.type === 'text'">
                                <input type="text" :name="'config[icons]['+idx+'][value]'" x-model="icon.value" aria-label="Payment mark label" class="v-control">
                            </template>
                            <template x-if="icon.type === 'image'">
                                <span class="v-actrow">
                                    <template x-if="icon.value">
                                        <img :src="icon.value.startsWith('http') || icon.value.startsWith('/') ? icon.value : '/storage/' + icon.value" class="v-payicon" alt="">
                                    </template>
                                    <input type="hidden" :name="'config[icons]['+idx+'][value]'" :value="icon.value">
                                    <input type="file" :name="'icon_uploads['+idx+']'" accept="image/*" aria-label="Payment mark image" class="v-file">
                                </span>
                            </template>
                            <button type="button" class="v-row__x" @click="payIcons.splice(idx, 1); $nextTick(() => touch())" aria-label="Remove payment icon"><x-admin.icon name="x" /></button>
                        </div>
                    </template>
                    <div class="v-gap-sm">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="payIcons.push({type: 'text', value: ''}); $nextTick(() => touch())"><x-admin.icon name="plus" /> Add mark</button>
                    </div>

                @elseif ($column->type === 'text')
                    <x-admin.field name="config.content" label="Text">
                        <textarea name="config[content]" rows="4" class="v-control">{{ old('config.content', $column->config['content'] ?? '') }}</textarea>
                    </x-admin.field>
                @endif
            </div>
        </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.shell>
