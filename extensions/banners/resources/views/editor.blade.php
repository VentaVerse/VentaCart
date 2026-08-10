<x-admin.shell :title="$banner ? 'Edit Slide: ' . ($banner->name ?: '#'.$banner->id) : 'New Slide'">
    @push('head')
        @vite('extensions/banners/resources/css/banners.css')
    @endpush
    <x-admin.page :title="$banner ? 'Edit Slide: ' . ($banner->name ?: '#'.$banner->id) : 'New Slide'"
        :breadcrumb="[['label' => $group->name, 'url' => route('admin.banners.slides', $group)]]">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.banners.slides', $group) }}">Back to Slides</x-admin.button>
        </x-slot:actions>

        <form method="POST"
              action="{{ $banner ? route('admin.banners.slides.update', [$group, $banner]) : route('admin.banners.slides.store', $group) }}"
              x-data="bannerEditor()" data-v-form
              class="space-y-5">
            @csrf
            @if ($banner) @method('PUT') @endif
            <input type="hidden" name="layers" :value="JSON.stringify(layers)">
            <input type="hidden" name="_selected_layer" :value="selectedLayer">

            {{-- ============ FULL-WIDTH PREVIEW ============ --}}
            <div class="v-panel transition-all duration-300 isolate"
                 :style="isFullWidth ? 'margin-left: -2rem; margin-right: -2rem; border-radius: 0; border-left: 0; border-right: 0;' : ''">
                <div class="v-panel__head">
                    <h2 class="v-subhead">Preview</h2>
                    <span class="v-panel__note" x-text="isFullWidth ? 'Full width' : 'Contained'"></span>
                    <span class="ml-auto flex items-center gap-3">
                        <x-admin.button type="button" size="sm" variant="ghost" @click="playAnimations()">
                            <x-admin.icon name="play" class="w-3.5 h-3.5" /> Play
                        </x-admin.button>
                        <span class="v-mono text-[11px] text-v-muted" x-text="previewWidth + '×' + height + 'px'"></span>
                    </span>
                </div>
                <div class="banner-stage">
                    <div class="relative overflow-hidden w-full" x-ref="previewCanvas"
                         @mousemove.prevent="onDrag($event)"
                         @mouseup.prevent="stopDrag()"
                         @mouseleave="stopDrag()"
                         :style="'height: ' + height + 'px; background-color: ' + bgColor + '; background-size: ' + bgSize + '; background-position: center; background-repeat: no-repeat;' + (bgImageUrl ? 'background-image: url(' + bgImageUrl + ')' : '')">
                        {{-- Centered stage container for layers (matches 1280px storefront stage) --}}
                        <div class="banner-frame">
                        <template x-for="(layer, i) in layers" :key="i">
                            <div :style="getLayerStyle(layer) + '; pointer-events: auto; z-index: ' + (selectedLayer === i ? 999 : 10 + i) + (animating && layer.delay ? '; animation-delay: ' + layer.delay + 'ms' : '')"
                                 :class="[
                                    selectedLayer === i ? 'ring-2 ring-v-accent ring-offset-1' : '',
                                    animating && layer.animation && layer.animation !== 'none' ? 'banner-anim banner-anim-' + layer.animation : ''
                                 ]"
                                 @mousedown.prevent="startDrag($event, i)"
                                 @click="selectedLayer = i"
                                 class="absolute cursor-move select-none">

                                {{-- Text layer --}}
                                <div x-show="layer.type === 'text'" x-text="layer.content || 'Text'" class="banner-layer-text"></div>

                                {{-- Image layer --}}
                                <img x-show="layer.type === 'image'" alt="" aria-hidden="true"
                                     :src="layer.src ? '/storage/' + layer.src : 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/></svg>'"
                                     :style="'width: ' + (layer.width || 200) + 'px; height: ' + (layer.height || 'auto')"
                                     class="block max-w-none pointer-events-none" draggable="false">

                                {{-- Button layer --}}
                                <span x-show="layer.type === 'button'"
                                      :style="'background: ' + (layer.bgColor || '#ffffff') + '; color: ' + (layer.color || '#000000') + '; padding: ' + (layer.paddingY || 12) + 'px ' + (layer.paddingX || 32) + 'px; border-radius: ' + (layer.borderRadius || 0) + 'px; font-size: ' + (layer.fontSize || 14) + 'px; font-weight: ' + (layer.fontWeight || 600) + '; letter-spacing: ' + (layer.letterSpacing || 1) + 'px; pointer-events: none;'"
                                      class="banner-layer-button" x-text="layer.content || 'BUTTON'"></span>

                                {{-- Resize handle --}}
                                <div x-show="selectedLayer === i"
                                     @mousedown.stop.prevent="startResize($event, i)"
                                     class="banner-handle absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-v-accent border border-white rounded-v-sm cursor-se-resize shadow-v-lift"></div>
                            </div>
                        </template>
                        </div>{{-- close stage --}}
                    </div>
                </div>
            </div>

            {{-- ============ TWO-COLUMN LAYOUT: LEFT + RIGHT ============ --}}
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                {{-- ============ LEFT COLUMN: Background + Slide Settings ============ --}}
                <div class="space-y-5">

                    {{-- Background Settings --}}
                    <x-admin.card>
                        <h2 class="v-label mb-4">Background</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                @include('admin.partials.single_image_picker', [
                                    'fieldName' => 'bg_image',
                                    'currentImage' => $banner?->bg_image,
                                    'label' => 'Desktop Image',
                                    'hint' => '1920×600 recommended',
                                    'defaultFolder' => 'Banners',
                                ])
                            </div>
                            <div>
                                @include('admin.partials.single_image_picker', [
                                    'fieldName' => 'bg_image_mobile',
                                    'currentImage' => $banner?->bg_image_mobile,
                                    'label' => 'Mobile Image (optional)',
                                    'hint' => '768×600 recommended',
                                    'defaultFolder' => 'Banners',
                                ])
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-3 mt-4">
                            <div>
                                <label for="bg_color" class="block text-xs font-medium text-v-secondary mb-1">BG Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="bg_color" id="bg_color" x-model="bgColor" class="w-8 h-8 shrink-0 rounded-v-sm border border-v-border cursor-pointer p-0.5 bg-v-surface">
                                    <span class="text-[11px] text-v-muted font-mono" x-text="bgColor"></span>
                                </div>
                            </div>
                            <div>
                                <label for="bg_size" class="block text-xs font-medium text-v-secondary mb-1">BG Size</label>
                                <select name="bg_size" id="bg_size" x-model="bgSize" class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="auto">Auto</option>
                                    <option value="100% 100%">Stretch</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-v-secondary mb-1">Height</label>
                                <div class="flex items-center gap-1">
                                    <span class="text-[13px] text-v-text" x-text="height + 'px'"></span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-v-secondary mb-1">Mobile H.</label>
                                <div class="flex items-center gap-1">
                                    <span class="text-[13px] text-v-text" x-text="heightMobile + 'px'"></span>
                                </div>
                                <a href="{{ route('admin.banners.edit', $group) }}" class="inline-flex min-h-[24px] items-center text-[10px] text-v-accent hover:opacity-80">Change in group settings</a>
                            </div>
                        </div>
                    </x-admin.card>

                    {{-- Slide Settings --}}
                    <x-admin.card>
                        <h2 class="v-label mb-3">Slide Settings</h2>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <x-admin.field name="name" label="Name">
                                    <x-admin.input name="name" :value="$banner?->name" placeholder="e.g. Summer Sale" />
                                </x-admin.field>
                                <x-admin.field name="link" label="Link URL">
                                    <x-admin.input name="link" :value="$banner?->link" placeholder="/category/..." />
                                </x-admin.field>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <label class="v-check text-xs text-v-secondary">
                                        <input type="hidden" name="link_new_tab" value="0">
                                        <input type="checkbox" name="link_new_tab" value="1" {{ ($banner?->link_new_tab) ? 'checked' : '' }} class="w-3.5 h-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                        New tab
                                    </label>
                                    <span class="text-xs text-v-muted" x-text="isFullWidth ? 'Full width' : 'Contained'"></span>
                                    <label class="v-check text-xs text-v-secondary">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" {{ ($banner?->is_active ?? true) ? 'checked' : '' }} class="w-3.5 h-3.5 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                        Active
                                    </label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label for="sort_order" class="v-label">Sort</label>
                                    <input type="number" name="sort_order" id="sort_order" value="{{ $banner?->sort_order ?? 0 }}" min="0" class="v-control w-14 h-[var(--control-h-sm)] text-[13px] text-center bg-v-surface text-v-text border border-v-border rounded-v-md">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <x-admin.field name="starts_at" label="Visible from">
                                    <x-admin.input type="date" name="starts_at" :value="$banner?->starts_at?->format('Y-m-d')" />
                                </x-admin.field>
                                <x-admin.field name="ends_at" label="Visible until">
                                    <x-admin.input type="date" name="ends_at" :value="$banner?->ends_at?->format('Y-m-d')" />
                                </x-admin.field>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                {{-- ============ RIGHT COLUMN: Layers + Properties ============ --}}
                <div class="space-y-5">

                    {{-- Layers Panel --}}
                    <div class="v-panel">
                        <div class="v-panel__head">
                            <h2 class="v-subhead">Layers</h2>
                            <div class="ml-auto flex gap-1">
                                <x-admin.button type="button" size="sm" variant="ghost" @click="addLayer('text')">
                                    <x-admin.icon name="text" class="w-3.5 h-3.5" /> Text
                                </x-admin.button>
                                <x-admin.button type="button" size="sm" variant="ghost" @click="addLayer('image')">
                                    <x-admin.icon name="media" class="w-3.5 h-3.5" /> Image
                                </x-admin.button>
                                <x-admin.button type="button" size="sm" variant="ghost" @click="addLayer('button')">
                                    <x-admin.icon name="link" class="w-3.5 h-3.5" /> Button
                                </x-admin.button>
                            </div>
                        </div>

                        {{-- Layer List --}}
                        <div class="divide-y divide-v-border max-h-52 overflow-y-auto">
                            <template x-if="layers.length === 0">
                                <div>
                                    <x-admin.empty-state icon="layers" title="No layers yet" />
                                </div>
                            </template>
                            <template x-for="(layer, i) in layers" :key="i">
                                <div @click="selectedLayer = i"
                                     class="flex items-center gap-2 px-3 py-2 cursor-pointer text-[13px] transition-colors"
                                     :class="selectedLayer === i ? 'bg-v-accent-weak border-l-2 border-v-accent' : 'hover:bg-v-hover border-l-2 border-transparent'">
                                    <span class="text-[10px] font-semibold shrink-0 w-11 text-center rounded-v-sm py-0.5"
                                          :class="layer.type === 'text' ? 'text-v-info bg-[color-mix(in_srgb,var(--info)_12%,transparent)]' : (layer.type === 'image' ? 'text-v-accent bg-v-accent-weak' : 'text-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)]')"
                                          x-text="layer.type"></span>
                                    <span class="flex-1 truncate text-xs text-v-secondary" x-text="layer.content || layer.src?.split('/').pop() || '(empty)'"></span>
                                    <button type="button" @click.stop="removeLayer(i)" class="v-badge__x" :aria-label="'Remove layer ' + (i + 1)" title="Remove layer">
                                        <x-admin.icon name="x" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Selected Layer Properties --}}
                    <div x-show="selectedLayer !== null && layers[selectedLayer]" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-admin.card>
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="v-label">Layer Properties</h2>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-semibold shrink-0 px-1.5 py-0.5 rounded-v-sm"
                                          :class="currentLayer?.type === 'text' ? 'text-v-info bg-[color-mix(in_srgb,var(--info)_12%,transparent)]' : (currentLayer?.type === 'image' ? 'text-v-accent bg-v-accent-weak' : 'text-v-warning bg-[color-mix(in_srgb,var(--warning)_12%,transparent)]')"
                                          x-text="currentLayer?.type"></span>
                                    <span class="text-[10px] text-v-muted/70 font-mono" x-text="'#' + (selectedLayer + 1)"></span>
                                </div>
                            </div>

                            <template x-if="selectedLayer !== null && layers[selectedLayer]">
                                <div class="space-y-3">
                                    {{-- Content (text/button) --}}
                                    <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Content</label>
                                        <input type="text" x-model="currentLayer.content" class="v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="Enter text...">
                                    </div>

                                    {{-- Image source --}}
                                    <div x-show="currentLayer?.type === 'image'">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Image</label>
                                        <div class="flex items-center gap-1.5 mb-2">
                                            <button type="button" @click="openLayerImageBrowser()"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-v-accent text-v-accent-text text-[11px] font-medium rounded-v-md hover:bg-v-accent-hover transition-colors">
                                                <x-admin.icon name="folder" class="w-3 h-3" />
                                                Browse
                                            </button>
                                        </div>
                                        <template x-if="currentLayer?.src">
                                            <div class="flex items-center gap-2 p-1.5 bg-v-sunken rounded-v-md">
                                                <img :src="'/storage/' + currentLayer.src" alt="" aria-hidden="true" class="h-10 w-10 rounded-v-sm border border-v-border object-cover shrink-0">
                                                <span class="text-[10px] text-v-muted font-mono truncate flex-1" x-text="currentLayer.src.split('/').pop()"></span>
                                                <button type="button" @click="currentLayer.src = ''" aria-label="Clear layer image" class="text-v-muted hover:text-v-danger text-xs p-0.5 transition-colors">
                                                    <x-admin.icon name="x" class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Position --}}
                                    <div>
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Position</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted font-mono">X</span>
                                                <input type="text" x-model="currentLayer.x" class="v-control w-full h-[var(--control-h)] pl-6 pr-3 text-[13px] font-mono bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="0">
                                            </div>
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted font-mono">Y</span>
                                                <input type="text" x-model="currentLayer.y" class="v-control w-full h-[var(--control-h)] pl-6 pr-3 text-[13px] font-mono bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="0">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Size (image) --}}
                                    <div x-show="currentLayer?.type === 'image'">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Size</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted font-mono">W</span>
                                                <input type="number" x-model.number="currentLayer.width" class="v-control w-full h-[var(--control-h)] pl-6 pr-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="200">
                                            </div>
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted font-mono">H</span>
                                                <input type="number" x-model.number="currentLayer.height" class="v-control w-full h-[var(--control-h)] pl-6 pr-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="auto">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Typography (text/button) --}}
                                    <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Typography</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted">Aa</span>
                                                <input type="number" x-model.number="currentLayer.fontSize" class="v-control w-full h-[var(--control-h)] pl-7 pr-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="24">
                                            </div>
                                            <select x-model="currentLayer.fontWeight" class="v-control h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                <option value="300">Light</option>
                                                <option value="400">Regular</option>
                                                <option value="500">Medium</option>
                                                <option value="600">Semi</option>
                                                <option value="700">Bold</option>
                                                <option value="900">Black</option>
                                            </select>
                                            <input type="color" x-model="currentLayer.color" class="w-full h-[34px] rounded-v-sm border border-v-border cursor-pointer p-0.5 bg-v-surface">
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mt-2">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-v-muted">LS</span>
                                                <input type="number" x-model.number="currentLayer.letterSpacing" class="v-control w-full h-[var(--control-h)] pl-7 pr-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="0">
                                            </div>
                                            <select x-model="currentLayer.textTransform" class="v-control h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                <option value="none">Normal</option>
                                                <option value="uppercase">UPPERCASE</option>
                                                <option value="capitalize">Capitalize</option>
                                            </select>
                                        </div>
                                    </div>

                                    {{-- Button styling --}}
                                    <div x-show="currentLayer?.type === 'button'">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1">Button Style</label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <span class="text-[10px] text-v-muted mb-0.5 block">BG</span>
                                                <input type="color" x-model="currentLayer.bgColor" class="w-full h-8 rounded-v-sm border border-v-border cursor-pointer p-0.5 bg-v-surface">
                                            </div>
                                            <div class="relative">
                                                <span class="text-[10px] text-v-muted mb-0.5 block">Radius</span>
                                                <input type="number" x-model.number="currentLayer.borderRadius" class="v-control w-full h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="0">
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-v-muted mb-0.5 block">URL</span>
                                                <input type="text" x-model="currentLayer.url" class="v-control w-full h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="/shop">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Animation --}}
                                    <div class="pt-2 border-t border-v-border space-y-2">
                                        <label class="block text-[11px] font-medium text-v-secondary mb-1.5">Animation</label>
                                        <select x-model="currentLayer.animation" class="v-control w-full h-[var(--control-h)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <optgroup label="None">
                                                <option value="none">None</option>
                                            </optgroup>
                                            <optgroup label="Fade">
                                                <option value="fade-in">Fade In</option>
                                                <option value="fade-up">Fade Up</option>
                                                <option value="fade-down">Fade Down</option>
                                                <option value="fade-left">Fade Left</option>
                                                <option value="fade-right">Fade Right</option>
                                            </optgroup>
                                            <optgroup label="Zoom">
                                                <option value="zoom-in">Zoom In</option>
                                                <option value="zoom-out">Zoom Out</option>
                                            </optgroup>
                                            <optgroup label="Slide">
                                                <option value="slide-up">Slide Up</option>
                                                <option value="slide-down">Slide Down</option>
                                                <option value="slide-left">Slide Left</option>
                                                <option value="slide-right">Slide Right</option>
                                            </optgroup>
                                            <optgroup label="Bounce">
                                                <option value="bounce-in">Bounce In</option>
                                                <option value="bounce-up">Bounce Up</option>
                                            </optgroup>
                                            <optgroup label="Rotate">
                                                <option value="rotate-in">Rotate In</option>
                                                <option value="flip-x">Flip X</option>
                                                <option value="flip-y">Flip Y</option>
                                            </optgroup>
                                        </select>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <label class="block text-[10px] text-v-muted mb-0.5">Delay (ms)</label>
                                                <input type="number" x-model.number="currentLayer.delay" step="100" min="0" class="v-control w-full h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="0">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-v-muted mb-0.5">Duration (ms)</label>
                                                <input type="number" x-model.number="currentLayer.duration" step="100" min="100" class="v-control w-full h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="800">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] text-v-muted mb-0.5">Distance (px)</label>
                                                <input type="number" x-model.number="currentLayer.distance" step="10" min="0" class="v-control w-full h-[var(--control-h)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md" placeholder="30">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Opacity --}}
                                    <div class="flex items-center gap-3">
                                        <label class="text-[11px] font-medium text-v-secondary shrink-0">Opacity</label>
                                        <input type="range" x-model.number="currentLayer.opacity" min="0" max="100" step="5" class="flex-1 h-1.5 accent-[var(--accent)]">
                                        <span class="text-[11px] text-v-muted font-mono w-8 text-right" x-text="(currentLayer.opacity ?? 100) + '%'"></span>
                                    </div>
                                </div>
                            </template>
                        </x-admin.card>
                    </div>

                    {{-- Save --}}
                    <x-admin.button type="submit" class="w-full justify-center py-3">
                        {{ $banner ? 'Update Slide' : 'Create Slide' }}
                    </x-admin.button>
                </div>
            </div>

            {{-- Layer Image Browser Modal --}}
            <div x-show="layerBrowserOpen" x-transition.opacity.duration.200ms
                 class="banner-veil fixed inset-0 z-[9999] flex items-center justify-center p-4"
                 @keydown.escape.window="layerBrowserOpen = false">

                <div class="w-full max-w-5xl max-h-[85vh] flex flex-col bg-v-surface rounded-v-lg shadow-v-lift-2 border border-v-border overflow-hidden"
                     @click.outside="layerBrowserOpen = false">

                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-v-border bg-v-sunken">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex items-center gap-2">
                                <x-admin.icon name="media" class="w-5 h-5 text-v-accent shrink-0" />
                                <h3 class="text-[13px] font-semibold text-v-text">Select Layer Image</h3>
                            </div>
                            <span class="text-v-border">|</span>
                            <nav class="flex items-center gap-0.5 text-[13px] min-w-0 overflow-x-auto">
                                <button type="button" @click="browseLayerDir('')" class="text-v-accent hover:opacity-80 transition-colors shrink-0 font-medium">catalog</button>
                                <template x-for="(crumb, ci) in layerBreadcrumbs" :key="ci">
                                    <span class="flex items-center gap-0.5 shrink-0">
                                        <span class="text-v-muted">/</span>
                                        <button type="button" @click="browseLayerDir(crumb.path)" class="text-v-accent hover:opacity-80 transition-colors font-medium" x-text="crumb.name"></button>
                                    </span>
                                </template>
                            </nav>
                        </div>
                        <button type="button" @click="layerBrowserOpen = false" aria-label="Close image browser" class="text-v-muted hover:text-v-text transition-colors p-1 rounded-v-md hover:bg-v-hover shrink-0 ml-3">
                            <x-admin.icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="flex items-center gap-3 px-5 py-3 border-b border-v-border bg-v-surface">
                        <div class="relative">
                            <x-admin.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-v-muted" />
                            <input type="text" x-model="layerBrowserSearch" aria-label="Search images" placeholder="Search..."
                                class="v-control w-52 h-[var(--control-h)] pl-9 pr-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md placeholder:text-v-muted">
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-5 bg-v-sunken/50">
                        <div x-show="layerBrowserLoading" class="flex items-center justify-center py-16">
                            <x-admin.icon name="spinner" class="w-6 h-6 animate-spin text-v-accent" />
                        </div>

                        <div x-show="!layerBrowserLoading" class="banner-picker-grid grid gap-2">
                            <template x-if="layerBrowserPath !== ''">
                                <button type="button" @click="browseLayerDir(layerBrowserParent)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                                    <x-admin.icon name="folder" class="w-10 h-10 text-v-warning group-hover:opacity-80 transition-opacity" />
                                    <span class="text-xs text-v-muted mt-1.5 font-medium">..</span>
                                </button>
                            </template>

                            <template x-for="folder in layerBrowserFolders" :key="folder.path">
                                <button type="button" @click="browseLayerDir(folder.path)" class="flex flex-col items-center p-3 rounded-v-lg hover:bg-v-hover transition-colors group">
                                    <x-admin.icon name="folder" class="w-10 h-10 text-v-warning group-hover:opacity-80 transition-opacity" />
                                    <span class="text-xs text-v-secondary mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                                </button>
                            </template>

                            <template x-for="file in filteredLayerBrowserFiles" :key="file.path">
                                <button type="button" @click="selectLayerImage(file)" class="flex flex-col items-center p-2 rounded-v-lg hover:bg-v-hover transition-colors w-full">
                                    <div class="w-full aspect-square rounded-v-md overflow-hidden bg-v-sunken border border-v-border">
                                        <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                    </div>
                                    <span class="text-[11px] text-v-muted mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="!layerBrowserLoading && layerBrowserFolders.length === 0 && layerBrowserFiles.length === 0"
                             class="text-center py-16">
                            <p class="text-[13px] text-v-secondary font-medium">Empty folder</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @push('head')
        {{-- Banner animation keyframes for admin preview --}}
        <script>
        function bannerEditor() {
            return {
                layers: @json($banner?->layers ?? []),
                selectedLayer: null,
                bgColor: '{{ $banner?->bg_color ?? '#000000' }}',
                bgSize: '{{ $banner?->bg_size ?? 'cover' }}',
                bgImageUrl: '{{ $banner?->bg_image_url ?? '' }}',
                height: {{ $group->height ?? 500 }},
                heightMobile: {{ $group->height_mobile ?? 400 }},
                previewWidth: 0,
                isFullWidth: {{ ($group->full_width ?? true) ? 'true' : 'false' }},

                dragging: null,
                resizing: null,
                animating: false,

                layerBrowserOpen: false,
                layerBrowserLoading: false,
                layerBrowserPath: '',
                layerBrowserParent: '',
                layerBrowserFolders: [],
                layerBrowserFiles: [],
                layerBrowserSearch: '',
                lastBrowsedLayerPath: null,

                init() {
                    this.previewWidth = this.$refs.previewCanvas?.offsetWidth || 900;

                    // Restore selected layer from URL hash (e.g. #layer-2)
                    const hash = window.location.hash;
                    if (hash && hash.startsWith('#layer-')) {
                        const idx = parseInt(hash.substring(7));
                        if (!isNaN(idx) && idx >= 0 && idx < this.layers.length) {
                            this.selectedLayer = idx;
                        }
                        history.replaceState(null, '', window.location.pathname + window.location.search);
                    }

                    this.$watch('isFullWidth', () => {
                        this.$nextTick(() => {
                            this.previewWidth = this.$refs.previewCanvas?.offsetWidth || 900;
                        });
                    });

                    this.$watch('layers', () => {}, { deep: true });

                    const self = this;
                    const observer = new MutationObserver(() => {
                        const bgInput = document.querySelector('input[name="bg_image"]');
                        if (bgInput && bgInput.value) {
                            self.bgImageUrl = '/storage/' + bgInput.value;
                        } else if (bgInput) {
                            self.bgImageUrl = '';
                        }
                    });
                    this.$nextTick(() => {
                        const bgInput = document.querySelector('input[name="bg_image"]');
                        if (bgInput) {
                            observer.observe(bgInput, { attributes: true, attributeFilter: ['value'] });
                            setInterval(() => {
                                const val = bgInput.value;
                                const url = val ? '/storage/' + val : '';
                                if (url !== self.bgImageUrl) self.bgImageUrl = url;
                            }, 500);
                        }
                    });
                },

                get currentLayer() {
                    return this.selectedLayer !== null ? this.layers[this.selectedLayer] : null;
                },

                addLayer(type) {
                    const defaults = {
                        text: { type: 'text', content: 'New Text', x: '50px', y: '50px', fontSize: 32, fontWeight: '700', color: '#ffffff', letterSpacing: 1, textTransform: 'none', animation: 'fade-up', delay: 0, opacity: 100 },
                        image: { type: 'image', src: '', x: '0', y: '0', width: 200, height: null, animation: 'fade-in', delay: 0, opacity: 100 },
                        button: { type: 'button', content: 'SHOP NOW', url: '#', x: '50px', y: '200px', fontSize: 14, fontWeight: '600', color: '#000000', bgColor: '#ffffff', borderRadius: 0, paddingX: 32, paddingY: 12, letterSpacing: 2, textTransform: 'uppercase', animation: 'fade-up', delay: 200, opacity: 100 },
                    };
                    this.layers.push({ ...defaults[type] });
                    this.selectedLayer = this.layers.length - 1;
                },

                removeLayer(i) {
                    this.layers.splice(i, 1);
                    if (this.selectedLayer >= this.layers.length) {
                        this.selectedLayer = this.layers.length > 0 ? this.layers.length - 1 : null;
                    }
                },

                startDrag(event, index) {
                    this.selectedLayer = index;
                    const layer = this.layers[index];
                    this.dragging = {
                        layerIndex: index,
                        startX: event.clientX,
                        startY: event.clientY,
                        origX: parseInt(layer.x) || 0,
                        origY: parseInt(layer.y) || 0,
                    };
                },

                onDrag(event) {
                    if (this.dragging) {
                        const dx = event.clientX - this.dragging.startX;
                        const dy = event.clientY - this.dragging.startY;
                        const layer = this.layers[this.dragging.layerIndex];
                        layer.x = (this.dragging.origX + dx) + 'px';
                        layer.y = (this.dragging.origY + dy) + 'px';
                    } else if (this.resizing) {
                        const dx = event.clientX - this.resizing.startX;
                        const dy = event.clientY - this.resizing.startY;
                        const layer = this.layers[this.resizing.layerIndex];
                        const newW = Math.max(20, this.resizing.origW + dx);
                        layer.width = Math.round(newW);
                        if (this.resizing.origH) {
                            const newH = Math.max(20, this.resizing.origH + dy);
                            layer.height = Math.round(newH);
                        }
                    }
                },

                stopDrag() {
                    this.dragging = null;
                    this.resizing = null;
                },

                startResize(event, index) {
                    this.selectedLayer = index;
                    const layer = this.layers[index];
                    this.resizing = {
                        layerIndex: index,
                        startX: event.clientX,
                        startY: event.clientY,
                        origW: parseInt(layer.width) || 200,
                        origH: parseInt(layer.height) || null,
                    };
                },

                playAnimations() {
                    this.animating = false;
                    const canvas = this.$refs.previewCanvas;
                    const stage = canvas.querySelector('div');
                    if (!stage) return;

                    const layerEls = stage.querySelectorAll(':scope > div');
                    layerEls.forEach(el => {
                        el.style.opacity = '0';
                        el.classList.remove('banner-anim', 'banner-anim-fade-in', 'banner-anim-fade-up', 'banner-anim-fade-down', 'banner-anim-fade-left', 'banner-anim-fade-right', 'banner-anim-zoom-in', 'banner-anim-zoom-out');
                    });

                    requestAnimationFrame(() => {
                        this.animating = true;
                        this.$nextTick(() => {
                            layerEls.forEach((el, i) => {
                                const layer = this.layers[i];
                                if (!layer || !layer.animation || layer.animation === 'none') {
                                    el.style.opacity = String((layer?.opacity ?? 100) / 100);
                                    return;
                                }
                                el.style.opacity = '';
                                el.style.animationDelay = (layer.delay || 0) + 'ms';
                                el.style.animationDuration = (layer.duration || 800) + 'ms';
                                el.style.setProperty('--anim-dist', (layer.distance || 30) + 'px');
                                el.classList.add('banner-anim', 'banner-anim-' + layer.animation);
                            });
                        });
                    });
                },

                async openLayerImageBrowser() {
                    this.layerBrowserOpen = true;
                    const startPath = this.lastBrowsedLayerPath ?? 'Banners';
                    await this.browseLayerDir(startPath);
                },

                get layerBreadcrumbs() {
                    if (!this.layerBrowserPath) return [];
                    const parts = this.layerBrowserPath.split('/');
                    return parts.map((part, i) => ({ name: part, path: parts.slice(0, i + 1).join('/') }));
                },

                get filteredLayerBrowserFiles() {
                    let files = [...this.layerBrowserFiles];
                    if (this.layerBrowserSearch) {
                        const q = this.layerBrowserSearch.toLowerCase();
                        files = files.filter(f => f.name.toLowerCase().includes(q));
                    }
                    return files.sort((a, b) => b.modified - a.modified);
                },

                async browseLayerDir(path) {
                    this.layerBrowserLoading = true;
                    const params = new URLSearchParams();
                    if (path) params.set('path', path);

                    const res = await fetch(`{{ route('admin.products.images.browse') }}?${params}`);
                    const data = await res.json();
                    if (data.ok) {
                        this.layerBrowserPath = data.path;
                        this.layerBrowserParent = data.parent ?? '';
                        this.layerBrowserFolders = data.folders;
                        this.layerBrowserFiles = data.files;
                        this.lastBrowsedLayerPath = data.path;
                    }
                    this.layerBrowserLoading = false;
                },

                selectLayerImage(file) {
                    if (this.currentLayer) {
                        this.currentLayer.src = file.path;
                    }
                    this.layerBrowserOpen = false;
                },

                getLayerStyle(layer) {
                    let style = `left: ${layer.x || '0'}; top: ${layer.y || '0'};`;
                    style += `opacity: ${(layer.opacity ?? 100) / 100};`;

                    if (layer.type === 'text') {
                        style += `font-size: ${layer.fontSize || 24}px;`;
                        style += `font-weight: ${layer.fontWeight || 400};`;
                        style += `color: ${layer.color || '#ffffff'};`;
                        style += `letter-spacing: ${layer.letterSpacing || 0}px;`;
                        style += `text-transform: ${layer.textTransform || 'none'};`;
                    }

                    const x = String(layer.x || '0');
                    const y = String(layer.y || '0');
                    style = style.replace(`left: ${x}`, `left: ${x.match(/[%px]/) ? x : x + 'px'}`);
                    style = style.replace(`top: ${y}`, `top: ${y.match(/[%px]/) ? y : y + 'px'}`);

                    return style;
                },
            };
        }
        </script>
        @endpush
    </x-admin.page>
</x-admin.shell>
