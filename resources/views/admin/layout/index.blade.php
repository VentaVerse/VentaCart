<x-admin.shell title="Layout">
    @push('head')
        @vite('resources/css/admin/layout-editor.css')
    @endpush

    @php
        $vPageName = $pageType === '*' ? 'Every page' : ($pageTypes[$pageType] ?? $pageType);
        $vCount = fn ($key) => (int) ($counts[$key] ?? 0);
        $vWidgets = fn ($n) => $n . ' ' . ($n === 1 ? 'widget' : 'widgets');
        $vRegion = fn ($key) => $regions[$key] ?? null;
    @endphp

    <div class="v-head">
        <div class="v-head__body">
            {{-- Storefront / Layout, and the theme being arranged when more
                 than one theme can be. --}}
            <div class="v-head__trail">
                <span>Storefront</span>
                <span aria-hidden="true">/</span>
                <span>Layout</span>
                @if (count($installedThemes) > 1)
                    <span aria-hidden="true">/</span>
                    <span>{{ $theme }}</span>
                @endif
            </div>
            <h1 class="v-head__title">Layout</h1>
        </div>
        <div class="v-head__actions">
            @if (count($installedThemes) > 1)
                <label class="v-actrow">
                    <span class="v-field__label">Theme</span>
                    <select class="v-control" onchange="window.location = this.value" aria-label="Theme being arranged">
                        @foreach ($installedThemes as $vSlug => $vName)
                            @php $vLiveSlug = $themeQuery === [] ? $themeSlug : null; @endphp
                            <option value="{{ route('admin.layout', ['page' => $pageType] + ($vSlug === $vLiveSlug ? [] : ['theme' => $vSlug])) }}" @selected($vSlug === $themeSlug)>{{ $vName }}{{ $vSlug === $vLiveSlug ? ' (live)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            {{-- A real page of the type being edited, to see the result. --}}
            <a href="{{ $sampleUrl }}" target="_blank" rel="noopener" class="v-btn v-btn--secondary v-btn--sm">View store</a>
        </div>
    </div>

    <x-admin.form :action="route('admin.layout.update', $themeQuery)" :acts="false">
      <div class="v-lay" :class="{ 'is-inspecting': editing || editingRow }" x-data="layoutEditor(window.__layout)">
        <input type="hidden" name="payload" :value="payload()">

        {{-- On a phone the rail of twelve pages would fill the first screen,
             so there it is one control. --}}
        <label class="v-lay__pagepick">
            <span>Page</span>
            <select class="v-control" onchange="window.location = this.value">
                @foreach ($pageTypes as $vKey => $vLabel)
                    <option value="{{ route('admin.layout', ['page' => $vKey] + $themeQuery) }}" @selected($pageType === $vKey)>{{ $vLabel }} ({{ $vCount($vKey) }})</option>
                @endforeach
                <option value="{{ route('admin.layout', ['page' => '*'] + $themeQuery) }}" @selected($pageType === '*')>Every page, shared ({{ $vCount('*') }})</option>
            </select>
        </label>

        <nav class="v-lay__rail" aria-label="Pages">
            <p class="v-lay__raillbl">Pages</p>
            @foreach ($pageTypes as $key => $label)
                <a href="{{ route('admin.layout', ['page' => $key] + $themeQuery) }}"
                   class="{{ $pageType === $key ? 'is-on' : '' }}" @if ($pageType === $key) aria-current="page" @endif>
                    <span>{{ $label }}</span>
                    <span class="n {{ $vCount($key) === 0 ? 'is-zero' : '' }}" aria-label="{{ $vWidgets($vCount($key)) }}">{{ $vCount($key) }}</span>
                </a>
            @endforeach
            <p class="v-lay__raillbl">Shared</p>
            <a href="{{ route('admin.layout', ['page' => '*'] + $themeQuery) }}"
               class="{{ $pageType === '*' ? 'is-on' : '' }}" @if ($pageType === '*') aria-current="page" @endif>
                <span>Every page (shared)</span>
                <span class="n {{ $vCount('*') === 0 ? 'is-zero' : '' }}" aria-label="{{ $vWidgets($vCount('*')) }}">{{ $vCount('*') }}</span>
            </a>
        </nav>

        <div class="v-lay__stage">
            @if ($liveElsewhere)
                {{-- The live theme keeps its own layout, so this screen is
                     arranging another theme. Said first, not discovered
                     after a save that never shows on the storefront. --}}
                <div class="v-lay__notice" role="note">
                    <x-admin.icon name="info" />
                    <p>
                        <b>Your live theme {{ $liveElsewhere['name'] }} arranges its pages in its own editor.</b>
                        Changes here apply to {{ $liveElsewhere['editing'] }}, which shoppers see only if you switch back to it.
                        @if ($liveElsewhere['url'])
                            <a href="{{ $liveElsewhere['url'] }}">Open the {{ $liveElsewhere['name'] }} editor</a>
                        @endif
                    </p>
                </div>
            @endif

            <p class="v-lay__intro">
                @if ($pageType === '*')
                    Widgets placed here show on every page of the store, above or below that page's own widgets.
                @else
                    Add widgets to the positions of <b>{{ $vPageName }}</b>. Widgets shared by every page show greyed; edit those on Every page.
                @endif
            </p>

            {{-- Placements in positions this theme no longer has. Kept, never
                 deleted, and listed so they can be moved back. --}}
            <div class="v-lay__orphans" x-show="orphans.length" x-cloak>
                <p>
                    <x-admin.icon name="warning" />
                    <span x-text="orphans.length + (orphans.length === 1 ? ' widget sits' : ' widgets sit') + ' in a position this theme does not have. They show nothing until you move them.'"></span>
                </p>
                <template x-for="o in orphans" :key="o.id">
                    <div class="v-lay__orphan">
                        <span x-text="o.title || o.widget"></span>
                        <span x-text="o.page"></span>
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="rescue(o)"
                                x-text="'Move to ' + regionLabel('content_bottom')"></button>
                    </div>
                </template>
            </div>

            <div class="v-lay__page">
                @if ($vRegion('header_top'))
                    <div class="v-lay__strip">
                        <x-admin.layout-slot region="header_top" :label="$regions['header_top']['label']" :description="$regions['header_top']['description'] ?? null" />
                    </div>
                @endif

                @if ($vRegion('header'))
                    {{-- A theme whose header is rows: the merchant arranges it here. --}}
                    <div class="v-lay__strip v-lay__strip--system">
                        <x-admin.layout-slot region="header" :label="$regions['header']['label'] ?? 'Header'" :description="$regions['header']['description'] ?? null" />
                    </div>
                @else
                    <div class="v-lay__chrome" :class="{ 'is-off': !partOn('header') }">
                        <p class="v-lay__chrometext">
                            <b>Header</b>
                            <span>Logo, search and the main menu, the same on every page.</span>
                            @if ($headerFooterUrl)
                                <a href="{{ $headerFooterUrl }}">Edit in Header &amp; footer</a>
                            @endif
                        </p>
                        <template x-for="f in partsIn('header')" :key="f.name">
                            <x-admin.part-switch text="Show on this page" />
                        </template>
                    </div>
                @endif

                <div class="v-lay__middle">
                    @if ($vRegion('column_left'))
                        <div class="v-lay__col v-lay__col--side" :class="{ 'is-off': cols.left === 0 }"
                             :style="cols.left > 0 ? 'width:' + (cols.left / grid * 100) + '%' : ''">
                            <x-admin.layout-slot region="column_left" :label="$regions['column_left']['label']" :description="$regions['column_left']['description'] ?? null" side="left" />
                        </div>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="{{ $regions['column_left']['label'] }} width"
                                 :aria-valuenow="cols.left" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical" title="Drag to change the width"
                                 @pointerdown="grab('left', $event)"
                                 @keydown.arrow-left.prevent="nudge('left', -1)"
                                 @keydown.arrow-right.prevent="nudge('left', 1)"></div>
                        </template>
                    @endif

                    <div class="v-lay__col v-lay__col--center">
                        @if ($vRegion('content_top'))
                            <x-admin.layout-slot region="content_top" :label="$regions['content_top']['label']" :description="$regions['content_top']['description'] ?? null" />
                        @endif

                        @php
                            // A theme may declare this page's own pieces as a
                            // region (a two-column product page): it is arranged
                            // here, in place of the fixed page body.
                            $vOwnRegion = collect($regions)->filter(function ($spec, $key) use ($pageType) {
                                return ! empty($spec['defaults']) && ($spec['page'] ?? '*') === $pageType && $pageType !== '*';
                            })->keys()->first();
                        @endphp
                        @if ($vOwnRegion)
                            <div class="v-lay__pagebody--region">
                                <x-admin.layout-slot :region="$vOwnRegion" :label="$regions[$vOwnRegion]['label'] ?? 'Page content'" :description="$regions[$vOwnRegion]['description'] ?? null" />
                            </div>
                        @elseif (collect($pageSettings)->contains(fn ($f) => ($f['part'] ?? null) === 'body'))
                            {{-- Only a theme that lets a merchant hide parts of
                                 the page's own content gets switches here; the
                                 page's content itself is not drawn, as the
                                 positions around it are what Layout arranges. --}}
                            <section class="v-lay__builtin" aria-labelledby="v-builtin-title">
                                <h2 class="v-lay__builtintitle" id="v-builtin-title">Page options</h2>
                                <ul class="v-lay__parts">
                                    <template x-for="f in bodyParts()" :key="f.name">
                                        <li class="v-lay__part" :class="{ 'is-off': !f.value }">
                                            <x-admin.part-switch />
                                            <span class="v-lay__partdesc" x-show="f.description" x-text="f.description"></span>
                                        </li>
                                    </template>
                                </ul>
                            </section>
                        @endif

                        @if ($vRegion('content_bottom'))
                            <x-admin.layout-slot region="content_bottom" :label="$regions['content_bottom']['label']" :description="$regions['content_bottom']['description'] ?? null" />
                        @endif
                    </div>

                    @if ($vRegion('column_right'))
                        <template x-if="cols.right > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="{{ $regions['column_right']['label'] }} width"
                                 :aria-valuenow="cols.right" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical" title="Drag to change the width"
                                 @pointerdown="grab('right', $event)"
                                 @keydown.arrow-left.prevent="nudge('right', 1)"
                                 @keydown.arrow-right.prevent="nudge('right', -1)"></div>
                        </template>
                        <div class="v-lay__col v-lay__col--side" :class="{ 'is-off': cols.right === 0 }"
                             :style="cols.right > 0 ? 'width:' + (cols.right / grid * 100) + '%' : ''">
                            <x-admin.layout-slot region="column_right" :label="$regions['column_right']['label']" :description="$regions['column_right']['description'] ?? null" side="right" />
                        </div>
                    @endif
                </div>

                <div class="v-lay__ruler" x-show="cols.left > 0 || cols.right > 0" x-cloak>
                    <span x-text="widthSentence()"></span>
                    <span class="v-lay__sp"></span>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="cols.left > 0" @click="dropColumn('left')"
                            x-text="dropLabel('left')"></button>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="cols.right > 0" @click="dropColumn('right')"
                            x-text="dropLabel('right')"></button>
                </div>

                {{-- Footer top sits above the footer, as the storefront draws it. --}}
                @if ($vRegion('footer_top'))
                    <div class="v-lay__strip">
                        <x-admin.layout-slot region="footer_top" :label="$regions['footer_top']['label']" :description="$regions['footer_top']['description'] ?? null" />
                    </div>
                @endif

                <div class="v-lay__chrome v-lay__chrome--foot" :class="{ 'is-off': !partOn('footer') }">
                    <p class="v-lay__chrometext">
                        <b>Footer</b>
                        <span>Links, contact details and payment icons, the same on every page.</span>
                        @if ($headerFooterUrl)
                            <a href="{{ $headerFooterUrl }}">Edit in Header &amp; footer</a>
                        @endif
                    </p>
                    <template x-for="f in partsIn('footer')" :key="f.name">
                        <x-admin.part-switch text="Show on this page" />
                    </template>
                </div>
            </div>
        </div>

        {{-- Settings for the widget being edited, from its own schema. --}}
        <div class="v-lay__inspector" x-show="editing" x-cloak
             role="region" aria-labelledby="v-inspect-title"
             @keydown.escape.window="if (editing && !panel) closeInspector()">
            <div class="v-lay__pickhead">
                <div>
                    <b id="v-inspect-title" x-text="editing ? lineName(editing) : ''"></b>
                    <span class="v-lay__picksub" x-text="editing ? editing.label + ' in ' + regionLabel(editingAt?.region) : ''"></span>
                </div>
                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="closeInspector()">Done</button>
            </div>

            <label class="v-lay__field">
                <span>Heading shown on the page</span>
                <input type="text" class="v-control" :value="editing?.title ?? ''" placeholder="No heading"
                       @input="editing.title = $event.target.value; touch()">
            </label>

            <template x-for="f in schemaFor(editing?.widget)" :key="f.name">
                <div class="v-lay__field" :data-needed="f.name === firstNeeded(editing?.widget) ? 'true' : null">
                    <template x-if="f.type === 'select'">
                        <label class="v-lay__fieldin">
                            <span x-text="f.label"></span>
                            <span class="v-lay__withnew">
                                {{-- :selected on the option, not :value on the
                                     select: a select's value cannot bind before
                                     x-for has added its options. --}}
                                <select class="v-control" @change="setCfg(f.name, $event.target.value)">
                                    <option value="">Choose&hellip;</option>
                                    <template x-for="(label, value) in f.options" :key="value">
                                        <option :value="value" :selected="String(cfg(f.name)) === String(value)" x-text="label"></option>
                                    </template>
                                </select>
                                <a x-show="f.create_url" :href="f.create_url" target="_blank" rel="noopener"
                                   class="v-lay__new" x-text="f.create_label ?? 'New'"></a>
                            </span>
                        </label>
                    </template>

                    <template x-if="f.type === 'number'">
                        <label class="v-lay__fieldin">
                            <span x-text="f.label"></span>
                            <input type="number" class="v-control" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                        </label>
                    </template>

                    <template x-if="f.type === 'toggle'">
                        <label class="v-toggle">
                            <input type="checkbox" :checked="[true, 1, '1', 'true', 'on'].includes(cfg(f.name) ?? f.default)"
                                   @change="setCfg(f.name, $event.target.checked)">
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span x-text="f.label"></span>
                        </label>
                    </template>

                    <template x-if="f.type === 'textarea'">
                        <label class="v-lay__fieldin">
                            <span x-text="f.label"></span>
                            <textarea class="v-control" :rows="f.code ? 10 : 4" :class="{ 'v-lay__code': f.code }" :spellcheck="f.code ? 'false' : 'true'"
                                      :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)"></textarea>
                        </label>
                    </template>

                    {{-- One image, or several, chosen through the page's media
                         browser aimed at this field. --}}
                    <template x-if="f.type === 'image' || f.type === 'images'">
                        <div class="v-lay__fieldin" role="group" :aria-label="f.label">
                            <span x-text="f.label"></span>
                            <span class="v-lay__media">
                                <template x-for="(path, pi) in mediaList(f)" :key="path + pi">
                                    <span class="v-lay__mediaitem">
                                        <img :src="'/storage/' + path" alt="">
                                        <button type="button" @click="dropMedia(f, pi)" aria-label="Remove image" title="Remove image">
                                            <x-admin.icon name="x" />
                                        </button>
                                    </span>
                                </template>
                                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="pickMedia(f, $el)"
                                        x-text="f.type === 'images' ? 'Add images' : (cfg(f.name) ? 'Change image' : 'Choose image')"></button>
                            </span>
                        </div>
                    </template>

                    {{-- Repeating rows of small fields: tabs, list items, sections. --}}
                    <template x-if="f.type === 'rows'">
                        <div class="v-lay__fieldin" role="group" :aria-label="f.label">
                            <span x-text="f.label"></span>
                            <span class="v-lay__rows">
                                <template x-for="(row, ri) in rowsOf(f)" :key="ri">
                                    <span class="v-lay__rowsitem">
                                        <template x-for="(sub, subName) in f.fields" :key="subName">
                                            <label class="v-lay__subfield">
                                                <span x-text="sub.label"></span>
                                                <template x-if="sub.type === 'select'">
                                                    <select class="v-control" @change="setRowField(f, ri, subName, $event.target.value)">
                                                        <option value="">Choose&hellip;</option>
                                                        <template x-for="(label, value) in sub.options" :key="value">
                                                            <option :value="value" :selected="row[subName] === value" x-text="label"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="sub.type === 'textarea'">
                                                    <textarea class="v-control" rows="3" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)"></textarea>
                                                </template>
                                                <template x-if="sub.type === 'number'">
                                                    <input type="number" class="v-control" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)">
                                                </template>
                                                <template x-if="sub.type !== 'select' && sub.type !== 'textarea' && sub.type !== 'number'">
                                                    <input type="text" class="v-control" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)">
                                                </template>
                                            </label>
                                        </template>
                                        <span class="v-lay__rowsacts">
                                            <button type="button" class="v-iconbtn" @click="moveRowField(f, ri, -1)" :disabled="ri === 0" aria-label="Move up" title="Move up"><x-admin.icon name="chevron-up" /></button>
                                            <button type="button" class="v-iconbtn" @click="moveRowField(f, ri, 1)" :disabled="ri === rowsOf(f).length - 1" aria-label="Move down" title="Move down"><x-admin.icon name="chevron-down" /></button>
                                            <button type="button" class="v-textbtn v-textbtn--danger" @click="dropRowField(f, ri)">Remove</button>
                                        </span>
                                    </span>
                                </template>
                                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="addRowField(f)">Add</button>
                            </span>
                        </div>
                    </template>

                    <template x-if="!['select', 'number', 'toggle', 'textarea', 'image', 'images', 'rows'].includes(f.type)">
                        <label class="v-lay__fieldin">
                            <span x-text="f.label"></span>
                            <input type="text" class="v-control" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                        </label>
                    </template>
                </div>
            </template>

            <p class="v-lay__pickempty" x-show="schemaFor(editing?.widget).length === 0">This widget has no settings of its own.</p>

            {{-- The page's media browsers, aimed at whichever field asked:
                 one image is chosen in one click, several are gathered first. --}}
            <x-admin.media-picker name="widget_image" mode="single" root="banners" bare trigger="Choose"
                class="v-lay__mediapicker" data-media-one @media:picked="heardMedia($event.detail)" />
            <x-admin.media-picker name="widget_media" mode="multi" root="products" bare trigger="Choose"
                class="v-lay__mediapicker" data-media-many @media:picked="heardMedia($event.detail)" />

            <label class="v-lay__field v-lay__fieldin" x-show="editing && movesFor(editing.widget).length > 1">
                <span>Position</span>
                <select class="v-control" @change="moveCard(editing, $event.target.value)">
                    <template x-for="target in movesFor(editing?.widget)" :key="target">
                        <option :value="target" :selected="target === editingAt?.region" x-text="regionLabel(target)"></option>
                    </template>
                </select>
            </label>

            {{-- A one-column row has no chrome on the page, so its columns
                 and style are reached from here, closed until asked for. --}}
            <template x-if="editingAt && editingAt.unit.columns.length === 1">
                <div class="v-lay__rowlook">
                    <button type="button" class="v-textbtn" :aria-expanded="inspectRowTools ? 'true' : 'false'"
                            @click="inspectRowTools = !inspectRowTools">
                        Columns and style<span x-show="styledRow(editingAt.unit)">: styled</span>
                    </button>
                    <template x-if="inspectRowTools">
                        <div>
                            @include('admin.layout.partials.row-tools', ['unitExpr' => 'editingAt.unit', 'regionExpr' => 'editingAt.region'])
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- The row's own style, from a closed set of choices. Colors come
             from the theme's palette or a hex; the storefront paints the row
             from custom properties, the rules live in one shared stylesheet. --}}
        <div class="v-lay__inspector v-lay__inspector--row" x-show="editingRow" x-cloak
             role="region" aria-labelledby="v-rowstyle-title"
             @keydown.escape.window="editingRow = null">
            <div class="v-lay__pickhead">
                <b id="v-rowstyle-title">Row style</b>
                <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="editingRow = null">Done</button>
            </div>
            <label class="v-lay__field">
                <span>Style preset</span>
                <span class="v-lay__withnew">
                    <select @change="applyPreset($event.target.value); $event.target.value = ''">
                        <option value="">Choose&hellip;</option>
                        <template x-for="p in stylePresets" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </span>
            </label>

            <div class="v-lay__field v-lay__presetsave">
                <input type="text" placeholder="" x-model="presetName" aria-label="Preset name" maxlength="80">
                <button type="button" class="v-btn v-btn--sm" @click="savePreset()" :disabled="!presetName.trim()">Save as preset</button>
            </div>

            <div class="v-lay__presets" x-show="stylePresets.length" x-cloak>
                <template x-for="p in stylePresets" :key="'d' + p.id">
                    <span class="v-lay__presetrow">
                        <button type="button" @click="applyPreset(p.id)" x-text="p.name"></button>
                        <button type="button" class="v-lay__presetx" @click="deletePreset(p)" :aria-label="'Delete preset ' + p.name" title="Delete preset">&times;</button>
                    </span>
                </template>
            </div>

            <label class="v-lay__field">
                <span>Background</span>
                <select :value="rs('bg')" @change="setRowStyle('bg', $event.target.value)">
                    <option value="none">None</option>
                    <option value="color">Color</option>
                    <option value="gradient">Gradient</option>
                    <option value="image">Image</option>
                </select>
            </label>

            <template x-if="rs('bg') === 'color' || rs('bg') === 'gradient'">
                <div class="v-lay__field">
                    <span x-text="rs('bg') === 'gradient' ? 'From' : 'Color'"></span>
                    <x-admin.row-color field="bg_color" />
                </div>
            </template>

            <template x-if="rs('bg') === 'gradient'">
                <div>
                    <div class="v-lay__field">
                        <span>To</span>
                        <x-admin.row-color field="bg_color2" />
                    </div>
                    <label class="v-lay__field">
                        <span>Angle</span>
                        <input type="number" min="0" max="360" step="15" :value="rs('bg_angle')" @input="setRowStyle('bg_angle', $event.target.value)">
                    </label>
                </div>
            </template>

            <template x-if="rs('bg') === 'image'">
                <div>
                    <div class="v-lay__field">
                        <span>Image</span>
                        <span class="v-lay__imgpath">
                            <span class="mono" x-text="rs('bg_image') || 'None chosen'"></span>
                            <button type="button" class="v-lay__new" x-show="rs('bg_image')" @click="setRowStyle('bg_image', '')">Clear</button>
                        </span>
                        <x-admin.media-picker name="row_bg_image" root="banners" bare trigger="Choose image"
                            @media:picked="setRowStyle('bg_image', $event.detail.path ?? ($event.detail.paths ?? [])[0] ?? '')" />
                    </div>
                    <label class="v-lay__field">
                        <span>Size</span>
                        <select :value="rs('bg_size')" @change="setRowStyle('bg_size', $event.target.value)">
                            <option value="cover">Cover</option><option value="contain">Contain</option><option value="auto">Actual size</option>
                        </select>
                    </label>
                    <label class="v-lay__field">
                        <span>Position</span>
                        <select :value="rs('bg_position')" @change="setRowStyle('bg_position', $event.target.value)">
                            <option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option><option value="left">Left</option><option value="right">Right</option>
                        </select>
                    </label>
                    <label class="v-lay__field">
                        <span>Repeat</span>
                        <select :value="rs('bg_repeat')" @change="setRowStyle('bg_repeat', $event.target.value)">
                            <option value="no-repeat">No repeat</option><option value="repeat">Repeat</option>
                        </select>
                    </label>
                    <label class="v-lay__field">
                        <span>Scrolling</span>
                        <select :value="rs('bg_attachment')" @change="setRowStyle('bg_attachment', $event.target.value)">
                            <option value="scroll">Scrolls with the page</option><option value="fixed">Fixed</option>
                        </select>
                    </label>
                </div>
            </template>

            <div class="v-lay__field">
                <span>Text color</span>
                <x-admin.row-color field="text" />
            </div>

            <label class="v-lay__field">
                <span>Padding</span>
                <select :value="rs('pad')" @change="setRowStyle('pad', $event.target.value)">
                    <option value="none">None</option><option value="sm">Small</option><option value="md">Medium</option><option value="lg">Large</option><option value="xl">Extra large</option>
                </select>
            </label>

            <label class="v-lay__field">
                <span>Width</span>
                <select :value="rs('width')" @change="setRowStyle('width', $event.target.value)">
                    <option value="contained">On the page</option><option value="full">Edge to edge</option><option value="edge">Edge to edge, content too</option>
                </select>
            </label>

            <label class="v-lay__field">
                <span>Border</span>
                <select :value="rs('border')" @change="setRowStyle('border', $event.target.value)">
                    <option value="none">None</option><option value="solid">Solid</option><option value="dashed">Dashed</option><option value="dotted">Dotted</option><option value="double">Double</option>
                </select>
            </label>

            <template x-if="rs('border') !== 'none'">
                <div>
                    <label class="v-lay__field">
                        <span>Border width</span>
                        <input type="number" min="1" max="8" :value="rs('border_width')" @input="setRowStyle('border_width', $event.target.value)">
                    </label>
                    <div class="v-lay__field">
                        <span>Border color</span>
                        <x-admin.row-color field="border_color" />
                    </div>
                </div>
            </template>

            <label class="v-lay__field">
                <span>Corners</span>
                <select :value="rs('radius')" @change="setRowStyle('radius', $event.target.value)">
                    <option value="none">Square</option><option value="sm">Slightly rounded</option><option value="md">Rounded</option><option value="lg">Very rounded</option>
                </select>
            </label>

            <label class="v-lay__field">
                <span>Shadow</span>
                <select :value="rs('shadow')" @change="setRowStyle('shadow', $event.target.value)">
                    <option value="none">None</option><option value="sm">Light</option><option value="md">Medium</option><option value="lg">Heavy</option>
                    <option value="custom" x-show="rs('shadow') === 'custom'">Custom</option>
                </select>
            </label>

            <label class="v-lay__field">
                <span>Space above (px)</span>
                <input type="number" min="-200" max="400" placeholder="Theme" :value="rs('margin_top')" @input="setRowStyle('margin_top', $event.target.value)">
            </label>
            <label class="v-lay__field">
                <span>Space below (px)</span>
                <input type="number" min="-200" max="400" placeholder="Theme" :value="rs('margin_bottom')" @input="setRowStyle('margin_bottom', $event.target.value)">
            </label>

            <label class="v-lay__field">
                <span>Extra CSS class</span>
                <input type="text" maxlength="200" placeholder="promo-band" :value="rs('css_class')" @change="setRowStyle('css_class', $event.target.value)">
            </label>

            <div class="v-lay__field">
                <span>Hide on</span>
                <span class="v-lay__hide">
                    <template x-for="d in [['hide_phone', 'Phones'], ['hide_tablet', 'Tablets'], ['hide_desktop', 'Desktops']]" :key="d[0]">
                        <label><input type="checkbox" :checked="!!rs(d[0])" @change="setRowStyle(d[0], $event.target.checked)"> <span x-text="d[1]"></span></label>
                    </template>
                </span>
            </div>

            <button type="button" class="v-lay__new" @click="clearRowStyle()">Remove all styling</button>
        </div>

        {{-- The widget's own subject, edited without leaving the layout.

             Two levels, and they belong to different owners. The first is
             ours: which record sits in this position is layout state, so
             choosing one arms this page's Save and writes nothing. Below
             that is the module's own page in a frame, saving itself the way
             every record editor opened by an explicit Edit already does. The
             two save scopes alternate by level and never overlap, which is
             what makes the nesting safe. --}}
        <div class="v-lay__panel" x-show="panel" x-cloak
             x-dialog="!!panel"
             @keydown.escape.window="closeSubject()"
             role="dialog" aria-modal="true" aria-labelledby="v-panel-title">
            {{-- The chooser is a short list and the editor is a whole page,
                 so the sheet is sized for what it is holding rather than
                 stretched to the tallest thing it might hold. --}}
            <div class="v-lay__sheet" :class="{ 'is-framed': frameUrl }" @click.outside="closeSubject()">
                <div class="v-lay__phead">
                    <button type="button" class="v-lay__pback" x-show="frameUrl" x-cloak
                            @click="panelBack()" aria-label="Back" title="Back"><x-admin.icon name="arrow-left" /></button>

                    <div class="v-lay__ptrail">
                        <template x-if="frameUrl">
                            <button type="button" @click="frameUrl = null" x-text="panel?.field?.label"></button>
                        </template>
                        <span aria-hidden="true" x-show="frameUrl" x-cloak>/</span>
                        <b id="v-panel-title" x-text="frameUrl ? (frameTitle || '&hellip;') : panel?.field?.label"></b>
                        <span class="v-lay__powner" :class="{ 'is-module': frameUrl }"
                              x-text="frameUrl ? ownerOf(panel?.row) : 'Layout'"></span>
                    </div>

                    <button type="button" class="v-lay__pclose" @click="closeSubject()"
                            aria-label="Close" title="Close" data-v-dialog-initial><x-admin.icon name="x" /></button>
                </div>

                <div class="v-lay__pbody">
                    {{-- level one: the choices, drawn from rows the module handed
                         over. No module code renders here, so a widget pointing at
                         a category gets this panel the day it declares one. --}}
                    <div class="v-lay__browse" x-show="!frameUrl">
                        <template x-for="choice in panel?.field?.browse ?? []" :key="choice.value">
                            <div class="v-lay__choice" :class="{ 'is-on': choice.value === panelValue() }">
                                <button type="button" class="v-lay__choicepick"
                                        @click="chooseSubject(choice)"
                                        :aria-pressed="choice.value === panelValue() ? 'true' : 'false'">
                                    <span class="v-lay__thumbs" aria-hidden="true">
                                        <template x-for="thumb in choice.thumbs" :key="thumb">
                                            <img :src="thumb" alt="" loading="lazy">
                                        </template>
                                    </span>
                                    <span class="v-lay__choicetxt">
                                        <span class="v-lay__choicenm" x-text="choice.label"></span>
                                        <span class="mono" x-text="choice.meta"></span>
                                    </span>
                                    <span class="v-tag" x-show="choice.value === panelValue()" x-cloak>In this position</span>
                                </button>
                                <button type="button" class="v-lay__choicego" x-show="choice.url"
                                        @click="enterSubject(choice)"
                                        :aria-label="'Open ' + choice.label">Open</button>
                            </div>
                        </template>

                        <p class="v-lay__pickempty" x-show="(panel?.field?.browse ?? []).length === 0">
                            Nothing saved yet
                        </p>

                        <a class="v-lay__pnew" x-show="panel?.field?.create_url" :href="panel?.field?.create_url"
                           target="_blank" rel="noopener" x-text="panel?.field?.create_label ?? 'New'"></a>
                    </div>

                    {{-- level two and below: the module's own page, same admin,
                         furniture off. Its Save is its own. --}}
                    {{-- x-if, not x-show: a bound src with nothing in it is
                         still a request, and an empty frame sat there asking
                         the server for /undefined every time the panel opened.
                         Interaction-gated, which is what x-if is still for. --}}
                    <template x-if="frameUrl">
                        <iframe class="v-lay__frame" :src="frameUrl"
                                :title="panel?.field?.label" x-ref="frame"></iframe>
                    </template>
                </div>
            </div>
        </div>


        {{-- The one Save for this page, always in reach. The live region
             speaks every change once; the visible note beside the change is
             for sighted merchants. --}}
        <div class="v-lay__savebar">
            <p class="v-lay__said" role="status" aria-live="polite" x-text="announcement || saveState()"></p>
            <button type="button" class="v-btn v-btn--ghost" x-show="dirty" x-cloak @click="discard()" :disabled="saving">Discard</button>
            <button type="submit" class="v-btn v-btn--primary" :disabled="saving">
                <span x-text="saving ? 'Saving\u2026' : 'Save'">Save</span>
            </button>
        </div>
      </div>
    </x-admin.form>

    @push('scripts')
        <script>
            window.__layout = {
                pageType: @js($pageType),
                regions: @js($regions),
                rows: @js($rows),
                inherited: @js($inherited),
                layouts: @js($layouts),
                themeColors: @js($themeColors),
                stylePresets: @js($stylePresets),
                styleDefaults: @js($styleDefaults),
                stylePresetsUrl: @js($stylePresetsUrl),
                templates: @js($templates),
                templatesUrl: @js($templatesUrl),
                exportUrl: @js($exportUrl),
                importUrl: @js($importUrl),
                csrf: @js(csrf_token()),
                widgets: @js($widgets),
                schemas: @js($schemas),
                pageSettings: @js($pageSettings),
                orphans: @js($orphans),
                schemasUrl: @js(route('admin.layout.schemas', $themeQuery)),
                cols: @js($columns),
                grid: @js($grid),
                minContent: @js($minContent),
                everyPageUrl: @js(route('admin.layout', ['page' => '*'] + $themeQuery)),
            };

            function layoutEditor(seed) {
                // The form element, held in a closure. Inside an x-for scope
                // $el is the control that fired, so touch() dispatched change
                // on the checkbox that called it and re-entered itself:
                // "Maximum call stack size exceeded" on every page-setting click.
                let root = null;
                let nextKey = 1;
                const keyed = (card) => { if (!card.key) card.key = 'w' + (nextKey++); return card; };

                // A row as the editor holds it: its columns (widths in eighths),
                // its cards per column, and a stable key for x-for so a row
                // keeps its DOM while it is moved.
                const wrap = (unit) => ({
                    key: 'r' + (nextKey++),
                    id: unit.id ?? null,
                    columns: unit.columns && unit.columns.length ? unit.columns.slice() : [8],
                    enabled: unit.enabled !== false,
                    style: unit.style ? { ...unit.style } : null,
                    // Kept as loaded so a save here never resets what another
                    // editor set (device widths, column and block styles).
                    responsive: unit.responsive ?? null,
                    shipped: !!unit.shipped,
                    // Every widget carries a key too, so a line keeps its DOM
                    // (and its focus) while it moves.
                    cols: (unit.cols || [[]]).map((c) => c.map(keyed)),
                });

                const seedRows = {};
                const seeded = Array.isArray(seed.rows) ? {} : (seed.rows || {});
                Object.entries(seeded).forEach(([region, units]) => {
                    seedRows[region] = units.map(wrap);
                });

                return {
                    pageType: seed.pageType,
                    regions: seed.regions,
                    // An OBJECT even when the server sent nothing. A JS Array with
                    // string keys assigned to it is not reactive in Alpine, and
                    // the payload input silently kept its load-time value.
                    rows: seedRows,
                    inherited: seed.inherited,
                    layouts: seed.layouts,
                    widgets: seed.widgets,
                    cols: { left: seed.cols.left, right: seed.cols.right },
                    grid: seed.grid,
                    minContent: seed.minContent,
                    schemas: seed.schemas,
                    schemasUrl: seed.schemasUrl,
                    pageSettings: seed.pageSettings,
                    orphans: seed.orphans,
                    // Where the picker is open: { region, u, c }.
                    adding: null,
                    // The row whose style is open in the inspector.
                    editingRow: null,
                    themeColors: seed.themeColors,
                    stylePresets: seed.stylePresets,
                    styleDefaults: seed.styleDefaults,
                    stylePresetsUrl: seed.stylePresetsUrl,
                    presetName: '',
                    everyPageUrl: seed.everyPageUrl,
                    // Templates: the position whose menu is open and the
                    // button that opened it, the template form the menu
                    // started (save, insert, replace, delete), its choice and
                    // the name being typed.
                    menuFor: null,
                    menuFrom: null,
                    tplFor: null,
                    tplMode: null,
                    tplChoice: '',
                    templateName: '',
                    templates: seed.templates,
                    templatesUrl: seed.templatesUrl,
                    exportUrl: seed.exportUrl,
                    importUrl: seed.importUrl,
                    csrf: seed.csrf,
                    // The plus that opened the picker, so Escape can hand
                    // focus back to it rather than dropping it on the body.
                    addingFrom: null,
                    editing: null,
                    // Where the widget being edited sits: { region, unit }.
                    editingAt: null,
                    inspectRowTools: false,
                    // The split row whose Columns and style panel is open.
                    rowTools: null,
                    // The open subject panel: { row, field }. frameUrl null
                    // means level one, the choices; anything else is the
                    // module's own page inside the frame.
                    panel: null,
                    frameUrl: null,
                    frameTitle: '',
                    frameSteps: 0,
                    frameGoingBack: false,
                    dragging: null,
                    announcement: '',
                    // The position the last change happened in, so its note
                    // shows beside it.
                    saidIn: null,
                    sayTimer: null,
                    // The page as loaded, to count what has changed since.
                    baseline: null,

                    init() {
                        root = this.$root ?? this.$el;
                        this.$nextTick(() => { this.baseline = JSON.parse(this.payload()); });
                        window.addEventListener('focus', () => this.refreshChoices());
                        window.addEventListener('message', (e) => this.hearFrame(e));
                    },

                    /* ---- reading ---- */

                    rowsIn(region) { return this.rows[region] || []; },

                    /** Every card in a position, whatever row or column it is in. */
                    cardsIn(region) {
                        return this.rowsIn(region).flatMap((u) => u.cols.flat());
                    },

                    regionLabel(region) { return (this.regions[region] || {}).label || region; },

                    /** Rows a page inherits from Every page. Shown, never editable here. */
                    inheritedIn(region) {
                        return (this.inherited[region] || []);
                    },

                    inheritedCards(region) {
                        return this.inheritedIn(region).flatMap((u) => u.cols.flat());
                    },

                    /** The merchant's heading, else what the widget is. */
                    lineName(row) { return (row && (row.title || row.label || row.widget)) || ''; },

                    /** What the widget is, and what it shows when it shows one thing. */
                    lineType(row) {
                        const chosen = this.subjectOf(row)?.chosen?.label;
                        const kind = row.title && row.title !== row.label ? row.label : (chosen ? row.label : '');
                        return [kind, chosen].filter(Boolean).join(': ');
                    },

                    styledCard(row) {
                        return !!row.style && typeof row.style === 'object' && Object.keys(row.style).length > 0;
                    },

                    shapeLabel(shape) {
                        return shape.length === 1 ? 'One column' : shape.length + ' columns, ' + shape.join(' + ') + ' of ' + this.grid;
                    },

                    fullShape() {
                        return (this.layouts.find((s) => s.length === 1) || [8]).slice();
                    },

                    rowIndex(region, unit) { return this.rowsIn(region).indexOf(unit); },

                    widthSentence() {
                        const content = this.grid - this.cols.left - this.cols.right;
                        const parts = [];
                        if (this.cols.left) parts.push(this.regionLabel('column_left') + ' ' + this.cols.left);
                        parts.push('content ' + content);
                        if (this.cols.right) parts.push(this.regionLabel('column_right') + ' ' + this.cols.right);
                        return 'Widths, in ' + this.grid + ' parts: ' + parts.join(', ') + '. Drag the bar beside a column to change it.';
                    },

                    sameShape(a, b) {
                        return a.length === b.length && a.every((w, i) => w === b[i]);
                    },

                    commit() { this.rows = { ...this.rows }; },

                    /* ---- area templates ---- */

                    /** One position's rows as data: shapes, styles, cards. */
                    regionRows(region) {
                        return this.rowsIn(region).map((unit) => ({
                            columns: unit.columns, enabled: unit.enabled, style: unit.style, responsive: unit.responsive,
                            placements: unit.cols.flatMap((col, c) => col.map((p) => ({
                                column: c, widget: p.widget, title: p.title, enabled: p.enabled, config: (p.config && typeof p.config === 'object') ? Object.assign({}, p.config) : {}, style: p.style ?? null,
                            }))),
                        }));
                    },

                    headers(json = true) {
                        return Object.assign({ Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf },
                            json ? { 'Content-Type': 'application/json' } : {});
                    },

                    async saveTemplate(region) {
                        const name = this.templateName.trim();
                        if (!name) return;
                        try {
                            const res = await fetch(this.templatesUrl, { method: 'POST', headers: this.headers(),
                                body: JSON.stringify({ name, region, rows: this.regionRows(region) }) });
                            const body = await res.json();
                            if (!res.ok) throw new Error(body.message || 'refused');
                            this.templates = [...this.templates, body].sort((a, b) => a.name.localeCompare(b.name));
                            this.templateName = '';
                            this.closeTemplate();
                            this.say('Template ' + body.name + ' saved.', region);
                        } catch (e) {
                            this.say(e.message || 'The template could not be saved.', region);
                        }
                    },

                    savedTemplates() {
                        return this.templates.filter((t) => !String(t.id).startsWith('theme:'));
                    },

                    toggleMenu(region, el) {
                        if (this.menuFor === region) { this.closeMenu(true); return; }
                        this.openMenu(region, el);
                    },

                    /**
                     * Opens below its button, inside the viewport: it flips to
                     * the button's left edge when there is no room to the
                     * left, and focus goes to the first item.
                     */
                    openMenu(region, el) {
                        this.closeTemplate();
                        this.menuFor = region;
                        this.menuFrom = el;
                        this.$nextTick(() => {
                            const menu = document.getElementById('v-menu-' + region);
                            if (!menu) return;
                            // Left-aligned under its button when it fits on
                            // screen that way, else right-aligned.
                            const room = window.innerWidth - el.getBoundingClientRect().left - 8;
                            menu.classList.toggle('is-left', menu.offsetWidth <= room);
                            menu.querySelector('[role=menuitem]')?.focus();
                        });
                    },

                    closeMenu(refocus = true) {
                        const back = this.menuFrom;
                        this.menuFor = null;
                        this.menuFrom = null;
                        if (refocus) back?.focus();
                    },

                    /** Arrow keys, Home and End move; Escape and Tab leave. */
                    menuKey(e) {
                        const items = [...e.currentTarget.querySelectorAll('[role=menuitem]')];
                        const at = items.indexOf(document.activeElement);
                        const go = (i) => { e.preventDefault(); items[(i + items.length) % items.length]?.focus(); };
                        if (e.key === 'ArrowDown') go(at + 1);
                        else if (e.key === 'ArrowUp') go(at - 1);
                        else if (e.key === 'Home') go(0);
                        else if (e.key === 'End') go(items.length - 1);
                        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); this.closeMenu(true); }
                        else if (e.key === 'Tab') this.closeMenu(false);
                    },

                    itemOff(e) { return e?.currentTarget?.getAttribute('aria-disabled') === 'true'; },

                    startTemplate(region, mode, e) {
                        if (this.itemOff(e)) return;
                        this.closeMenu(false);
                        this.tplFor = region;
                        this.tplMode = mode;
                        this.tplChoice = '';
                        this.templateName = '';
                        this.$nextTick(() => root?.querySelector('#v-slot-' + region)?.closest('.v-slot')?.querySelector('[data-tpl-first]')?.focus());
                    },

                    closeTemplate() {
                        const region = this.tplFor;
                        this.tplFor = null;
                        this.tplMode = null;
                        if (region) document.getElementById('v-menubtn-' + region)?.focus();
                    },

                    tplTitle() {
                        return {
                            save: 'Save this position as a template',
                            insert: 'Insert a template after the widgets here',
                            replace: 'Replace the widgets here with a template',
                            delete: 'Delete a template',
                        }[this.tplMode] || '';
                    },

                    tplAction() {
                        return { insert: 'Insert', replace: 'Replace', delete: 'Delete template' }[this.tplMode] || '';
                    },

                    tplChoices() {
                        return this.tplMode === 'delete' ? this.savedTemplates() : this.templates;
                    },

                    async runTemplate(region) {
                        if (!this.tplChoice) return;
                        if (this.tplMode === 'delete') {
                            await this.deleteTemplate(this.tplChoice, region);
                        } else {
                            this.loadTemplate(region, this.tplChoice, this.tplMode === 'replace' ? 'replace' : 'append');
                        }
                        this.closeTemplate();
                    },

                    menuExport(region, e) {
                        if (this.itemOff(e)) return;
                        this.closeMenu(true);
                        this.exportRegion(region);
                    },

                    menuImport(region) {
                        this.closeMenu(true);
                        document.getElementById('v-import-' + region)?.click();
                    },

                    loadTemplate(region, id, how) {
                        const t = this.templates.find((x) => String(x.id) === String(id));
                        if (!t) return;
                        const fresh = t.rows.map((u) => wrap(u));
                        if (how === 'replace') {
                            if (this.rowsIn(region).some((u) => u.cols.flat().includes(this.editing))) this.editing = null;
                            if (this.rowsIn(region).includes(this.editingRow)) this.editingRow = null;
                            this.rows[region] = fresh;
                        } else {
                            if (!this.rows[region]) this.rows[region] = [];
                            this.rows[region].push(...fresh);
                        }
                        this.commit();
                        this.say(t.name + (how === 'replace' ? ' replaced the widgets in ' : ' added to ') + this.regionLabel(region) + '. Save to keep it.', region);
                        this.touch();
                    },

                    async deleteTemplate(id, region = null) {
                        const t = this.templates.find((x) => String(x.id) === String(id));
                        if (!t) return;
                        if (String(t.id).startsWith('theme:')) { this.say(t.name + ' ships with the theme and stays.', region); return; }
                        try {
                            const res = await fetch(this.templatesUrl + '/' + t.id, { method: 'DELETE', headers: this.headers(false) });
                            if (!res.ok) throw new Error('refused');
                            this.templates = this.templates.filter((x) => x.id !== t.id);
                            this.say('Template ' + t.name + ' deleted.', region);
                        } catch (e) {
                            this.say('The template could not be deleted.', region);
                        }
                    },

                    /** A form post, so the browser saves the file itself. */
                    exportRegion(region) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = this.exportUrl;
                        form.target = '_blank';
                        const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i); };
                        add('_token', this.csrf);
                        add('region', region);
                        add('rows', JSON.stringify(this.regionRows(region)));
                        document.body.appendChild(form);
                        form.submit();
                        form.remove();
                    },

                    async importRegion(region, event) {
                        const file = event.target.files?.[0];
                        event.target.value = '';
                        if (!file) return;
                        const data = new FormData();
                        data.append('file', file);
                        try {
                            const res = await fetch(this.importUrl, { method: 'POST', headers: this.headers(false), body: data });
                            const body = await res.json();
                            if (!res.ok) throw new Error(body.message || 'refused');
                            if (!this.rows[region]) this.rows[region] = [];
                            this.rows[region].push(...body.rows.map((u) => wrap(u)));
                            this.commit();
                            this.say(body.rows.length + (body.rows.length === 1 ? ' row' : ' rows') + ' imported into ' + this.regionLabel(region) + '. Save to keep it.', region);
                            this.touch();
                        } catch (e) {
                            this.say(e.message || 'The file could not be read.', region);
                        }
                    },

                    /* ---- row style ---- */

                    editRowOf(region, unit) {
                        this.editing = null;
                        this.editingAt = null;
                        this.editingRow = unit;
                        if (!unit.style) unit.style = { ...this.styleDefaults };
                    },

                    rs(name) {
                        const style = this.editingRow?.style ?? {};
                        return style[name] ?? this.styleDefaults[name] ?? '';
                    },

                    setRowStyle(name, value) {
                        if (!this.editingRow) return;
                        if (!this.editingRow.style) this.editingRow.style = { ...this.styleDefaults };
                        this.editingRow.style[name] = value;
                        this.commit();
                        this.touch();
                    },

                    clearRowStyle() {
                        if (!this.editingRow) return;
                        this.editingRow.style = { ...this.styleDefaults };
                        this.commit();
                        this.say('Row styling removed. Save to keep it.');
                        this.touch();
                    },

                    /** Is this row styled at all? Said in words on its lines. */
                    styledRow(unit) {
                        const s = unit?.style;
                        if (!s) return false;
                        return s.bg !== 'none' || s.text !== '' || s.pad !== 'none' || s.width !== 'contained'
                            || s.border !== 'none' || s.radius !== 'none' || s.shadow !== 'none'
                            || (s.margin_top ?? '') !== '' || (s.margin_bottom ?? '') !== '' || !!s.css_class
                            || !!s.hide_phone || !!s.hide_tablet || !!s.hide_desktop;
                    },

                    /** A hex to show on a chip, whether the value is a hex or a theme key. */
                    swatch(value) {
                        if (!value) return 'transparent';
                        if (value.startsWith('#')) return value;
                        return (this.themeColors.find((c) => c.key === value) || {}).hex || 'transparent';
                    },

                    applyPreset(id) {
                        const preset = this.stylePresets.find((p) => String(p.id) === String(id));
                        if (!preset || !this.editingRow) return;
                        this.editingRow.style = { ...this.styleDefaults, ...preset.style };
                        this.commit();
                        this.say('Preset ' + preset.name + ' applied. Save to keep it.');
                        this.touch();
                    },

                    async savePreset() {
                        const name = this.presetName.trim();
                        if (!name || !this.editingRow) return;
                        try {
                            const res = await fetch(this.stylePresetsUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                                body: JSON.stringify({ name, style: this.editingRow.style ?? this.styleDefaults }),
                            });
                            if (!res.ok) throw new Error('refused');
                            const preset = await res.json();
                            this.stylePresets = [...this.stylePresets, preset].sort((a, b) => a.name.localeCompare(b.name));
                            this.presetName = '';
                            this.say('Preset ' + preset.name + ' saved.');
                        } catch (e) {
                            this.say('The preset could not be saved.');
                        }
                    },

                    async deletePreset(preset) {
                        try {
                            const res = await fetch(this.stylePresetsUrl + '/' + preset.id, {
                                method: 'DELETE',
                                headers: { Accept: 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                            });
                            if (!res.ok) throw new Error('refused');
                            this.stylePresets = this.stylePresets.filter((p) => p.id !== preset.id);
                            this.say('Preset ' + preset.name + ' deleted.');
                        } catch (e) {
                            this.say('The preset could not be deleted.');
                        }
                    },

                    /* ---- rows ---- */

                    /**
                     * Changing the shape keeps every widget: columns that
                     * vanish hand their widgets to the last column that
                     * remains, in order, rather than dropping them.
                     */
                    setShapeOf(region, unit, shape) {
                        const cols = shape.map((_, i) => unit.cols[i] ? unit.cols[i].slice() : []);
                        if (unit.cols.length > shape.length) {
                            cols[shape.length - 1].push(...unit.cols.slice(shape.length).flat());
                        }
                        unit.columns = shape.slice();
                        unit.cols = cols;
                        if (shape.length > 1) this.rowTools = unit;
                        this.commit();
                        this.say(shape.length === 1 ? 'Row set to one column. Save to keep it.'
                            : 'Row split into ' + shape.length + ' columns. Save to keep it.', region);
                        this.touch();
                    },

                    moveRowOf(region, unit, by) {
                        const list = this.rows[region];
                        const u = list ? list.indexOf(unit) : -1;
                        const to = u + by;
                        if (u < 0 || to < 0 || to >= list.length) return;
                        [list[u], list[to]] = [list[to], list[u]];
                        this.commit();
                        this.say('Row moved ' + (by < 0 ? 'up' : 'down') + '. Save to keep it.', region);
                        this.touch();
                    },

                    toggleRowOf(region, unit) {
                        unit.enabled = !unit.enabled;
                        this.say('Row ' + (unit.enabled ? 'shown' : 'hidden') + '. Save to keep it.', region);
                        this.touch();
                    },

                    /** Removing a row removes what is in it, and says how much. */
                    removeRowOf(region, unit) {
                        const u = this.rowIndex(region, unit);
                        if (u < 0) return;
                        const n = unit.cols.flat().length;
                        if (unit.cols.flat().includes(this.editing)) this.closeInspector();
                        if (this.editingRow === unit) this.editingRow = null;
                        if (this.rowTools === unit) this.rowTools = null;
                        this.rows[region].splice(u, 1);
                        this.commit();
                        this.say('Row removed from ' + this.regionLabel(region)
                            + (n ? ' with ' + n + (n === 1 ? ' widget' : ' widgets') : '') + '. Save to keep it.', region);
                        this.touch();
                    },

                    /* ---- adding widgets ---- */

                    /** What fits this position on this page type. */
                    offerable(region) {
                        if (!region) return [];
                        return this.widgets.filter((w) => w.regions.includes(region)
                            && (!w.pages || w.pages.includes(this.pageType)));
                    },

                    isAdding(region, u, c) {
                        return !!this.adding && this.adding.region === region && this.adding.u === u && this.adding.c === c;
                    },

                    /**
                     * Opens in place: at the end of the position (u null), or
                     * in one column of a split row. Focus goes to the first
                     * widget and comes back on close.
                     */
                    openPicker(region, el, u = null, c = null) {
                        if (this.isAdding(region, u, c)) { this.closePicker(); return; }
                        this.adding = { region, u, c };
                        this.addingFrom = el;
                        this.$nextTick(() => {
                            root?.querySelector('.v-pick .v-pickcard')?.focus();
                        });
                    },

                    closePicker(refocus = true) {
                        const back = this.addingFrom;
                        this.adding = null;
                        this.addingFrom = null;
                        if (refocus) back?.focus();
                    },

                    /**
                     * One step: the widget lands as its own line (a new
                     * one-column row) or in the chosen column, and when it
                     * needs a choice its settings open with that field focused.
                     */
                    add(w) {
                        const { region, u, c } = this.adding;
                        const card = keyed({
                            id: null, widget: w.key, title: null,
                            label: w.title, enabled: true, available: true, config: {},
                        });
                        let unit;
                        if (u === null) {
                            if (!this.rows[region]) this.rows[region] = [];
                            unit = wrap({ columns: this.fullShape(), cols: [[card]] });
                            unit.cols[0][0] = card;
                            this.rows[region].push(unit);
                        } else {
                            unit = this.rows[region]?.[u];
                            if (!unit) return;
                            unit.cols[c].push(card);
                        }
                        const side = region === 'column_left' ? 'left' : (region === 'column_right' ? 'right' : null);
                        const opened = side && this.cols[side] === 0;
                        if (opened) this.setWidth(side, 2);
                        this.commit();
                        this.closePicker(false);
                        this.touch();
                        this.say(w.title + ' added to ' + this.regionLabel(region)
                            + (opened ? ', which is now on for this page' : '') + '. Save to keep it.', region);
                        if (this.firstNeeded(w.key)) {
                            this.edit(card, region, unit);
                        } else {
                            this.$nextTick(() => this.lineOf(card)?.querySelector('.v-line__edit')?.focus());
                        }
                    },

                    /**
                     * The field a new widget cannot do without: one its schema
                     * marks required, else an image, a list or a text it has
                     * no default for.
                     */
                    firstNeeded(widget) {
                        const schema = this.schemaFor(widget);
                        const marked = schema.find((f) => f.required);
                        if (marked) return marked.name;
                        const open = schema.find((f) => ['image', 'images', 'rows', 'textarea'].includes(f.type)
                            && (f.default === undefined || f.default === null || f.default === ''));
                        return open ? open.name : null;
                    },

                    /* ---- settings ---- */

                    edit(row, region, unit) {
                        this.editingRow = null;
                        this.editing = row;
                        this.editingAt = { region, unit };
                        this.inspectRowTools = false;
                        this.$nextTick(() => {
                            const panel = root?.querySelector('.v-lay__inspector');
                            if (!panel) return;
                            const target = panel.querySelector('[data-needed] input, [data-needed] select, [data-needed] textarea, [data-needed] button')
                                ?? panel.querySelector('input, select, textarea');
                            target?.focus({ preventScroll: true });
                            panel.scrollIntoView({ block: 'nearest' });
                        });
                    },

                    closeInspector() {
                        const row = this.editing;
                        this.editing = null;
                        this.editingAt = null;
                        this.inspectRowTools = false;
                        // Back to the line that opened it.
                        this.$nextTick(() => this.lineOf(row)?.querySelector('.v-line__edit')?.focus());
                    },

                    lineOf(card) {
                        return card?.key ? root?.querySelector('.v-line[data-key="' + card.key + '"]') : null;
                    },

                    schemaFor(widget) { return widget ? (this.schemas[widget] || []) : []; },

                    /**
                     * Falls back to the schema's declared default, so a freshly
                     * added Product grid does not show Show and How many blank.
                     */
                    cfg(name) {
                        const set = this.editing?.config ?? {};
                        if (set[name] !== undefined && set[name] !== '') return set[name];
                        const field = this.schemaFor(this.editing?.widget).find((f) => f.name === name);
                        return field?.default ?? '';
                    },

                    setCfg(name, value) {
                        if (!this.editing.config) this.editing.config = {};
                        this.editing.config[name] = value;
                        this.touch();
                    },

                    /* ---- media and rows fields ---- */

                    mediaTarget: null,

                    mediaList(f) {
                        const v = this.cfg(f.name);
                        if (f.type === 'images') return Array.isArray(v) ? v : [];
                        return v ? [v] : [];
                    },

                    pickMedia(f, el) {
                        this.mediaTarget = f;
                        const picker = root?.querySelector(f.type === 'image' ? '[data-media-one]' : '[data-media-many]');
                        picker?.dispatchEvent(new CustomEvent('media:open', { detail: { from: el }, bubbles: false }));
                    },

                    /** The browser handed over paths; they land in the field that asked. */
                    heardMedia(detail) {
                        const f = this.mediaTarget;
                        if (!f || !this.editing) return;
                        const paths = (detail?.paths ?? (detail?.path ? [detail.path] : [])).filter(Boolean);
                        if (!paths.length) return;
                        if (f.type === 'images') {
                            const have = this.mediaList(f);
                            this.setCfg(f.name, [...have, ...paths.filter((p) => !have.includes(p))]);
                        } else {
                            this.setCfg(f.name, paths[paths.length - 1]);
                        }
                    },

                    dropMedia(f, i) {
                        if (f.type === 'images') {
                            const list = this.mediaList(f).slice();
                            list.splice(i, 1);
                            this.setCfg(f.name, list);
                        } else {
                            this.setCfg(f.name, '');
                        }
                    },

                    rowsOf(f) {
                        const v = this.cfg(f.name);
                        return Array.isArray(v) ? v : [];
                    },

                    addRowField(f) {
                        const blank = {};
                        Object.keys(f.fields || {}).forEach((k) => { blank[k] = ''; });
                        this.setCfg(f.name, [...this.rowsOf(f), blank]);
                    },

                    setRowField(f, i, name, value) {
                        const list = this.rowsOf(f).map((r) => ({ ...r }));
                        if (!list[i]) return;
                        list[i][name] = value;
                        this.setCfg(f.name, list);
                    },

                    moveRowField(f, i, by) {
                        const list = this.rowsOf(f).slice();
                        const to = i + by;
                        if (to < 0 || to >= list.length) return;
                        [list[i], list[to]] = [list[to], list[i]];
                        this.setCfg(f.name, list);
                    },

                    dropRowField(f, i) {
                        const list = this.rowsOf(f).slice();
                        list.splice(i, 1);
                        this.setCfg(f.name, list);
                    },

                    /* ---- page parts ---- */

                    partsIn(part) {
                        return this.pageSettings.filter((f) => (f.part ?? null) === part);
                    },

                    /** Everything the page draws itself, in the order the theme lists it. */
                    bodyParts() {
                        return this.pageSettings.filter((f) => (f.part ?? null) === 'body' || (f.part ?? null) === null);
                    },

                    partOn(part) {
                        return this.partsIn(part).every((f) => f.value);
                    },

                    setPart(f, value) {
                        f.value = value;
                        this.say(f.label + (value ? ' shown' : ' hidden') + ' on this page. Save to keep it.');
                        this.touch();
                    },

                    /* ---- the subject panel ---- */

                    subjectOf(row) {
                        const field = this.schemaFor(row?.widget).find((f) => Array.isArray(f.browse));
                        if (!field) return null;
                        const value = row?.config?.[field.name] ?? field.default ?? '';
                        return {
                            field,
                            browse_label: field.browse_label,
                            chosen: field.browse.find((c) => c.value === value) || null,
                        };
                    },

                    openSubject(row) {
                        const subject = this.subjectOf(row);
                        if (!subject) return;
                        this.panel = { row, field: subject.field };
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    closeSubject() {
                        if (!this.panel) return;
                        this.panel = null;
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    panelValue() {
                        const name = this.panel?.field?.name;
                        if (!name) return '';
                        return this.panel.row?.config?.[name] ?? this.panel.field.default ?? '';
                    },

                    chooseSubject(choice) {
                        const name = this.panel?.field?.name;
                        if (!name || choice.value === this.panelValue()) return;
                        if (!this.panel.row.config) this.panel.row.config = {};
                        this.panel.row.config[name] = choice.value;
                        this.commit();
                        this.say(choice.label + ' placed. Save to keep it.', this.locate(this.panel.row)?.region);
                        this.touch();
                    },

                    ownerOf(row) {
                        const spec = this.widgets.find((w) => w.key === row?.widget);
                        return spec?.module || (row?.label ?? '');
                    },

                    enterSubject(choice) {
                        if (!choice.url) return;
                        this.frameTitle = choice.label;
                        this.frameSteps = 0;
                        this.frameUrl = choice.url;
                    },

                    panelBack() {
                        if (this.frameSteps > 1) {
                            this.frameSteps -= 1;
                            this.frameGoingBack = true;
                            setTimeout(() => { this.frameGoingBack = false; }, 2000);
                            this.$refs.frame?.contentWindow?.postMessage(
                                { v: 'venta-frame-host', type: 'back' }, window.location.origin
                            );
                            return;
                        }
                        this.frameUrl = null;
                        this.frameTitle = '';
                        this.frameSteps = 0;
                    },

                    hearFrame(e) {
                        if (e.origin !== window.location.origin) return;
                        if (!e.data || e.data.v !== 'venta-frame') return;
                        if (!this.panel) return;
                        if (e.data.type === 'ready') {
                            this.frameTitle = e.data.title || '';
                            if (this.frameGoingBack) {
                                this.frameGoingBack = false;
                            } else {
                                this.frameSteps += 1;
                            }
                        }
                        if (e.data.type === 'saved') {
                            this.refreshChoices().then(() => this.resyncPanel());
                            if (e.data.message) this.say(e.data.message);
                        }
                    },

                    resyncPanel() {
                        if (!this.panel) return;
                        const fresh = this.schemaFor(this.panel.row?.widget)
                            .find((f) => f.name === this.panel.field.name);
                        if (fresh) this.panel = { row: this.panel.row, field: fresh };
                    },

                    /* ---- lines ---- */

                    /** Where a widget is: { region, u, c, i }, or null. */
                    locate(card) {
                        for (const [region, units] of Object.entries(this.rows)) {
                            for (let u = 0; u < units.length; u++) {
                                for (let c = 0; c < units[u].cols.length; c++) {
                                    const i = units[u].cols[c].indexOf(card);
                                    if (i >= 0) return { region, u, c, i };
                                }
                            }
                        }
                        return null;
                    },

                    /**
                     * Removing the last widget of a one-column row removes the
                     * row too: it has no chrome, so an empty one would be an
                     * invisible leftover.
                     */
                    remove(region, u, c, i) {
                        const unit = this.rows[region][u];
                        const col = unit.cols[c];
                        const row = col[i];
                        if (this.editing === row) this.closeInspector();
                        col.splice(i, 1);
                        if (unit.columns.length === 1 && col.length === 0) {
                            if (this.editingRow === unit) this.editingRow = null;
                            this.rows[region].splice(u, 1);
                        }
                        this.commit();
                        this.say(this.lineName(row) + ' removed from ' + this.regionLabel(region) + '. Save to keep it.', region);
                        this.touch();
                    },

                    toggle(row, region) {
                        row.enabled = !row.enabled;
                        this.say(this.lineName(row) + (row.enabled ? ' shown' : ' hidden') + '. Save to keep it.', region);
                        this.touch();
                    },

                    /* ---- moving ---- */

                    start(region, u, c, i) { this.dragging = { region, u, c, i }; },

                    /** The dragged card, lifted out of where it was. */
                    lift() {
                        const d = this.dragging;
                        this.dragging = null;
                        if (!d) return null;
                        const col = this.rows[d.region]?.[d.u]?.cols?.[d.c];
                        if (!col) return null;
                        return { card: col[d.i], from: d, col };
                    },

                    fits(card, region) {
                        const spec = this.widgets.find((w) => w.key === card.widget);
                        if (spec && !spec.regions.includes(region)) {
                            this.say((card.title || card.label) + ' cannot go in ' + this.regionLabel(region));
                            return false;
                        }
                        return true;
                    },

                    /** Drop onto a column, or onto a card in it (before that card). */
                    drop(region, u, c, at = null) {
                        const d = this.dragging;
                        if (!d) return;
                        const card = this.rows[d.region]?.[d.u]?.cols?.[d.c]?.[d.i];
                        if (!card || !this.fits(card, region)) { this.dragging = null; return; }

                        const lifted = this.lift();
                        lifted.col.splice(d.i, 1);

                        const target = this.rows[region][u].cols[c];
                        let index = at === null ? target.length : at;
                        // Dropping onto a later card in the same column: the
                        // removal above shifted it up by one.
                        if (target === lifted.col && at !== null && at > d.i) index -= 1;
                        target.splice(index, 0, card);
                        this.commit();
                        this.touch();
                    },

                    /** Drop on the position itself, outside any row: a new row at the end. */
                    dropIntoRegion(region) {
                        const d = this.dragging;
                        if (!d) return;
                        const card = this.rows[d.region]?.[d.u]?.cols?.[d.c]?.[d.i];
                        if (!card || !this.fits(card, region)) { this.dragging = null; return; }

                        const lifted = this.lift();
                        lifted.col.splice(d.i, 1);
                        if (!this.rows[region]) this.rows[region] = [];
                        this.rows[region].push(wrap({ columns: [8], cols: [[card]] }));
                        this.commit();
                        this.touch();
                    },

                    /**
                     * Move up and Move down. Inside a column a widget swaps
                     * with its neighbour; at the edge of a one-column row the
                     * whole row moves, which is how lines that each sit in a
                     * row of their own read as one list. A split row keeps its
                     * widgets in their column; the row moves from its own panel.
                     */
                    canMove(region, u, c, i, by) {
                        const unit = this.rows[region]?.[u];
                        if (!unit) return false;
                        const col = unit.cols[c];
                        if (i + by >= 0 && i + by < col.length) return true;
                        if (unit.columns.length > 1) return false;
                        const to = u + by;
                        return to >= 0 && to < this.rows[region].length;
                    },

                    lineMove(region, u, c, i, by) {
                        if (!this.canMove(region, u, c, i, by)) return;
                        const list = this.rows[region];
                        const col = list[u].cols[c];
                        const card = col[i];
                        if (i + by >= 0 && i + by < col.length) {
                            [col[i], col[i + by]] = [col[i + by], col[i]];
                        } else {
                            [list[u], list[u + by]] = [list[u + by], list[u]];
                        }
                        this.commit();
                        this.touch();
                        this.say(this.lineName(card) + ' moved ' + (by < 0 ? 'up' : 'down') + '. Save to keep it.', region);
                        // Keep focus on the same button of the line that moved.
                        this.$nextTick(() => {
                            const btns = this.lineOf(card)?.querySelectorAll('.v-iconbtn');
                            const want = btns?.[by < 0 ? 0 : 1];
                            (want && !want.disabled ? want : btns?.[by < 0 ? 1 : 0])?.focus();
                        });
                    },

                    /** Where else this widget is allowed to go. */
                    movesFor(widget) {
                        if (!widget) return [];
                        const spec = this.widgets.find((w) => w.key === widget);
                        const allowed = spec ? spec.regions : Object.keys(this.regions);
                        return allowed.filter((r) => this.regions[r]);
                    },

                    /** Into its own line at the end of the target position. */
                    moveCard(card, target) {
                        const at = this.locate(card);
                        if (!at || at.region === target) return;
                        const unit = this.rows[at.region][at.u];
                        unit.cols[at.c].splice(at.i, 1);
                        if (unit.columns.length === 1 && unit.cols[0].length === 0) this.rows[at.region].splice(at.u, 1);
                        if (!this.rows[target]) this.rows[target] = [];
                        const fresh = wrap({ columns: this.fullShape(), cols: [[]] });
                        fresh.cols[0].push(card);
                        this.rows[target].push(fresh);
                        const side = target === 'column_left' ? 'left' : (target === 'column_right' ? 'right' : null);
                        if (side && this.cols[side] === 0) this.setWidth(side, 2);
                        if (this.editing === card) this.editingAt = { region: target, unit: fresh };
                        this.commit();
                        this.say(this.lineName(card) + ' moved to ' + this.regionLabel(target) + '. Save to keep it.', target);
                        this.touch();
                    },

                    /**
                     * Bring a stranded placement back into a position that exists.
                     * Only offered for the page being edited; an orphan on another
                     * page is listed so it can be found, not silently moved.
                     */
                    rescue(orphan) {
                        if (orphan.page_type !== this.pageType) {
                            window.location = '{{ route('admin.layout', $themeQuery) }}{{ $themeQuery === [] ? '?' : '&' }}page=' + encodeURIComponent(orphan.page_type);
                            return;
                        }
                        if (!this.rows.content_bottom) this.rows.content_bottom = [];
                        this.rows.content_bottom.push(wrap({ columns: this.fullShape(), cols: [[{
                            id: orphan.id, widget: orphan.widget, title: orphan.title,
                            label: orphan.title || orphan.widget, enabled: true, available: true, config: {},
                        }]] }));
                        this.orphans = this.orphans.filter((o) => o.id !== orphan.id);
                        this.commit();
                        this.say((orphan.title || orphan.widget) + ' moved to ' + this.regionLabel('content_bottom') + '. Save to keep it.', 'content_bottom');
                        this.touch();
                    },

                    async refreshChoices() {
                        try {
                            const res = await fetch(this.schemasUrl, { headers: { Accept: 'application/json' } });
                            if (res.ok) this.schemas = await res.json();
                        } catch (e) { /* the merchant loses nothing if this fails */ }
                    },

                    /**
                     * Announced once to a screen reader from the save bar, and
                     * shown beside the position it happened in.
                     */
                    say(message, region = null) {
                        this.announcement = message;
                        this.saidIn = region;
                        clearTimeout(this.sayTimer);
                        this.sayTimer = setTimeout(() => { this.announcement = ''; this.saidIn = null; }, 5000);
                    },

                    /**
                     * How many things differ from the page as loaded: each
                     * widget added, removed, moved or edited, each row changed,
                     * each page part switched, the side column widths.
                     */
                    changeCount() {
                        if (!this.baseline) return 0;
                        const now = JSON.parse(this.payload());
                        // Widgets are counted when added, removed or changed;
                        // a row only when it existed before and changed, since
                        // a new or removed row is already counted by its widgets.
                        const sig = (data) => {
                            const out = {};
                            let fresh = 0;
                            const seen = {};
                            data.rows.forEach((row) => {
                                const pos = (seen[row.region] = (seen[row.region] ?? -1) + 1);
                                const { placements, id, ...rest } = row;
                                if (id) out['row:' + id] = JSON.stringify({ ...rest, pos });
                                placements.forEach((p, k) => {
                                    out['w:' + (p.id ?? 'n' + (fresh++))] = JSON.stringify({ ...p, region: row.region, row: id ?? null, k });
                                });
                            });
                            Object.entries(data.settings || {}).forEach(([k, v]) => { out['s:' + k] = JSON.stringify(v); });
                            out.cols = JSON.stringify(data.columns);
                            return out;
                        };
                        const a = sig(this.baseline);
                        const b = sig(now);
                        const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
                        let n = 0;
                        keys.forEach((k) => {
                            if (k.startsWith('row:') && (!(k in a) || !(k in b))) return;
                            if (a[k] !== b[k]) n++;
                        });
                        return n;
                    },

                    saveState() {
                        if (this.saving) return 'Saving\u2026';
                        if (!this.dirty) return 'No unsaved changes';
                        const n = this.changeCount();
                        return n > 0 ? n + (n === 1 ? ' unsaved change' : ' unsaved changes') : 'Unsaved changes';
                    },

                    /* ---- the page rails ---- */

                    dropLabel(side) {
                        const region = side === 'left' ? 'column_left' : 'column_right';
                        const n = this.cardsIn(region).length;
                        return 'Turn off ' + this.regionLabel(region) + (n === 0 ? '' : ' (' + n + ' inside)');
                    },

                    /**
                     * Turning a side column off moves its rows to Content bottom and
                     * says so, rather than hiding them and saving them anyway.
                     */
                    dropColumn(side) {
                        const region = side === 'left' ? 'column_left' : 'column_right';
                        const stranded = this.rowsIn(region);
                        const n = this.cardsIn(region).length;
                        if (stranded.length) {
                            if (!this.rows.content_bottom) this.rows.content_bottom = [];
                            this.rows.content_bottom.push(...stranded);
                            this.rows[region] = [];
                            this.say(n + (n === 1 ? ' widget' : ' widgets') + ' moved to ' + this.regionLabel('content_bottom') + '. Save to keep it.', 'content_bottom');
                        }
                        this.cols[side] = 0;
                        this.commit();
                        this.touch();
                    },

                    grab(side, e) {
                        e.preventDefault();
                        const row = e.target.closest('.v-lay__middle');
                        const move = (ev) => {
                            const box = row.getBoundingClientRect();
                            const frac = (ev.clientX - box.left) / box.width;
                            let n = Math.round(frac * this.grid);
                            if (side === 'right') n = this.grid - n;
                            this.setWidth(side, n);
                        };
                        const up = () => {
                            window.removeEventListener('pointermove', move);
                            window.removeEventListener('pointerup', up);
                            this.touch();
                        };
                        window.addEventListener('pointermove', move);
                        window.addEventListener('pointerup', up);
                    },

                    nudge(side, by) { this.setWidth(side, this.cols[side] + by); this.touch(); },

                    /** The content column is never allowed to disappear. */
                    setWidth(side, n) {
                        const other = side === 'left' ? this.cols.right : this.cols.left;
                        const max = this.grid - this.minContent - other;
                        this.cols[side] = Math.max(0, Math.min(n, max));
                    },

                    touch() {
                        (root ?? this.$root).dispatchEvent(new Event('change', { bubbles: true }));
                    },

                    payload() {
                        const rows = [];
                        Object.entries(this.rows).forEach(([region, units]) => {
                            units.forEach((unit) => {
                                const placements = [];
                                unit.cols.forEach((col, c) => {
                                    col.forEach((p) => placements.push({
                                        id: p.id, column: c, widget: p.widget,
                                        title: p.title, enabled: p.enabled, config: (p.config && typeof p.config === 'object') ? Object.assign({}, p.config) : {},
                                        style: p.style ?? null,
                                    }));
                                });
                                rows.push({
                                    id: unit.id, region, columns: unit.columns, enabled: unit.enabled,
                                    style: unit.style, responsive: unit.responsive, placements,
                                });
                            });
                        });
                        const settings = {};
                        this.pageSettings.forEach((f) => { settings[f.name] = f.value; });

                        return JSON.stringify({
                            page_type: this.pageType,
                            columns: this.cols,
                            settings,
                            rows,
                        });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
