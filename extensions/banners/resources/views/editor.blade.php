<x-admin.shell :title="$banner ? 'Edit Slide: ' . ($banner->name ?: '#'.$banner->id) : 'New Slide'">
    @push('head')
        @vite('extensions/banners/resources/css/banners.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.banners.index') }}">Banner groups</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.banners.slides', $group) }}">{{ $group->name }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $banner ? ($banner->name ?: 'Slide #'.$banner->id) : 'New slide' }}</span>
            </div>
            <h1 class="v-head__title">{{ $banner ? ($banner->name ?: 'Slide #'.$banner->id) : 'New slide' }}</h1>
        </div>
    </div>

    <x-admin.form
        :action="$banner ? route('admin.banners.slides.update', [$group, $banner]) : route('admin.banners.slides.store', $group)"
        :method="$banner ? 'PUT' : 'POST'" class="v-settle">
        {{-- The whole canvas lives in one editor scope nested inside the
             doctrine form. Structural edits that only move Alpine state
             (drag, layer add/remove, image picks) call touch(), because
             the bound hidden inputs fire no events of their own. --}}
        <div x-data="bannerEditor()"
             @mousemove.window="onDrag($event)" @mouseup.window="stopDrag()">
            <input type="hidden" name="layers" :value="JSON.stringify(layers)">
            <input type="hidden" name="_selected_layer" :value="selectedLayer">
            <input type="hidden" name="bg_image" :value="bgImage">
            <input type="hidden" name="bg_image_mobile" :value="bgImageMobile">

            {{-- ============ PREVIEW ============ --}}
            <x-admin.panel title="Preview" flush>
                <x-slot:acts>
                    <span class="v-cell-quiet v-mono" x-text="previewWidth + '×' + height + 'px'"></span>
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="playAnimations()">
                        <x-admin.icon name="play" class="v-ico-sm" /> Play
                    </button>
                </x-slot:acts>
                <div class="banner-stage">
                    <div class="banner-canvas" x-ref="previewCanvas"
                         :style="'height: ' + height + 'px; background-color: ' + bgColor + '; background-size: ' + bgSize + '; background-position: center; background-repeat: no-repeat;' + (bgImage ? 'background-image: url(/storage/' + bgImage + ')' : '')">
                        {{-- Centered stage container for layers (matches the storefront stage) --}}
                        <div class="banner-frame">
                        <template x-for="(layer, i) in layers" :key="i">
                            <div :style="getLayerStyle(layer) + '; pointer-events: auto; z-index: ' + (selectedLayer === i ? 999 : 10 + i) + (animating && layer.delay ? '; animation-delay: ' + layer.delay + 'ms' : '')"
                                 :class="[
                                    selectedLayer === i ? 'banner-sel' : '',
                                    animating && layer.animation && layer.animation !== 'none' ? 'banner-anim banner-anim-' + layer.animation : ''
                                 ]"
                                 @mousedown.prevent="startDrag($event, i)"
                                 @click="selectedLayer = i"
                                 class="banner-layer">

                                {{-- Text layer --}}
                                <div x-show="layer.type === 'text'" x-text="layer.content || 'Text'" class="banner-layer-text"></div>

                                {{-- Image layer --}}
                                <img x-show="layer.type === 'image'" alt="" aria-hidden="true"
                                     :src="layer.src ? '/storage/' + layer.src : 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><rect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/></svg>'"
                                     :style="'width: ' + (layer.width || 200) + 'px; height: ' + (layer.height || 'auto')"
                                     class="banner-layer__img" draggable="false">

                                {{-- Button layer --}}
                                <span x-show="layer.type === 'button'"
                                      :style="'background: ' + (layer.bgColor || '#ffffff') + '; color: ' + (layer.color || '#000000') + '; padding: ' + (layer.paddingY || 12) + 'px ' + (layer.paddingX || 32) + 'px; border-radius: ' + (layer.borderRadius || 0) + 'px; font-size: ' + (layer.fontSize || 14) + 'px; font-weight: ' + (layer.fontWeight || 600) + '; letter-spacing: ' + (layer.letterSpacing || 1) + 'px; pointer-events: none;'"
                                      class="banner-layer-button" x-text="layer.content || 'BUTTON'"></span>

                                {{-- Resize handle --}}
                                <div x-show="selectedLayer === i"
                                     @mousedown.stop.prevent="startResize($event, i)"
                                     class="banner-handle"></div>
                            </div>
                        </template>
                        </div>
                    </div>
                </div>
            </x-admin.panel>

            {{-- ============ TWO COLUMNS: settings left, layers right ============ --}}
            <div class="v-gap v-editcols">
                <div class="v-settle">
                    {{-- Background --}}
                    <x-admin.panel title="Background">
                        <div class="v-grid v-grid--2">
                            <x-admin.field name="bg_image" label="Desktop image" hint="1920x600 recommended.">
                                <span class="v-mediafield">
                                    <template x-if="bgImage">
                                        <img :src="'/storage/' + bgImage" alt="" class="v-thumb">
                                    </template>
                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openBrowser('bg')">Browse</button>
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="bgImage" @click="bgImage = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </x-admin.field>
                            <x-admin.field name="bg_image_mobile" label="Mobile image" hint="768x600 recommended. Blank falls back to the desktop image.">
                                <span class="v-mediafield">
                                    <template x-if="bgImageMobile">
                                        <img :src="'/storage/' + bgImageMobile" alt="" class="v-thumb">
                                    </template>
                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openBrowser('bg_mobile')">Browse</button>
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="bgImageMobile" @click="bgImageMobile = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-actrow">
                            <x-admin.field name="bg_color" label="Color">
                                <input type="color" name="bg_color" x-model="bgColor" class="v-swatch v-swatch--lg">
                            </x-admin.field>
                            <x-admin.field name="bg_size" label="Image fit">
                                <select name="bg_size" x-model="bgSize" class="v-control v-w-name">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="auto">Auto</option>
                                    <option value="100% 100%">Stretch</option>
                                </select>
                            </x-admin.field>
                            <x-admin.field name="heights" label="Heights">
                                <span class="v-cell-quiet v-mono">{{ $group->height }}px / {{ $group->height_mobile }}px mobile
                                    <a href="{{ route('admin.banners.edit', $group) }}" class="v-link">Group settings</a>
                                </span>
                            </x-admin.field>
                        </div>
                    </x-admin.panel>

                    {{-- Slide settings --}}
                    <x-admin.panel title="Slide">
                        <div class="v-grid v-grid--2">
                            <x-admin.field name="name" label="Name">
                                <input type="text" name="name" value="{{ old('name', $banner?->name) }}" placeholder="e.g. Summer Sale" class="v-control">
                            </x-admin.field>
                            <x-admin.field name="link" label="Link URL">
                                <input type="text" name="link" value="{{ old('link', $banner?->link) }}" placeholder="/category/…" class="v-control v-mono">
                            </x-admin.field>
                        </div>
                        <div class="v-grid v-grid--2 v-gap">
                            <x-admin.field name="starts_at" label="Visible from">
                                <input type="date" name="starts_at" value="{{ old('starts_at', $banner?->starts_at?->format('Y-m-d')) }}" class="v-control v-mono">
                            </x-admin.field>
                            <x-admin.field name="ends_at" label="Visible until">
                                <input type="date" name="ends_at" value="{{ old('ends_at', $banner?->ends_at?->format('Y-m-d')) }}" class="v-control v-mono">
                            </x-admin.field>
                        </div>
                        <div class="v-gap v-actrow">
                            <label class="v-toggle">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner?->is_active ?? true))>
                                <span class="v-toggle__track" aria-hidden="true"></span>
                                Active
                            </label>
                            <label class="v-check">
                                <input type="hidden" name="link_new_tab" value="0">
                                <input type="checkbox" name="link_new_tab" value="1" @checked(old('link_new_tab', $banner?->link_new_tab))>
                                Open link in a new tab
                            </label>
                            <x-admin.field name="sort_order" label="Sort">
                                <input type="number" name="sort_order" value="{{ old('sort_order', $banner?->sort_order ?? 0) }}" min="0" class="v-control v-mono v-w-sort">
                            </x-admin.field>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="v-settle">
                    {{-- Layers --}}
                    <x-admin.panel title="Layers" flush>
                        <x-slot:acts>
                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="addLayer('text')"><x-admin.icon name="text" class="v-ico-sm" /> Text</button>
                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="addLayer('image')"><x-admin.icon name="image" class="v-ico-sm" /> Image</button>
                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="addLayer('button')"><x-admin.icon name="link" class="v-ico-sm" /> Button</button>
                        </x-slot:acts>
                        <div class="v-layerlist">
                            <template x-if="layers.length === 0">
                                <div class="v-empty"><p class="v-empty__title">No layers yet</p></div>
                            </template>
                            <template x-for="(layer, i) in layers" :key="i">
                                <div @click="selectedLayer = i" class="v-layerrow" :class="selectedLayer === i && 'is-on'">
                                    <span class="v-badge v-badge--quiet" x-text="layer.type"></span>
                                    <span class="v-layerrow__name" x-text="layer.content || layer.src?.split('/').pop() || '(empty)'"></span>
                                    <button type="button" @click.stop="removeLayer(i)" class="v-row__x" :aria-label="'Remove layer ' + (i + 1)">
                                        <x-admin.icon name="x" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </x-admin.panel>

                    {{-- Selected layer properties --}}
                    <template x-if="selectedLayer !== null && layers[selectedLayer]">
                        <div>
                        <x-admin.panel title="Layer">
                            <x-slot:acts>
                                <span class="v-badge v-badge--quiet" x-text="currentLayer?.type"></span>
                                <span class="v-cell-quiet v-mono" x-text="'#' + (selectedLayer + 1)"></span>
                            </x-slot:acts>

                            <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                <x-admin.field name="layer_content" label="Content">
                                    <input type="text" x-model="currentLayer.content" aria-label="Layer content" placeholder="Enter text…" class="v-control">
                                </x-admin.field>
                            </div>

                            <div x-show="currentLayer?.type === 'image'">
                                <div class="v-label v-gap-sm">Image</div>
                                <span class="v-mediafield">
                                    <template x-if="currentLayer?.src">
                                        <img :src="'/storage/' + currentLayer.src" alt="" class="v-thumb">
                                    </template>
                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="openBrowser('layer')">Browse</button>
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="currentLayer?.src" @click="currentLayer.src = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </div>

                            <div class="v-gap">
                                <div class="v-label v-gap-sm">Position</div>
                                <div class="v-grid v-grid--2">
                                    <input type="text" x-model="currentLayer.x" aria-label="Layer X position" placeholder="X" class="v-control v-mono">
                                    <input type="text" x-model="currentLayer.y" aria-label="Layer Y position" placeholder="Y" class="v-control v-mono">
                                </div>
                            </div>

                            <div class="v-gap" x-show="currentLayer?.type === 'image'">
                                <div class="v-label v-gap-sm">Size (px)</div>
                                <div class="v-grid v-grid--2">
                                    <input type="number" x-model.number="currentLayer.width" aria-label="Layer width" placeholder="Width" class="v-control v-mono">
                                    <input type="number" x-model.number="currentLayer.height" aria-label="Layer height" placeholder="Height (auto)" class="v-control v-mono">
                                </div>
                            </div>

                            <div class="v-gap" x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                <div class="v-label v-gap-sm">Typography</div>
                                <div class="v-grid v-grid--3">
                                    <input type="number" x-model.number="currentLayer.fontSize" aria-label="Font size" placeholder="Size" class="v-control v-mono">
                                    <select x-model="currentLayer.fontWeight" aria-label="Font weight" class="v-control">
                                        <option value="300">Light</option>
                                        <option value="400">Regular</option>
                                        <option value="500">Medium</option>
                                        <option value="600">Semi</option>
                                        <option value="700">Bold</option>
                                        <option value="900">Black</option>
                                    </select>
                                    <input type="color" x-model="currentLayer.color" aria-label="Text color" class="v-swatch">
                                </div>
                                <div class="v-grid v-grid--2 v-gap-sm">
                                    <input type="number" x-model.number="currentLayer.letterSpacing" aria-label="Letter spacing" placeholder="Tracking" class="v-control v-mono">
                                    <select x-model="currentLayer.textTransform" aria-label="Text case" class="v-control">
                                        <option value="none">Normal</option>
                                        <option value="uppercase">UPPERCASE</option>
                                        <option value="capitalize">Capitalize</option>
                                    </select>
                                </div>
                            </div>

                            <div class="v-gap" x-show="currentLayer?.type === 'button'">
                                <div class="v-label v-gap-sm">Button</div>
                                <div class="v-grid v-grid--3">
                                    <input type="color" x-model="currentLayer.bgColor" aria-label="Button background" class="v-swatch">
                                    <input type="number" x-model.number="currentLayer.borderRadius" aria-label="Corner radius" placeholder="Radius" class="v-control v-mono">
                                    <input type="text" x-model="currentLayer.url" aria-label="Button URL" placeholder="/shop" class="v-control v-mono">
                                </div>
                            </div>

                            <div class="v-gap">
                                <div class="v-label v-gap-sm">Animation</div>
                                <select x-model="currentLayer.animation" aria-label="Animation" class="v-control">
                                    <optgroup label="None"><option value="none">None</option></optgroup>
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
                                <div class="v-grid v-grid--3 v-gap-sm">
                                    <input type="number" x-model.number="currentLayer.delay" step="100" min="0" aria-label="Delay in ms" placeholder="Delay ms" class="v-control v-mono">
                                    <input type="number" x-model.number="currentLayer.duration" step="100" min="100" aria-label="Duration in ms" placeholder="Duration ms" class="v-control v-mono">
                                    <input type="number" x-model.number="currentLayer.distance" step="10" min="0" aria-label="Travel distance in px" placeholder="Distance px" class="v-control v-mono">
                                </div>
                            </div>

                            <div class="v-gap v-actrow">
                                <div class="v-label">Opacity</div>
                                <input type="range" x-model.number="currentLayer.opacity" min="0" max="100" step="5" aria-label="Layer opacity" class="v-range">
                                <span class="v-cell-quiet v-mono" x-text="(currentLayer.opacity ?? 100) + '%'"></span>
                            </div>
                        </x-admin.panel>
                        </div>
                    </template>
                </div>
            </div>

            {{-- The shared browser. Was hand-rolled, opened at 'Banners'
                 with a capital B which does not exist so every merchant met
                 "Empty folder" first, and carried a root crumb labelled
                 "catalog" that navigated to the storage root instead. --}}
            <x-admin.media-picker name="__banner_image" root="banners"
                                  mode="single" can="upload,url" bare
                                  @media:picked="selectImage($event.detail.paths[0] ?? '')"
                                  @media:uploaded="selectImage($event.detail.path)" />

        </div>
    </x-admin.form>

    @push('scripts')
    <script>
        function bannerEditor() {
            return {
                layers: @json($banner?->layers ?? []),
                selectedLayer: null,
                bgColor: '{{ $banner?->bg_color ?? '#000000' }}',
                bgSize: '{{ $banner?->bg_size ?? 'cover' }}',
                bgImage: @js($banner?->bg_image ?? ''),
                bgImageMobile: @js($banner?->bg_image_mobile ?? ''),
                height: {{ $group->height ?? 500 }},
                heightMobile: {{ $group->height_mobile ?? 400 }},
                previewWidth: 0,
                isFullWidth: {{ ($group->full_width ?? true) ? 'true' : 'false' }},

                dragging: null,
                resizing: null,
                moved: false,
                animating: false,

                // Which of the three image slots the shared panel is filling.
                // The panel keeps its own path, folders, files and search.
                browserTarget: 'layer',

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
                },

                get currentLayer() {
                    return this.selectedLayer !== null ? this.layers[this.selectedLayer] : null;
                },

                markDirty() {
                    // The bound hidden inputs update reactively without any
                    // input event, so tell the doctrine form directly.
                    this.$nextTick(() => this.$el.dispatchEvent(new Event('change', { bubbles: true })));
                },

                addLayer(type) {
                    const defaults = {
                        text: { type: 'text', content: 'New Text', x: '50px', y: '50px', fontSize: 32, fontWeight: '700', color: '#ffffff', letterSpacing: 1, textTransform: 'none', animation: 'fade-up', delay: 0, opacity: 100 },
                        image: { type: 'image', src: '', x: '0', y: '0', width: 200, height: null, animation: 'fade-in', delay: 0, opacity: 100 },
                        button: { type: 'button', content: 'SHOP NOW', url: '#', x: '50px', y: '200px', fontSize: 14, fontWeight: '600', color: '#000000', bgColor: '#ffffff', borderRadius: 0, paddingX: 32, paddingY: 12, letterSpacing: 2, textTransform: 'uppercase', animation: 'fade-up', delay: 200, opacity: 100 },
                    };
                    this.layers.push({ ...defaults[type] });
                    this.selectedLayer = this.layers.length - 1;
                    this.markDirty();
                },

                removeLayer(i) {
                    this.layers.splice(i, 1);
                    if (this.selectedLayer >= this.layers.length) {
                        this.selectedLayer = this.layers.length > 0 ? this.layers.length - 1 : null;
                    }
                    this.markDirty();
                },

                startDrag(event, index) {
                    this.selectedLayer = index;
                    this.moved = false;
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
                        if (dx || dy) this.moved = true;
                        const layer = this.layers[this.dragging.layerIndex];
                        layer.x = (this.dragging.origX + dx) + 'px';
                        layer.y = (this.dragging.origY + dy) + 'px';
                    } else if (this.resizing) {
                        const dx = event.clientX - this.resizing.startX;
                        const dy = event.clientY - this.resizing.startY;
                        if (dx || dy) this.moved = true;
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
                    const acted = (this.dragging || this.resizing) && this.moved;
                    this.dragging = null;
                    this.resizing = null;
                    if (acted) this.markDirty();
                },

                startResize(event, index) {
                    this.selectedLayer = index;
                    this.moved = false;
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

                openBrowser(target) {
                    this.browserTarget = target;
                    // By selector: the picker owns its own Alpine scope, so a
                    // ref on it does not resolve into this one.
                    this.$root.querySelector('[data-media-picker="__banner_image"]')
                        ?.dispatchEvent(new CustomEvent('media:open', {
                            detail: { from: event?.currentTarget },
                        }));
                },

                // The browser's state and its four methods left with the
                // markup: browseDir, breadcrumbs, filteredBrowserFiles and
                // uploadToBrowser are the shared component's job now.


                selectImage(path) {
                    if (!path) return;

                    if (this.browserTarget === 'bg') {
                        this.bgImage = path;
                    } else if (this.browserTarget === 'bg_mobile') {
                        this.bgImageMobile = path;
                    } else if (this.currentLayer) {
                        this.currentLayer.src = path;
                    }

                    this.markDirty();
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
</x-admin.shell>
