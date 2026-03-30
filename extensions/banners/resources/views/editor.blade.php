<x-admin-layout>
    <x-slot name="header">{{ $banner ? 'Edit Slide: ' . ($banner->name ?: '#'.$banner->id) : 'New Slide' }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.banners.slides', $group) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
            Back to Slides
        </a>
    </x-slot>

    <form method="POST"
          action="{{ $banner ? route('admin.banners.slides.update', [$group, $banner]) : route('admin.banners.slides.store', $group) }}"
          x-data="bannerEditor()"
          class="space-y-5">
        @csrf
        @if ($banner) @method('PUT') @endif
        <input type="hidden" name="layers" :value="JSON.stringify(layers)">
        <input type="hidden" name="_selected_layer" :value="selectedLayer">

        {{-- ============ FULL-WIDTH PREVIEW ============ --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden transition-all duration-300 isolate"
             :style="isFullWidth ? 'margin-left: -2rem; margin-right: -2rem; border-radius: 0; border-left: 0; border-right: 0;' : ''">
            <div class="px-4 py-2 border-b border-gray-200 bg-gray-50/80 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Preview</h3>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                          :class="isFullWidth ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'"
                          x-text="isFullWidth ? 'Full Width' : 'Contained'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click="playAnimations()"
                        class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors"
                        title="Preview animations">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        Play
                    </button>
                    <span class="text-[11px] text-gray-400 font-mono" x-text="previewWidth + '×' + height + 'px'"></span>
                </div>
            </div>
            <div class="bg-gray-800/5 flex justify-center"
                 style="background-image: linear-gradient(45deg, #f0f0f0 25%, transparent 25%), linear-gradient(-45deg, #f0f0f0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f0f0f0 75%), linear-gradient(-45deg, transparent 75%, #f0f0f0 75%); background-size: 16px 16px; background-position: 0 0, 0 8px, 8px -8px, -8px 0px;">
                <div class="relative overflow-hidden w-full" x-ref="previewCanvas"
                     @mousemove.prevent="onDrag($event)"
                     @mouseup.prevent="stopDrag()"
                     @mouseleave="stopDrag()"
                     :style="'height: ' + height + 'px; background-color: ' + bgColor + '; background-size: ' + bgSize + '; background-position: center; background-repeat: no-repeat;' + (bgImageUrl ? 'background-image: url(' + bgImageUrl + ')' : '')">
                    {{-- Centered stage container for layers (matches 1280px storefront stage) --}}
                    <div style="position: absolute; inset: 0; max-width: 1280px; margin: 0 auto; left: 0; right: 0;">
                    <template x-for="(layer, i) in layers" :key="i">
                        <div :style="getLayerStyle(layer) + '; pointer-events: auto; z-index: ' + (selectedLayer === i ? 999 : 10 + i) + (animating && layer.delay ? '; animation-delay: ' + layer.delay + 'ms' : '')"
                             :class="[
                                selectedLayer === i ? 'ring-2 ring-indigo-500 ring-offset-1' : '',
                                animating && layer.animation && layer.animation !== 'none' ? 'banner-anim banner-anim-' + layer.animation : ''
                             ]"
                             @mousedown.prevent="startDrag($event, i)"
                             @click="selectedLayer = i"
                             class="absolute cursor-move select-none">

                            {{-- Text layer --}}
                            <div x-show="layer.type === 'text'" x-text="layer.content || 'Text'" style="white-space: pre-line; pointer-events: none;"></div>

                            {{-- Image layer --}}
                            <img x-show="layer.type === 'image'"
                                 :src="layer.src ? '/storage/' + layer.src : 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/></svg>'"
                                 :style="'width: ' + (layer.width || 200) + 'px; height: ' + (layer.height || 'auto')"
                                 class="block max-w-none pointer-events-none" draggable="false">

                            {{-- Button layer --}}
                            <span x-show="layer.type === 'button'"
                                  :style="'background: ' + (layer.bgColor || '#ffffff') + '; color: ' + (layer.color || '#000000') + '; padding: ' + (layer.paddingY || 12) + 'px ' + (layer.paddingX || 32) + 'px; border-radius: ' + (layer.borderRadius || 0) + 'px; font-size: ' + (layer.fontSize || 14) + 'px; font-weight: ' + (layer.fontWeight || 600) + '; letter-spacing: ' + (layer.letterSpacing || 1) + 'px; pointer-events: none;'"
                                  x-text="layer.content || 'BUTTON'"></span>

                            {{-- Resize handle --}}
                            <div x-show="selectedLayer === i"
                                 @mousedown.stop.prevent="startResize($event, i)"
                                 class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-indigo-500 border border-white rounded-sm cursor-se-resize shadow-sm"
                                 style="pointer-events: auto; z-index: 50;"></div>
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-4">Background</h3>
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
                            <label class="block text-xs font-medium text-gray-600 mb-1">BG Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="bg_color" x-model="bgColor" class="w-8 h-8 rounded border-gray-300 cursor-pointer p-0.5">
                                <span class="text-[11px] text-gray-400 font-mono" x-text="bgColor"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">BG Size</label>
                            <select name="bg_size" x-model="bgSize" class="w-full rounded-lg border-gray-300 text-sm py-1.5">
                                <option value="cover">Cover</option>
                                <option value="contain">Contain</option>
                                <option value="auto">Auto</option>
                                <option value="100% 100%">Stretch</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Height</label>
                            <div class="flex items-center gap-1">
                                <span class="text-sm text-gray-700" x-text="height + 'px'"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Mobile H.</label>
                            <div class="flex items-center gap-1">
                                <span class="text-sm text-gray-700" x-text="heightMobile + 'px'"></span>
                            </div>
                            <a href="{{ route('admin.banners.edit', $group) }}" class="text-[10px] text-indigo-500 hover:text-indigo-700">Change in group settings</a>
                        </div>
                    </div>
                </div>

                {{-- Slide Settings --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-3">Slide Settings</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                <input type="text" name="name" value="{{ $banner?->name }}" class="w-full rounded-lg border-gray-300 text-sm py-1.5" placeholder="e.g. Summer Sale">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Link URL</label>
                                <input type="text" name="link" value="{{ $banner?->link }}" class="w-full rounded-lg border-gray-300 text-sm py-1.5" placeholder="/category/...">
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                    <input type="hidden" name="link_new_tab" value="0">
                                    <input type="checkbox" name="link_new_tab" value="1" {{ ($banner?->link_new_tab) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                    New tab
                                </label>
                                <span class="text-xs text-gray-400" x-text="isFullWidth ? 'Full width' : 'Contained'"></span>
                                <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" {{ ($banner?->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                    Active
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-[10px] text-gray-400 uppercase tracking-wide">Sort</label>
                                <input type="number" name="sort_order" value="{{ $banner?->sort_order ?? 0 }}" min="0" class="w-14 rounded-md border-gray-300 text-sm py-1 text-center">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Visible from</label>
                                <input type="date" name="starts_at" value="{{ $banner?->starts_at?->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm py-1.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Visible until</label>
                                <input type="date" name="ends_at" value="{{ $banner?->ends_at?->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm py-1.5">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ RIGHT COLUMN: Layers + Properties ============ --}}
            <div class="space-y-5">

                {{-- Layers Panel --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-4 py-2.5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                        <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Layers</h3>
                        <div class="flex gap-1">
                            <button type="button" @click="addLayer('text')" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] bg-blue-50 hover:bg-blue-100 rounded text-blue-700 font-medium transition-colors" title="Add text layer">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 6v2m16-2v2M7 6v12m0 0h2m-2 0H5m12-12v12m0 0h2m-2 0h-2"/></svg>
                                Text
                            </button>
                            <button type="button" @click="addLayer('image')" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] bg-emerald-50 hover:bg-emerald-100 rounded text-emerald-700 font-medium transition-colors" title="Add image layer">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Image
                            </button>
                            <button type="button" @click="addLayer('button')" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] bg-amber-50 hover:bg-amber-100 rounded text-amber-700 font-medium transition-colors" title="Add button layer">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg>
                                Button
                            </button>
                        </div>
                    </div>

                    {{-- Layer List --}}
                    <div class="divide-y divide-gray-100 max-h-52 overflow-y-auto">
                        <template x-if="layers.length === 0">
                            <div class="px-5 py-8 text-center">
                                <svg class="w-8 h-8 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0L21.75 16.5 12 21.75 2.25 16.5l4.179-2.25m0 0l5.571 3 5.571-3"/></svg>
                                <p class="text-xs text-gray-400">No layers yet</p>
                                <p class="text-[10px] text-gray-300 mt-0.5">Add text, image, or button above</p>
                            </div>
                        </template>
                        <template x-for="(layer, i) in layers" :key="i">
                            <div @click="selectedLayer = i"
                                 class="flex items-center gap-2 px-3 py-2 cursor-pointer text-sm transition-colors"
                                 :class="selectedLayer === i ? 'bg-indigo-50 border-l-2 border-indigo-500' : 'hover:bg-gray-50 border-l-2 border-transparent'">
                                <span class="text-[10px] uppercase font-semibold shrink-0 w-11 text-center rounded py-0.5"
                                      :class="layer.type === 'text' ? 'text-blue-700 bg-blue-50' : (layer.type === 'image' ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50')"
                                      x-text="layer.type"></span>
                                <span class="flex-1 truncate text-xs text-gray-500" x-text="layer.content || layer.src?.split('/').pop() || '(empty)'"></span>
                                <button type="button" @click.stop="removeLayer(i)" class="text-gray-300 hover:text-red-500 p-0.5 transition-colors" title="Remove layer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Selected Layer Properties --}}
                <div x-show="selectedLayer !== null && layers[selectedLayer]" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Layer Properties</h3>
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] uppercase font-semibold shrink-0 px-1.5 py-0.5 rounded"
                                      :class="currentLayer?.type === 'text' ? 'text-blue-700 bg-blue-50' : (currentLayer?.type === 'image' ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50')"
                                      x-text="currentLayer?.type"></span>
                                <span class="text-[10px] text-gray-300 font-mono" x-text="'#' + (selectedLayer + 1)"></span>
                            </div>
                        </div>

                        <template x-if="selectedLayer !== null && layers[selectedLayer]">
                            <div class="space-y-3">
                                {{-- Content (text/button) --}}
                                <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Content</label>
                                    <input type="text" x-model="currentLayer.content" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="Enter text...">
                                </div>

                                {{-- Image source --}}
                                <div x-show="currentLayer?.type === 'image'">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Image</label>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <button type="button" @click="openLayerImageBrowser()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-600 text-white text-[11px] font-medium rounded-md hover:bg-indigo-700 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                            Browse
                                        </button>
                                    </div>
                                    <template x-if="currentLayer?.src">
                                        <div class="flex items-center gap-2 p-1.5 bg-gray-50 rounded-md">
                                            <img :src="'/storage/' + currentLayer.src" class="h-10 w-10 rounded border border-gray-200 object-cover shrink-0">
                                            <span class="text-[10px] text-gray-400 font-mono truncate flex-1" x-text="currentLayer.src.split('/').pop()"></span>
                                            <button type="button" @click="currentLayer.src = ''" class="text-gray-300 hover:text-red-500 text-xs p-0.5 transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                {{-- Position --}}
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Position</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 font-mono">X</span>
                                            <input type="text" x-model="currentLayer.x" class="w-full pl-6 rounded-md border-gray-300 text-sm font-mono py-1.5" placeholder="0">
                                        </div>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 font-mono">Y</span>
                                            <input type="text" x-model="currentLayer.y" class="w-full pl-6 rounded-md border-gray-300 text-sm font-mono py-1.5" placeholder="0">
                                        </div>
                                    </div>
                                </div>

                                {{-- Size (image) --}}
                                <div x-show="currentLayer?.type === 'image'">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Size</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 font-mono">W</span>
                                            <input type="number" x-model.number="currentLayer.width" class="w-full pl-6 rounded-md border-gray-300 text-sm py-1.5" placeholder="200">
                                        </div>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 font-mono">H</span>
                                            <input type="number" x-model.number="currentLayer.height" class="w-full pl-6 rounded-md border-gray-300 text-sm py-1.5" placeholder="auto">
                                        </div>
                                    </div>
                                </div>

                                {{-- Typography (text/button) --}}
                                <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Typography</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">Aa</span>
                                            <input type="number" x-model.number="currentLayer.fontSize" class="w-full pl-7 rounded-md border-gray-300 text-sm py-1.5" placeholder="24">
                                        </div>
                                        <select x-model="currentLayer.fontWeight" class="rounded-md border-gray-300 text-sm py-1.5">
                                            <option value="300">Light</option>
                                            <option value="400">Regular</option>
                                            <option value="500">Medium</option>
                                            <option value="600">Semi</option>
                                            <option value="700">Bold</option>
                                            <option value="900">Black</option>
                                        </select>
                                        <input type="color" x-model="currentLayer.color" class="w-full h-[34px] rounded border-gray-300 cursor-pointer p-0.5">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-2">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-400">LS</span>
                                            <input type="number" x-model.number="currentLayer.letterSpacing" class="w-full pl-7 rounded-md border-gray-300 text-sm py-1.5" placeholder="0">
                                        </div>
                                        <select x-model="currentLayer.textTransform" class="rounded-md border-gray-300 text-sm py-1.5">
                                            <option value="none">Normal</option>
                                            <option value="uppercase">UPPERCASE</option>
                                            <option value="capitalize">Capitalize</option>
                                        </select>
                                    </div>
                                </div>

                                {{-- Button styling --}}
                                <div x-show="currentLayer?.type === 'button'">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Button Style</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <span class="text-[10px] text-gray-400 mb-0.5 block">BG</span>
                                            <input type="color" x-model="currentLayer.bgColor" class="w-full h-8 rounded border-gray-300 cursor-pointer p-0.5">
                                        </div>
                                        <div class="relative">
                                            <span class="text-[10px] text-gray-400 mb-0.5 block">Radius</span>
                                            <input type="number" x-model.number="currentLayer.borderRadius" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="0">
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-gray-400 mb-0.5 block">URL</span>
                                            <input type="text" x-model="currentLayer.url" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="/shop">
                                        </div>
                                    </div>
                                </div>

                                {{-- Animation --}}
                                <div class="pt-2 border-t border-gray-100 space-y-2">
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1.5">Animation</label>
                                    <select x-model="currentLayer.animation" class="w-full rounded-md border-gray-300 text-sm py-1.5">
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
                                            <label class="block text-[10px] text-gray-400 mb-0.5">Delay (ms)</label>
                                            <input type="number" x-model.number="currentLayer.delay" step="100" min="0" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="0">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 mb-0.5">Duration (ms)</label>
                                            <input type="number" x-model.number="currentLayer.duration" step="100" min="100" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="800">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-400 mb-0.5">Distance (px)</label>
                                            <input type="number" x-model.number="currentLayer.distance" step="10" min="0" class="w-full rounded-md border-gray-300 text-sm py-1.5" placeholder="30">
                                        </div>
                                    </div>
                                </div>

                                {{-- Opacity --}}
                                <div class="flex items-center gap-3">
                                    <label class="text-[11px] font-medium text-gray-500 shrink-0">Opacity</label>
                                    <input type="range" x-model.number="currentLayer.opacity" min="0" max="100" step="5" class="flex-1 h-1.5 accent-indigo-600">
                                    <span class="text-[11px] text-gray-400 font-mono w-8 text-right" x-text="(currentLayer.opacity ?? 100) + '%'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Save --}}
                <button type="submit" class="w-full px-4 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
                    {{ $banner ? 'Update Slide' : 'Create Slide' }}
                </button>
            </div>
        </div>

        {{-- Layer Image Browser Modal --}}
        <div x-show="layerBrowserOpen" x-transition.opacity.duration.200ms
             class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);"
             @keydown.escape.window="layerBrowserOpen = false">

            <div class="w-full max-w-5xl max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
                 @click.outside="layerBrowserOpen = false">

                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            <h3 class="text-sm font-semibold text-gray-900">Select Layer Image</h3>
                        </div>
                        <span class="text-gray-300">|</span>
                        <nav class="flex items-center gap-0.5 text-sm min-w-0 overflow-x-auto">
                            <button type="button" @click="browseLayerDir('')" class="text-indigo-600 hover:text-indigo-800 transition-colors shrink-0 font-medium">catalog</button>
                            <template x-for="(crumb, ci) in layerBreadcrumbs" :key="ci">
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <span class="text-gray-400">/</span>
                                    <button type="button" @click="browseLayerDir(crumb.path)" class="text-indigo-600 hover:text-indigo-800 transition-colors font-medium" x-text="crumb.name"></button>
                                </span>
                            </template>
                        </nav>
                    </div>
                    <button type="button" @click="layerBrowserOpen = false" class="text-gray-400 hover:text-gray-700 transition-colors p-1 rounded-lg hover:bg-gray-100 shrink-0 ml-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 bg-white">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="layerBrowserSearch" placeholder="Search..."
                            class="w-52 pl-9 pr-3 py-1.5 rounded-lg bg-white border-gray-300 text-gray-900 text-sm placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 bg-gray-50/50">
                    <div x-show="layerBrowserLoading" class="flex items-center justify-center py-16">
                        <svg class="w-6 h-6 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>

                    <div x-show="!layerBrowserLoading" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                        <template x-if="layerBrowserPath !== ''">
                            <button type="button" @click="browseLayerDir(layerBrowserParent)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                <span class="text-xs text-gray-500 mt-1.5 font-medium">..</span>
                            </button>
                        </template>

                        <template x-for="folder in layerBrowserFolders" :key="folder.path">
                            <button type="button" @click="browseLayerDir(folder.path)" class="flex flex-col items-center p-3 rounded-xl hover:bg-gray-100 transition-colors group">
                                <svg class="w-12 h-12 text-amber-500 group-hover:text-amber-600 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.097.903 2 2 2h16c1.097 0 2-.903 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/></svg>
                                <span class="text-xs text-gray-600 mt-1.5 truncate w-full text-center font-medium" x-text="folder.name"></span>
                            </button>
                        </template>

                        <template x-for="file in filteredLayerBrowserFiles" :key="file.path">
                            <button type="button" @click="selectLayerImage(file)" class="flex flex-col items-center p-2 rounded-xl hover:bg-gray-100 transition-colors w-full">
                                <div class="w-full aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                                    <img :src="file.url" :alt="file.name" class="w-full h-full object-cover" loading="lazy">
                                </div>
                                <span class="text-[11px] text-gray-500 mt-1.5 truncate w-full text-center leading-tight" x-text="file.name"></span>
                            </button>
                        </template>
                    </div>

                    <div x-show="!layerBrowserLoading && layerBrowserFolders.length === 0 && layerBrowserFiles.length === 0"
                         class="text-center py-16">
                        <p class="text-sm text-gray-500 font-medium">Empty folder</p>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('head')
    {{-- Banner animation keyframes for admin preview --}}
    <style>
        .banner-anim { animation-duration: 0.8s; animation-fill-mode: both; animation-timing-function: ease-out; }
        .banner-anim-fade-in { animation-name: bannerFadeIn; }
        .banner-anim-fade-up { animation-name: bannerFadeUp; }
        .banner-anim-fade-down { animation-name: bannerFadeDown; }
        .banner-anim-fade-left { animation-name: bannerFadeLeft; }
        .banner-anim-fade-right { animation-name: bannerFadeRight; }
        .banner-anim-zoom-in { animation-name: bannerZoomIn; }
        .banner-anim-zoom-out { animation-name: bannerZoomOut; }
        .banner-anim-slide-up { animation-name: bannerSlideUp; }
        .banner-anim-slide-down { animation-name: bannerSlideDown; }
        .banner-anim-slide-left { animation-name: bannerSlideLeft; }
        .banner-anim-slide-right { animation-name: bannerSlideRight; }
        .banner-anim-bounce-in { animation-name: bannerBounceIn; animation-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1); }
        .banner-anim-bounce-up { animation-name: bannerBounceUp; animation-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1); }
        .banner-anim-rotate-in { animation-name: bannerRotateIn; }
        .banner-anim-flip-x { animation-name: bannerFlipX; }
        .banner-anim-flip-y { animation-name: bannerFlipY; }

        @keyframes bannerFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes bannerFadeUp { from { opacity: 0; transform: translateY(var(--anim-dist, 30px)); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bannerFadeDown { from { opacity: 0; transform: translateY(calc(-1 * var(--anim-dist, 30px))); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bannerFadeLeft { from { opacity: 0; transform: translateX(var(--anim-dist, 30px)); } to { opacity: 1; transform: translateX(0); } }
        @keyframes bannerFadeRight { from { opacity: 0; transform: translateX(calc(-1 * var(--anim-dist, 30px))); } to { opacity: 1; transform: translateX(0); } }
        @keyframes bannerZoomIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        @keyframes bannerZoomOut { from { opacity: 0; transform: scale(1.2); } to { opacity: 1; transform: scale(1); } }
        @keyframes bannerSlideUp { from { transform: translateY(var(--anim-dist, 100px)); } to { transform: translateY(0); } }
        @keyframes bannerSlideDown { from { transform: translateY(calc(-1 * var(--anim-dist, 100px))); } to { transform: translateY(0); } }
        @keyframes bannerSlideLeft { from { transform: translateX(var(--anim-dist, 100px)); } to { transform: translateX(0); } }
        @keyframes bannerSlideRight { from { transform: translateX(calc(-1 * var(--anim-dist, 100px))); } to { transform: translateX(0); } }
        @keyframes bannerBounceIn { from { opacity: 0; transform: scale(0.3); } to { opacity: 1; transform: scale(1); } }
        @keyframes bannerBounceUp { from { opacity: 0; transform: translateY(var(--anim-dist, 60px)); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bannerRotateIn { from { opacity: 0; transform: rotate(-90deg) scale(0.5); } to { opacity: 1; transform: rotate(0) scale(1); } }
        @keyframes bannerFlipX { from { opacity: 0; transform: perspective(400px) rotateX(90deg); } to { opacity: 1; transform: perspective(400px) rotateX(0); } }
        @keyframes bannerFlipY { from { opacity: 0; transform: perspective(400px) rotateY(90deg); } to { opacity: 1; transform: perspective(400px) rotateY(0); } }
    </style>
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
</x-admin-layout>
