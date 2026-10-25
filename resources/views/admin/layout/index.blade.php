<x-admin.shell title="Layout">
    @push('head')
        @vite('resources/css/admin/layout-editor.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            {{-- The trail still reads through Themes (owner: keep the entry
                 there for now), but the page is core: the links only appear
                 while the theme editor is enabled to provide them. --}}
            <div class="v-head__trail">
                @if (count($installedThemes) > 1)
                    <span>Content</span>
                    <span aria-hidden="true">/</span>
                    <span>{{ $theme }}</span>
                    <span aria-hidden="true">/</span>
                @else
                    <span>{{ $theme }}</span>
                    <span aria-hidden="true">/</span>
                @endif
                <span>Layout</span>
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
            {{-- The screen changes what every shopper sees and had no path to
                 the storefront it edits. --}}
            <a href="{{ $sampleUrl }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    <x-admin.form :action="route('admin.layout.update', $themeQuery)">
      <div class="v-lay" :class="{ 'is-inspecting': editing || editingRow }" x-data="layoutEditor(window.__layout)">
        <input type="hidden" name="payload" :value="payload()">

        {{-- On a phone the 12-link rail filled the whole first viewport before
             the editor was reachable, so there it collapses to one control. --}}
        <label class="v-lay__pagepick">
            <span>Page</span>
            <select onchange="window.location = this.value">
                <option value="{{ route('admin.layout', ['page' => '*'] + $themeQuery) }}" @selected($pageType === '*')>All pages</option>
                @foreach ($pageTypes as $vKey => $vLabel)
                    <option value="{{ route('admin.layout', ['page' => $vKey] + $themeQuery) }}" @selected($pageType === $vKey)>{{ $vLabel }}</option>
                @endforeach
            </select>
        </label>

        {{-- which page --}}
        <nav class="v-lay__rail" aria-label="Pages">
            <div class="v-lay__raillbl">Everywhere</div>
            <a href="{{ route('admin.layout', ['page' => '*'] + $themeQuery) }}"
               class="{{ $pageType === '*' ? 'is-on' : '' }}">
                <span>All pages</span>
                @if (($counts['*'] ?? 0) > 0)<span class="n">{{ $counts['*'] }}</span>@endif
            </a>

            <div class="v-lay__raillbl">Pages</div>
            @foreach ($pageTypes as $key => $label)
                <a href="{{ route('admin.layout', ['page' => $key] + $themeQuery) }}"
                   class="{{ $pageType === $key ? 'is-on' : '' }}">
                    <span>{{ $label }}</span>
                    @if (($counts[$key] ?? 0) > 0)<span class="n">{{ $counts[$key] }}</span>@endif
                </a>
            @endforeach
        </nav>

        {{-- the page, drawn as a page --}}
        <div class="v-lay__stage">
            {{-- Inside the stage, not beside it.

                 It was a direct child of the .v-lay grid carrying
                 grid-column: 1 / -1, so the moment it appeared it claimed a
                 whole row, and the auto-placed stage below it fell into the
                 rail's 176px column: the entire editor collapsed to a
                 narrow strip on every announcement. Latent while the live
                 region only spoke for moves and rescues, and immediate once
                 adding a widget started announcing too. It belongs in here
                 anyway, because everything it reports happened in here. --}}
            <p class="v-lay__said" role="status" aria-live="polite" x-text="announcement" x-show="announcement" x-cloak></p>

            {{-- Placements in positions this theme no longer has. Kept, never
                 deleted, and now actually shown: they were computed since the
                 layout system landed and no view ever drew them, so a theme
                 update that dropped a region hid the work in silence. --}}
            <div class="v-lay__orphans" x-show="orphans.length" x-cloak>
                <p>
                    <x-admin.icon name="warning" />
                    <span x-text="orphans.length + (orphans.length === 1 ? ' widget sits' : ' widgets sit') + ' in a position this theme does not have. They render nothing until you move them.'"></span>
                </p>
                <template x-for="o in orphans" :key="o.id">
                    <div class="v-lay__orphan">
                        <span x-text="o.title || o.widget"></span>
                        <span class="mono" x-text="o.page + ' / ' + o.region"></span>
                        <button type="button" @click="rescue(o)">Move to Below the content</button>
                    </div>
                </template>
            </div>

            <div class="v-lay__page">
                {{-- top --}}
                @if (isset($regions['header_top']))
                    <div class="v-lay__strip">
                        <x-admin.layout-slot region="header_top" label="Above the header" />
                    </div>
                @endif

                @if (isset($regions['header']))
                    {{-- A theme whose header is rows: the merchant arranges it here. --}}
                    <div class="v-lay__strip v-lay__strip--system">
                        <x-admin.layout-slot region="header" :label="$regions['header']['label'] ?? 'Header'" />
                    </div>
                @else
                    <div class="v-lay__chrome" :class="{ 'is-off': !partOn('header') }">
                        <span class="v-lay__logo"></span>
                        <span class="v-lay__line"></span>
                        <span>header</span>
                        <template x-for="f in partsIn('header')" :key="f.name">
                            <x-admin.part-switch />
                        </template>
                    </div>
                @endif

                {{-- the row --}}
                <div class="v-lay__middle">
                    @if (isset($regions['column_left']))
                        {{-- The right column had a stub and the left never did, so a
                             filter rail, the commonest sidebar in commerce, could not
                             be made from this screen at any width. --}}
                        <template x-if="cols.left === 0">
                            <button type="button" class="v-lay__stub" @click="addColumn('left')"
                                    title="Add a left column" aria-label="Add a left column">+</button>
                        </template>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__col v-lay__col--side" :style="'width:' + (cols.left / grid * 100) + '%'">
                                <x-admin.layout-slot region="column_left" label="Left column" side="true" />
                            </div>
                        </template>
                        <template x-if="cols.left > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Left column width"
                                 :aria-valuenow="cols.left" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical"
                                 @pointerdown="grab('left', $event)"
                                 @keydown.arrow-left.prevent="nudge('left', -1)"
                                 @keydown.arrow-right.prevent="nudge('left', 1)"></div>
                        </template>
                    @endif

                    <div class="v-lay__col v-lay__col--center">
                        @if (isset($regions['content_top']))
                            <x-admin.layout-slot region="content_top" label="Above the content" />
                        @endif

                        {{-- The parts of the hardcoded page this theme lets a
                             merchant switch off, drawn where they actually sit.
                             The control on the thing it governs answers "on this
                             page" by standing on the page, and switching one off
                             dims the part rather than changing nothing anyone
                             can see. --}}
                        @php
                            // A theme may declare this page's own pieces as a
                            // region (Plaza's product page): it is arranged
                            // here, in place of the fixed page body.
                            $vOwnRegion = collect($regions)->filter(function ($spec, $key) use ($pageType) {
                                return ! empty($spec['defaults']) && ($spec['page'] ?? '*') === $pageType && $pageType !== '*';
                            })->keys()->first();
                        @endphp
                        @if ($vOwnRegion)
                            <div class="v-lay__pagebody v-lay__pagebody--region">
                                <x-admin.layout-slot :region="$vOwnRegion" :label="$regions[$vOwnRegion]['label'] ?? 'The page itself'" />
                            </div>
                        @else
                            <div class="v-lay__pagebody">
                                <template x-for="f in partsIn('body')" :key="f.name">
                                    <div class="v-lay__part" :class="{ 'is-off': !f.value }">
                                        <span class="v-lay__partbar" aria-hidden="true"></span>
                                        <x-admin.part-switch />
                                    </div>
                                </template>
                                <b>The page itself</b>
                            </div>
                        @endif

                        @if (isset($regions['content_bottom']))
                            <x-admin.layout-slot region="content_bottom" label="Below the content" />
                        @endif
                    </div>

                    @if (isset($regions['column_right']))
                        <template x-if="cols.right > 0">
                            <div class="v-lay__handle" role="separator" tabindex="0"
                                 aria-label="Right column width"
                                 :aria-valuenow="cols.right" aria-valuemin="0" :aria-valuemax="grid - minContent"
                                 aria-orientation="vertical"
                                 @pointerdown="grab('right', $event)"
                                 @keydown.arrow-left.prevent="nudge('right', 1)"
                                 @keydown.arrow-right.prevent="nudge('right', -1)"></div>
                        </template>
                        <template x-if="cols.right > 0">
                            <div class="v-lay__col v-lay__col--side" :style="'width:' + (cols.right / grid * 100) + '%'">
                                <x-admin.layout-slot region="column_right" label="Right column" side="true" />
                            </div>
                        </template>
                        <template x-if="cols.right === 0">
                            <button type="button" class="v-lay__stub" @click="cols.right = 2; touch()"
                                    title="Add a right column" aria-label="Add a right column">+</button>
                        </template>
                    @endif
                </div>

                <div class="v-lay__chrome" :class="{ 'is-off': !partOn('footer') }">
                    <span class="v-lay__line"></span>
                    <span>footer</span>
                    <template x-for="f in partsIn('footer')" :key="f.name">
                        <x-admin.part-switch />
                    </template>
                </div>

                @if (isset($regions['footer_top']))
                    <div class="v-lay__strip">
                        <x-admin.layout-slot region="footer_top" label="Above the footer" />
                    </div>
                @endif

                <div class="v-lay__ruler">
                    <span>Columns</span>
                    <span class="mono" x-text="cols.left + ' / ' + (grid - cols.left - cols.right) + ' / ' + cols.right"></span>
                    <span class="v-lay__sp"></span>
                    <button type="button" class="v-lay__drop" x-show="cols.left > 0" @click="dropColumn('left')"
                            :aria-label="dropLabel('left')" x-text="dropLabel('left')"></button>
                    <button type="button" class="v-lay__drop" x-show="cols.right > 0" @click="dropColumn('right')"
                            :aria-label="dropLabel('right')" x-text="dropLabel('right')"></button>
                </div>
            </div>
        </div>

        {{-- settings for the selected widget, from its own schema --}}
        <div class="v-lay__inspector" x-show="editing" x-cloak
             @keydown.escape.window="editing = null">
            <div class="v-lay__pickhead">
                <b x-text="editing ? (editing.title || editing.label) : ''"></b>
                <button type="button" @click="editing = null" aria-label="Close">&times;</button>
            </div>

            <label class="v-lay__field">
                <span>Heading shown on the page</span>
                <input type="text" :value="editing?.title ?? ''"
                       @input="editing.title = $event.target.value; touch()">
            </label>

            <template x-for="f in schemaFor(editing?.widget)" :key="f.name">
                <label class="v-lay__field">
                    <span x-text="f.label"></span>

                    <template x-if="f.type === 'select'">
                        <span class="v-lay__withnew">
                            {{-- :selected on the option, not :value on the select.
                                 A select's value cannot bind before x-for has
                                 added its options, so the saved choice showed as
                                 "Choose..." on every open. --}}
                            <select @change="setCfg(f.name, $event.target.value)">
                                <option value="">Choose&hellip;</option>
                                <template x-for="(label, value) in f.options" :key="value">
                                    <option :value="value" :selected="cfg(f.name) === value" x-text="label"></option>
                                </template>
                            </select>
                            {{-- Creating the thing you are placing, without leaving --}}
                            <a x-show="f.create_url" :href="f.create_url" target="_blank"
                               class="v-lay__new" x-text="f.create_label ?? 'New'"></a>
                        </span>
                    </template>

                    <template x-if="f.type === 'number'">
                        <input type="number" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                    </template>

                    <template x-if="f.type === 'textarea'">
                        <textarea :rows="f.code ? 10 : 4" :class="{ 'v-lay__code': f.code }" :spellcheck="f.code ? 'false' : 'true'"
                                  :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)"></textarea>
                    </template>

                    {{-- One image, or several: chosen through the page's one
                         media browser, aimed at this field. --}}
                    <template x-if="f.type === 'image' || f.type === 'images'">
                        <span class="v-lay__media">
                            <template x-for="(path, pi) in mediaList(f)" :key="path + pi">
                                <span class="v-lay__mediaitem">
                                    <img :src="'/storage/' + path" alt="">
                                    <button type="button" @click="dropMedia(f, pi)" aria-label="Remove image">&times;</button>
                                </span>
                            </template>
                            <button type="button" class="v-lay__new" @click="pickMedia(f, $el)"
                                    x-text="f.type === 'images' ? 'Add images' : (cfg(f.name) ? 'Change image' : 'Choose image')"></button>
                        </span>
                    </template>

                    {{-- Repeating rows of small fields: tabs, list items, sections. --}}
                    <template x-if="f.type === 'rows'">
                        <span class="v-lay__rows">
                            <template x-for="(row, ri) in rowsOf(f)" :key="ri">
                                <span class="v-lay__rowsitem">
                                    <template x-for="(sub, subName) in f.fields" :key="subName">
                                        <label class="v-lay__subfield">
                                            <span x-text="sub.label"></span>
                                            <template x-if="sub.type === 'select'">
                                                <select @change="setRowField(f, ri, subName, $event.target.value)">
                                                    <option value="">Choose&hellip;</option>
                                                    <template x-for="(label, value) in sub.options" :key="value">
                                                        <option :value="value" :selected="row[subName] === value" x-text="label"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="sub.type === 'textarea'">
                                                <textarea rows="3" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)"></textarea>
                                            </template>
                                            <template x-if="sub.type === 'number'">
                                                <input type="number" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)">
                                            </template>
                                            <template x-if="sub.type !== 'select' && sub.type !== 'textarea' && sub.type !== 'number'">
                                                <input type="text" :value="row[subName] ?? ''" @input="setRowField(f, ri, subName, $event.target.value)">
                                            </template>
                                        </label>
                                    </template>
                                    <span class="v-lay__rowsacts">
                                        <button type="button" @click="moveRowField(f, ri, -1)" :disabled="ri === 0" aria-label="Move up">&uarr;</button>
                                        <button type="button" @click="moveRowField(f, ri, 1)" :disabled="ri === rowsOf(f).length - 1" aria-label="Move down">&darr;</button>
                                        <button type="button" @click="dropRowField(f, ri)" aria-label="Remove">&times;</button>
                                    </span>
                                </span>
                            </template>
                            <button type="button" class="v-lay__new" @click="addRowField(f)">Add</button>
                        </span>
                    </template>

                    <template x-if="!['select', 'number', 'textarea', 'image', 'images', 'rows'].includes(f.type)">
                        <input type="text" :value="cfg(f.name)" @input="setCfg(f.name, $event.target.value)">
                    </template>
                </label>
            </template>

            {{-- The one media browser for every image field on this page,
                 aimed at whichever field asked. --}}
            <x-admin.media-picker name="widget_media" mode="multi" root="products" bare trigger="Choose"
                class="v-lay__mediapicker" @media:picked="heardMedia($event.detail)" />

            <p class="v-lay__pickempty" x-show="schemaFor(editing?.widget).length === 0">
                Nothing to configure
            </p>
        </div>

        {{-- The row's own style, from a closed set of choices. Colours come
             from the theme's palette or a hex; the storefront paints the row
             from custom properties, the rules live in one shared stylesheet. --}}
        <div class="v-lay__inspector v-lay__inspector--row" x-show="editingRow" x-cloak
             @keydown.escape.window="editingRow = null">
            <div class="v-lay__pickhead">
                <b>Row style</b>
                <button type="button" @click="editingRow = null" aria-label="Close">&times;</button>
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
                        <button type="button" class="v-lay__presetx" @click="deletePreset(p)" :aria-label="'Delete preset ' + p.name">&times;</button>
                    </span>
                </template>
            </div>

            <label class="v-lay__field">
                <span>Background</span>
                <select :value="rs('bg')" @change="setRowStyle('bg', $event.target.value)">
                    <option value="none">None</option>
                    <option value="color">Colour</option>
                    <option value="gradient">Gradient</option>
                    <option value="image">Image</option>
                </select>
            </label>

            <template x-if="rs('bg') === 'color' || rs('bg') === 'gradient'">
                <div class="v-lay__field">
                    <span x-text="rs('bg') === 'gradient' ? 'From' : 'Colour'"></span>
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
                <span>Text colour</span>
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
                        <span>Border colour</span>
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

        {{-- Parts this theme declares but does not say where to draw. They
             have nowhere to stand on the stage, so they stand under it. A
             theme that names every part's home never renders this at all. --}}
        <fieldset class="v-lay__page-settings" x-show="partsIn(null).length" x-cloak>
            <legend>Elsewhere on this page</legend>
            <template x-for="f in partsIn(null)" :key="f.name">
                <x-admin.part-switch />
            </template>
        </fieldset>

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
                            @click="panelBack()" aria-label="Back">&larr;</button>

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
                            aria-label="Close" data-v-dialog-initial>&times;</button>
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
                copyUrl: @js($copyUrl),
                csrf: @js(csrf_token()),
                widgets: @js($widgets),
                schemas: @js($schemas),
                pageSettings: @js($pageSettings),
                orphans: @js($orphans),
                schemasUrl: @js(route('admin.layout.schemas', $themeQuery)),
                cols: @js($columns),
                grid: @js($grid),
                minContent: @js($minContent),
            };

            function layoutEditor(seed) {
                // The form element, held in a closure. Inside an x-for scope
                // $el is the control that fired, so touch() dispatched change
                // on the checkbox that called it and re-entered itself:
                // "Maximum call stack size exceeded" on every page-setting click.
                let root = null;
                let nextKey = 1;

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
                    cols: (unit.cols || [[]]).map((c) => c.slice()),
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
                    // Area templates: which position's menu is open, the name
                    // being typed, and the saved arrangements.
                    tools: null,
                    templateName: '',
                    templates: seed.templates,
                    templatesUrl: seed.templatesUrl,
                    exportUrl: seed.exportUrl,
                    importUrl: seed.importUrl,
                    copyUrl: seed.copyUrl,
                    csrf: seed.csrf,
                    // The plus that opened the picker, so Escape can hand
                    // focus back to it rather than dropping it on the body.
                    addingFrom: null,
                    editing: null,
                    // The open subject panel: { row, field }. frameUrl null
                    // means level one, the choices; anything else is the
                    // module's own page inside the frame.
                    panel: null,
                    frameUrl: null,
                    frameTitle: '',
                    frameSteps: 0,
                    frameGoingBack: false,
                    dragging: null,
                    moving: null,
                    announcement: '',
                    sayTimer: null,

                    init() {
                        root = this.$root ?? this.$el;
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

                    /** Rows a page inherits from All pages. Shown, never editable here. */
                    inheritedIn(region) {
                        return (this.inherited[region] || []);
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
                            this.say('Template ' + body.name + ' saved.');
                        } catch (e) {
                            this.say(e.message || 'The template could not be saved.');
                        }
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
                        this.say(t.name + (how === 'replace' ? ' replaced ' : ' added to ') + this.regionLabel(region) + '. Save to keep it.');
                        this.touch();
                    },

                    async deleteTemplate(id) {
                        const t = this.templates.find((x) => String(x.id) === String(id));
                        if (!t) return;
                        if (String(t.id).startsWith('theme:')) { this.say(t.name + ' ships with the theme and stays.'); return; }
                        try {
                            const res = await fetch(this.templatesUrl + '/' + t.id, { method: 'DELETE', headers: this.headers(false) });
                            if (!res.ok) throw new Error('refused');
                            this.templates = this.templates.filter((x) => x.id !== t.id);
                            this.say('Template ' + t.name + ' deleted.');
                        } catch (e) {
                            this.say('The template could not be deleted.');
                        }
                    },

                    async copyRegion(region, target) {
                        if (!target) return;
                        try {
                            const res = await fetch(this.copyUrl, { method: 'POST', headers: this.headers(),
                                body: JSON.stringify({ region, target_page: target, rows: this.regionRows(region) }) });
                            const body = await res.json();
                            if (!res.ok) throw new Error(body.message || 'refused');
                            this.say(body.message);
                        } catch (e) {
                            this.say(e.message || 'Nothing was copied.');
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
                            this.say(body.rows.length + (body.rows.length === 1 ? ' row' : ' rows') + ' imported into ' + this.regionLabel(region) + '. Save to keep it.');
                            this.touch();
                        } catch (e) {
                            this.say(e.message || 'The file could not be read.');
                        }
                    },

                    /* ---- row style ---- */

                    editRow(region, u) {
                        this.editing = null;
                        this.editingRow = this.rows[region][u];
                        if (!this.editingRow.style) this.editingRow.style = { ...this.styleDefaults };
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

                    /** Is this row painted at all? Drawn as a mark on its head. */
                    styled(unit) {
                        const s = unit.style;
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

                    addRow(region, el) {
                        if (!this.rows[region]) this.rows[region] = [];
                        this.rows[region].push(wrap({ columns: [8], cols: [[]] }));
                        this.commit();
                        this.say('Row added to ' + this.regionLabel(region) + '. Save to keep it.');
                        this.touch();
                        this.$nextTick(() => {
                            const rows = root?.querySelectorAll('.v-slot .v-lrow');
                            const last = el?.closest('.v-slot')?.querySelectorAll('.v-lrow:not(.v-lrow--inherit)');
                            last?.[last.length - 1]?.querySelector('.v-lrowcol__add')?.focus();
                        });
                    },

                    /**
                     * Changing the shape keeps every card: columns that vanish
                     * hand their cards to the last column that remains, in
                     * order, rather than dropping them off the page.
                     */
                    setShape(region, u, shape) {
                        const unit = this.rows[region][u];
                        const cols = shape.map((_, i) => unit.cols[i] ? unit.cols[i].slice() : []);
                        if (unit.cols.length > shape.length) {
                            const spill = unit.cols.slice(shape.length).flat();
                            cols[shape.length - 1].push(...spill);
                        }
                        unit.columns = shape.slice();
                        unit.cols = cols;
                        this.commit();
                        this.say('Row set to ' + shape.join(' + ') + '. Save to keep it.');
                        this.touch();
                    },

                    nudgeRow(region, u, by) {
                        const list = this.rows[region];
                        const to = u + by;
                        if (!list || to < 0 || to >= list.length) return;
                        [list[u], list[to]] = [list[to], list[u]];
                        this.commit();
                        this.say('Row moved ' + (by < 0 ? 'up' : 'down'));
                        this.touch();
                    },

                    toggleRow(region, u) {
                        const unit = this.rows[region][u];
                        unit.enabled = !unit.enabled;
                        this.say('Row ' + (unit.enabled ? 'shown' : 'hidden') + '. Save to keep it.');
                        this.touch();
                    },

                    /** Removing a row removes what is in it, and says how much. */
                    removeRow(region, u) {
                        const unit = this.rows[region][u];
                        const n = unit.cols.flat().length;
                        if (unit.cols.flat().includes(this.editing)) this.editing = null;
                        if (this.editingRow === unit) this.editingRow = null;
                        this.rows[region].splice(u, 1);
                        this.commit();
                        this.say('Row removed from ' + this.regionLabel(region)
                            + (n ? ' with ' + n + (n === 1 ? ' widget' : ' widgets') : '') + '. Save to keep it.');
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
                     * Opening in place, and moving focus with it: focus in on
                     * open, focus back on close.
                     */
                    openPicker(region, u, c, el) {
                        this.adding = { region, u, c };
                        this.addingFrom = el;
                        this.$nextTick(() => {
                            root?.querySelector('.v-slot__pick .v-pickcard')?.focus();
                        });
                    },

                    closePicker() {
                        const back = this.addingFrom;
                        this.adding = null;
                        this.addingFrom = null;
                        back?.focus();
                    },

                    add(w) {
                        const { region, u, c } = this.adding;
                        const unit = this.rows[region]?.[u];
                        if (!unit) return;
                        unit.cols[c].push({
                            id: null, widget: w.key, title: w.title,
                            label: w.title, enabled: true, available: true, config: {},
                        });
                        this.commit();
                        this.say(w.title + ' added to ' + this.regionLabel(region) + '. Save to keep it.');
                        this.closePicker();
                        this.touch();
                    },

                    /* ---- settings ---- */

                    edit(row) { this.editing = row; },

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
                        const picker = root?.querySelector('.v-lay__mediapicker');
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
                        this.say(choice.label + ' placed. Save to keep it.');
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

                    /* ---- cards ---- */

                    remove(region, u, c, i) {
                        const col = this.rows[region][u].cols[c];
                        const row = col[i];
                        if (this.editing === row) this.editing = null;
                        col.splice(i, 1);
                        this.commit();
                        this.say((row.title || row.label) + ' removed from ' + this.regionLabel(region) + '. Save to keep it.');
                        this.touch();
                    },

                    toggle(row) {
                        row.enabled = !row.enabled;
                        this.say((row.title || row.label) + (row.enabled ? ' shown' : ' hidden') + '. Save to keep it.');
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

                    /** Reorder inside a column with buttons, so touch and keyboard both work. */
                    nudgeCard(region, u, c, i, by) {
                        const list = this.rows[region][u].cols[c];
                        const to = i + by;
                        if (!list || to < 0 || to >= list.length) return;
                        [list[i], list[to]] = [list[to], list[i]];
                        this.commit();
                        this.say((list[to].title || list[to].label) + ' moved ' + (by < 0 ? 'up' : 'down'));
                        this.touch();
                    },

                    /** Where else this widget is allowed to go. */
                    movesFor(widget) {
                        const spec = this.widgets.find((w) => w.key === widget);
                        const allowed = spec ? spec.regions : Object.keys(this.regions);
                        return allowed.filter((r) => this.regionOpen(r));
                    },

                    regionOpen(region) {
                        if (region === 'column_left') return this.cols.left > 0;
                        if (region === 'column_right') return this.cols.right > 0;
                        return true;
                    },

                    /** Into a new full-width row at the end of the target position. */
                    moveTo(region, u, c, i, target) {
                        if (region === target) { this.moving = null; return; }
                        const col = this.rows[region][u].cols[c];
                        const card = col[i];
                        col.splice(i, 1);
                        if (!this.rows[target]) this.rows[target] = [];
                        this.rows[target].push(wrap({ columns: [8], cols: [[card]] }));
                        this.commit();
                        this.say((card.title || card.label) + ' moved to ' + this.regionLabel(target));
                        this.moving = null;
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
                        this.rows.content_bottom.push(wrap({ columns: [8], cols: [[{
                            id: orphan.id, widget: orphan.widget, title: orphan.title,
                            label: orphan.title || orphan.widget, enabled: true, available: true, config: {},
                        }]] }));
                        this.orphans = this.orphans.filter((o) => o.id !== orphan.id);
                        this.commit();
                        this.say((orphan.title || orphan.widget) + ' moved to Below the content');
                        this.touch();
                    },

                    async refreshChoices() {
                        try {
                            const res = await fetch(this.schemasUrl, { headers: { Accept: 'application/json' } });
                            if (res.ok) this.schemas = await res.json();
                        } catch (e) { /* the merchant loses nothing if this fails */ }
                    },

                    /** Announced to a screen reader, and shown to everyone else. */
                    say(message) {
                        this.announcement = message;
                        clearTimeout(this.sayTimer);
                        this.sayTimer = setTimeout(() => { this.announcement = ''; }, 4000);
                    },

                    /* ---- the page rails ---- */

                    addColumn(side) { this.setWidth(side, 2); this.touch(); },

                    dropLabel(side) {
                        const n = this.cardsIn(side === 'left' ? 'column_left' : 'column_right').length;
                        return n === 0
                            ? 'Remove ' + side + ' column'
                            : 'Remove ' + side + ' column (' + n + ' inside)';
                    },

                    /**
                     * Removing a rail moves its rows to Below the content and
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
                            this.say(n + (n === 1 ? ' widget' : ' widgets') + ' moved to Below the content');
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
