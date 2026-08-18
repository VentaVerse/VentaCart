<x-admin3.shell :title="$banner ? 'Edit Slide: ' . ($banner->name ?: '#'.$banner->id) : 'New Slide'">
    @push('head')
        @vite('extensions/banners/resources/css/banners.css')
    @endpush

    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.banners.index') }}">Banner groups</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.banners.slides', $group) }}">{{ $group->name }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $banner ? ($banner->name ?: 'Slide #'.$banner->id) : 'New slide' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $banner ? ($banner->name ?: 'Slide #'.$banner->id) : 'New slide' }}</h1>
        </div>
    </div>

    <x-admin3.form
        :action="$banner ? route('admin.banners.slides.update', [$group, $banner]) : route('admin.banners.slides.store', $group)"
        :method="$banner ? 'PUT' : 'POST'" class="v3-settle">
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
            <x-admin3.panel title="Preview" flush>
                <x-slot:acts>
                    <span class="v3-cell-quiet v3-mono" x-text="previewWidth + '×' + height + 'px'"></span>
                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="playAnimations()">
                        <x-admin3.icon name="play" class="v3-ico-sm" /> Play
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
            </x-admin3.panel>

            {{-- ============ TWO COLUMNS: settings left, layers right ============ --}}
            <div class="v3-gap v3-editcols">
                <div class="v3-settle">
                    {{-- Background --}}
                    <x-admin3.panel title="Background">
                        <div class="v3-grid v3-grid--2">
                            <x-admin3.field name="bg_image" label="Desktop image" hint="1920x600 recommended.">
                                <span class="v3-mediafield">
                                    <template x-if="bgImage">
                                        <img :src="'/storage/' + bgImage" alt="" class="v3-thumb">
                                    </template>
                                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="openBrowser('bg')">Browse</button>
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" x-show="bgImage" @click="bgImage = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </x-admin3.field>
                            <x-admin3.field name="bg_image_mobile" label="Mobile image" hint="768x600 recommended. Blank falls back to the desktop image.">
                                <span class="v3-mediafield">
                                    <template x-if="bgImageMobile">
                                        <img :src="'/storage/' + bgImageMobile" alt="" class="v3-thumb">
                                    </template>
                                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="openBrowser('bg_mobile')">Browse</button>
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" x-show="bgImageMobile" @click="bgImageMobile = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap v3-actrow">
                            <x-admin3.field name="bg_color" label="Color">
                                <input type="color" name="bg_color" x-model="bgColor" class="v3-swatch v3-swatch--lg">
                            </x-admin3.field>
                            <x-admin3.field name="bg_size" label="Image fit">
                                <select name="bg_size" x-model="bgSize" class="v3-control v3-w-name">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="auto">Auto</option>
                                    <option value="100% 100%">Stretch</option>
                                </select>
                            </x-admin3.field>
                            <x-admin3.field name="heights" label="Heights">
                                <span class="v3-cell-quiet v3-mono">{{ $group->height }}px / {{ $group->height_mobile }}px mobile
                                    <a href="{{ route('admin.banners.edit', $group) }}" class="v3-link">Group settings</a>
                                </span>
                            </x-admin3.field>
                        </div>
                    </x-admin3.panel>

                    {{-- Slide settings --}}
                    <x-admin3.panel title="Slide">
                        <div class="v3-grid v3-grid--2">
                            <x-admin3.field name="name" label="Name">
                                <input type="text" name="name" value="{{ old('name', $banner?->name) }}" placeholder="e.g. Summer Sale" class="v3-control">
                            </x-admin3.field>
                            <x-admin3.field name="link" label="Link URL">
                                <input type="text" name="link" value="{{ old('link', $banner?->link) }}" placeholder="/category/…" class="v3-control v3-mono">
                            </x-admin3.field>
                        </div>
                        <div class="v3-grid v3-grid--2 v3-gap">
                            <x-admin3.field name="starts_at" label="Visible from">
                                <input type="date" name="starts_at" value="{{ old('starts_at', $banner?->starts_at?->format('Y-m-d')) }}" class="v3-control v3-mono">
                            </x-admin3.field>
                            <x-admin3.field name="ends_at" label="Visible until">
                                <input type="date" name="ends_at" value="{{ old('ends_at', $banner?->ends_at?->format('Y-m-d')) }}" class="v3-control v3-mono">
                            </x-admin3.field>
                        </div>
                        <div class="v3-gap v3-actrow">
                            <label class="v3-toggle">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner?->is_active ?? true))>
                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                Active
                            </label>
                            <label class="v3-check">
                                <input type="hidden" name="link_new_tab" value="0">
                                <input type="checkbox" name="link_new_tab" value="1" @checked(old('link_new_tab', $banner?->link_new_tab))>
                                Open link in a new tab
                            </label>
                            <x-admin3.field name="sort_order" label="Sort">
                                <input type="number" name="sort_order" value="{{ old('sort_order', $banner?->sort_order ?? 0) }}" min="0" class="v3-control v3-mono v3-w-sort">
                            </x-admin3.field>
                        </div>
                    </x-admin3.panel>
                </div>

                <div class="v3-settle">
                    {{-- Layers --}}
                    <x-admin3.panel title="Layers" flush>
                        <x-slot:acts>
                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="addLayer('text')"><x-admin3.icon name="text" class="v3-ico-sm" /> Text</button>
                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="addLayer('image')"><x-admin3.icon name="image" class="v3-ico-sm" /> Image</button>
                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="addLayer('button')"><x-admin3.icon name="link" class="v3-ico-sm" /> Button</button>
                        </x-slot:acts>
                        <div class="v3-layerlist">
                            <template x-if="layers.length === 0">
                                <div class="v3-empty"><p class="v3-empty__title">No layers yet</p></div>
                            </template>
                            <template x-for="(layer, i) in layers" :key="i">
                                <div @click="selectedLayer = i" class="v3-layerrow" :class="selectedLayer === i && 'is-on'">
                                    <span class="v3-badge v3-badge--quiet" x-text="layer.type"></span>
                                    <span class="v3-layerrow__name" x-text="layer.content || layer.src?.split('/').pop() || '(empty)'"></span>
                                    <button type="button" @click.stop="removeLayer(i)" class="v3-row__x" :aria-label="'Remove layer ' + (i + 1)">
                                        <x-admin3.icon name="x" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </x-admin3.panel>

                    {{-- Selected layer properties --}}
                    <template x-if="selectedLayer !== null && layers[selectedLayer]">
                        <div>
                        <x-admin3.panel title="Layer">
                            <x-slot:acts>
                                <span class="v3-badge v3-badge--quiet" x-text="currentLayer?.type"></span>
                                <span class="v3-cell-quiet v3-mono" x-text="'#' + (selectedLayer + 1)"></span>
                            </x-slot:acts>

                            <div x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                <x-admin3.field name="layer_content" label="Content">
                                    <input type="text" x-model="currentLayer.content" aria-label="Layer content" placeholder="Enter text…" class="v3-control">
                                </x-admin3.field>
                            </div>

                            <div x-show="currentLayer?.type === 'image'">
                                <div class="v3-label v3-gap-sm">Image</div>
                                <span class="v3-mediafield">
                                    <template x-if="currentLayer?.src">
                                        <img :src="'/storage/' + currentLayer.src" alt="" class="v3-thumb">
                                    </template>
                                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="openBrowser('layer')">Browse</button>
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" x-show="currentLayer?.src" @click="currentLayer.src = ''; $nextTick(() => touch())">Clear</button>
                                </span>
                            </div>

                            <div class="v3-gap">
                                <div class="v3-label v3-gap-sm">Position</div>
                                <div class="v3-grid v3-grid--2">
                                    <input type="text" x-model="currentLayer.x" aria-label="Layer X position" placeholder="X" class="v3-control v3-mono">
                                    <input type="text" x-model="currentLayer.y" aria-label="Layer Y position" placeholder="Y" class="v3-control v3-mono">
                                </div>
                            </div>

                            <div class="v3-gap" x-show="currentLayer?.type === 'image'">
                                <div class="v3-label v3-gap-sm">Size (px)</div>
                                <div class="v3-grid v3-grid--2">
                                    <input type="number" x-model.number="currentLayer.width" aria-label="Layer width" placeholder="Width" class="v3-control v3-mono">
                                    <input type="number" x-model.number="currentLayer.height" aria-label="Layer height" placeholder="Height (auto)" class="v3-control v3-mono">
                                </div>
                            </div>

                            <div class="v3-gap" x-show="currentLayer?.type === 'text' || currentLayer?.type === 'button'">
                                <div class="v3-label v3-gap-sm">Typography</div>
                                <div class="v3-grid v3-grid--3">
                                    <input type="number" x-model.number="currentLayer.fontSize" aria-label="Font size" placeholder="Size" class="v3-control v3-mono">
                                    <select x-model="currentLayer.fontWeight" aria-label="Font weight" class="v3-control">
                                        <option value="300">Light</option>
                                        <option value="400">Regular</option>
                                        <option value="500">Medium</option>
                                        <option value="600">Semi</option>
                                        <option value="700">Bold</option>
                                        <option value="900">Black</option>
                                    </select>
                                    <input type="color" x-model="currentLayer.color" aria-label="Text color" class="v3-swatch">
                                </div>
                                <div class="v3-grid v3-grid--2 v3-gap-sm">
                                    <input type="number" x-model.number="currentLayer.letterSpacing" aria-label="Letter spacing" placeholder="Tracking" class="v3-control v3-mono">
                                    <select x-model="currentLayer.textTransform" aria-label="Text case" class="v3-control">
                                        <option value="none">Normal</option>
                                        <option value="uppercase">UPPERCASE</option>
                                        <option value="capitalize">Capitalize</option>
                                    </select>
                                </div>
                            </div>

                            <div class="v3-gap" x-show="currentLayer?.type === 'button'">
                                <div class="v3-label v3-gap-sm">Button</div>
                                <div class="v3-grid v3-grid--3">
                                    <input type="color" x-model="currentLayer.bgColor" aria-label="Button background" class="v3-swatch">
                                    <input type="number" x-model.number="currentLayer.borderRadius" aria-label="Corner radius" placeholder="Radius" class="v3-control v3-mono">
                                    <input type="text" x-model="currentLayer.url" aria-label="Button URL" placeholder="/shop" class="v3-control v3-mono">
                                </div>
                            </div>

                            <div class="v3-gap">
                                <div class="v3-label v3-gap-sm">Animation</div>
                                <select x-model="currentLayer.animation" aria-label="Animation" class="v3-control">
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
                                <div class="v3-grid v3-grid--3 v3-gap-sm">
                                    <input type="number" x-model.number="currentLayer.delay" step="100" min="0" aria-label="Delay in ms" placeholder="Delay ms" class="v3-control v3-mono">
                                    <input type="number" x-model.number="currentLayer.duration" step="100" min="100" aria-label="Duration in ms" placeholder="Duration ms" class="v3-control v3-mono">
                                    <input type="number" x-model.number="currentLayer.distance" step="10" min="0" aria-label="Travel distance in px" placeholder="Distance px" class="v3-control v3-mono">
                                </div>
                            </div>

                            <div class="v3-gap v3-actrow">
                                <div class="v3-label">Opacity</div>
                                <input type="range" x-model.number="currentLayer.opacity" min="0" max="100" step="5" aria-label="Layer opacity" class="v3-range">
                                <span class="v3-cell-quiet v3-mono" x-text="(currentLayer.opacity ?? 100) + '%'"></span>
                            </div>
                        </x-admin3.panel>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ============ IMAGE BROWSER ============ --}}
            <div x-show="browserOpen" x-transition.opacity x-cloak class="v3-overlay"
                 @keydown.escape.window="browserOpen = false" @click.self="browserOpen = false">
                <div class="v3-modal v3-modal--media" role="dialog" aria-modal="true" aria-label="Image browser">
                    <div class="v3-namerow">
                        <strong>Pick an image</strong>
                        <nav class="v3-crumbrow" aria-label="Folder path">
                            <button type="button" @click="browseDir('')" class="v3-link">catalog</button>
                            <template x-for="(crumb, ci) in breadcrumbs" :key="ci">
                                <span>
                                    <span class="v3-cell-quiet">/</span>
                                    <button type="button" @click="browseDir(crumb.path)" class="v3-link" x-text="crumb.name"></button>
                                </span>
                            </template>
                        </nav>
                        <span class="v3-namerow__end">
                            <input type="text" x-model="browserSearch" aria-label="Search images" placeholder="Search…" class="v3-control v3-control--sm v3-w-name">
                            <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="$refs.mediaUpload.click()">Upload</button>
                            <input type="file" x-ref="mediaUpload" accept="image/*" hidden aria-label="Upload image" @change="uploadToBrowser($event)">
                            <button type="button" @click="browserOpen = false" class="v3-row__x" aria-label="Close image browser"><x-admin3.icon name="x" /></button>
                        </span>
                    </div>

                    <div class="v3-mediagrid-wrap">
                        <div x-show="browserLoading" class="v3-empty"><p class="v3-empty__title">Loading…</p></div>
                        <div x-show="!browserLoading" class="banner-picker-grid">
                            <template x-if="browserPath !== ''">
                                <button type="button" @click="browseDir(browserParent)" class="v3-mediatile">
                                    <x-admin3.icon name="folder" class="v3-mediatile__folder" />
                                    <span>..</span>
                                </button>
                            </template>
                            <template x-for="folder in browserFolders" :key="folder.path">
                                <button type="button" @click="browseDir(folder.path)" class="v3-mediatile">
                                    <x-admin3.icon name="folder" class="v3-mediatile__folder" />
                                    <span x-text="folder.name"></span>
                                </button>
                            </template>
                            <template x-for="file in filteredBrowserFiles" :key="file.path">
                                <button type="button" @click="selectImage(file)" class="v3-mediatile">
                                    <span class="v3-mediatile__frame"><img :src="file.url" :alt="file.name" loading="lazy"></span>
                                    <span x-text="file.name"></span>
                                </button>
                            </template>
                        </div>
                        <div x-show="!browserLoading && browserFolders.length === 0 && browserFiles.length === 0" class="v3-empty">
                            <p class="v3-empty__title">Empty folder</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin3.form>

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

                browserOpen: false,
                browserTarget: 'layer',
                browserLoading: false,
                browserPath: '',
                browserParent: '',
                browserFolders: [],
                browserFiles: [],
                browserSearch: '',
                lastBrowsedPath: null,

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

                async openBrowser(target) {
                    this.browserTarget = target;
                    this.browserOpen = true;
                    await this.browseDir(this.lastBrowsedPath ?? 'Banners');
                },

                get breadcrumbs() {
                    if (!this.browserPath) return [];
                    const parts = this.browserPath.split('/');
                    return parts.map((part, i) => ({ name: part, path: parts.slice(0, i + 1).join('/') }));
                },

                get filteredBrowserFiles() {
                    let files = [...this.browserFiles];
                    if (this.browserSearch) {
                        const q = this.browserSearch.toLowerCase();
                        files = files.filter(f => f.name.toLowerCase().includes(q));
                    }
                    return files.sort((a, b) => b.modified - a.modified);
                },

                async browseDir(path) {
                    this.browserLoading = true;
                    const params = new URLSearchParams();
                    if (path) params.set('path', path);

                    const res = await fetch(`{{ route('admin.products.images.browse') }}?${params}`);
                    const data = await res.json();
                    if (data.ok) {
                        this.browserPath = data.path;
                        this.browserParent = data.parent ?? '';
                        this.browserFolders = data.folders;
                        this.browserFiles = data.files;
                        this.lastBrowsedPath = data.path;
                    }
                    this.browserLoading = false;
                },

                async uploadToBrowser(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    const body = new FormData();
                    body.append('file', file);
                    body.append('catalog_path', this.browserPath);
                    this.browserLoading = true;
                    try {
                        const res = await fetch('{{ route('admin.products.images.upload-to-catalog') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                            body,
                        });
                        const data = await res.json();
                        if (data.ok) await this.browseDir(this.browserPath);
                    } finally {
                        this.browserLoading = false;
                        event.target.value = '';
                    }
                },

                selectImage(file) {
                    if (this.browserTarget === 'bg') {
                        this.bgImage = file.path;
                    } else if (this.browserTarget === 'bg_mobile') {
                        this.bgImageMobile = file.path;
                    } else if (this.currentLayer) {
                        this.currentLayer.src = file.path;
                    }
                    this.browserOpen = false;
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
</x-admin3.shell>
