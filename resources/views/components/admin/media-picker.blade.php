@props([
    'name' => 'image',
    'value' => null,
    'root' => 'products',
    'mode' => 'single',
    // What a multi selection is FOR. 'add' hands the paths to the caller,
    // which is what a picker does. 'manage' deletes them, which is what the
    // library page does and what it could not do until now: it rendered an
    // Add button that fired into a handler doing nothing.
    'commit' => 'add',
    'can' => 'upload,url,delete',
    'label' => null,
    'trigger' => 'Browse',
    'help' => null,
    'required' => false,
    'bare' => false,
])
@php
    // Multi carries a JSON array in one hidden input, single carries a path.
    $vSeed = $mode === 'multi'
        ? (is_string($value) ? (json_decode($value, true) ?: []) : (array) ($value ?? []))
        : (string) ($value ?? '');
@endphp
{{-- The one image browser.

     Five hand-rolled copies of this existed, all calling the product image
     endpoints, all diverging: delete in two of them, sort in one, multi-select
     in one, three labels for the root crumb, six wordings for the empty state,
     and four that swallowed browse errors so a session expiry showed
     "Loading..." forever. Everything a caller used to decide for itself is
     decided here now; a caller says what it points at and what it may do. --}}
<div class="v-mpick {{ $bare ? 'v-mpick--bare' : '' }}"
     x-data="vMediaPicker({
        root: @js($root),
        mode: @js($mode),
        can: @js($can),
        commit: @js($commit),
        value: @js($vSeed),
        endpoints: {
            browse: @js(route('admin.media.browse')),
            upload: @js(route('admin.media.upload')),
            importUrl: @js(route('admin.media.import-url')),
            delete: @js(route('admin.media.delete')),
            folder: @js(route('admin.media.folder')),
            token: @js(csrf_token()),
        },
     })"
     @keydown.escape.window="escape()"
     {{-- Opened from elsewhere on the page by dispatching media:open at this
          element, with the control that asked as detail.from. That is how the
          variant matrix aims one panel at one row without owning a browser. --}}
     data-media-picker="{{ $name }}"
     @media:open="show($event.detail?.from)"
     {{ $attributes }}>

    @unless ($bare)
        @if ($label)
            <span class="v-field__label">
                {{ $label }}
                @if ($required)<span class="v-req">Required</span>@endif
                @if ($help)<x-admin.help :text="$help" />@endif
            </span>
        @endif

        <input type="hidden" name="{{ $name }}" :value="payload">

        {{-- What is chosen, and the way to change or drop it. --}}
        <div class="v-mpick__chosen" x-show="chosen.length" x-cloak>
            <template x-for="path in chosen" :key="path">
                <span class="v-mpick__one">
                    <img :src="path" x-effect="$el.src = '/storage/' + path.split('/').map(encodeURIComponent).join('/')" alt="" aria-hidden="true">
                    <button type="button" class="v-mpick__drop"
                            @click="mode === 'multi' ? drop(path) : clear()"
                            :aria-label="'Remove ' + path.split('/').pop()">&times;</button>
                </span>
            </template>
        </div>

        <span class="v-actrow">
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="show($el)">{{ $trigger }}</button>
            <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="chosen.length && mode === 'single'" x-cloak
                    @click="clear()">Remove</button>
        </span>
    @endunless

    {{-- The panel. One in the document per picker, and a picker is one
         component, so a nine-variant matrix has one of these and not nine. --}}
    <div class="v-overlay" x-show="open" x-cloak @click.self="hide()">
        <div class="v-modal v-modal--media" role="dialog" aria-modal="true"
             :aria-label="'Choose an image from ' + root" x-ref="panel"
             x-dialog="open">

            <div class="v-mpick__head">
                <nav class="v-crumbrow" aria-label="Folder path">
                    <button type="button" class="v-link" @click="browse(root, 1)" x-text="root"></button>
                    <template x-for="crumb in crumbs.slice(1)" :key="crumb.path">
                        <span class="v-crumbrow">
                            <span aria-hidden="true">/</span>
                            <button type="button" class="v-link" @click="browse(crumb.path, 1)" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <button type="button" class="v-row__x" @click="hide()" aria-label="Close the image browser">
                    <x-admin.icon name="x" />
                </button>
            </div>

            <div class="v-mpick__bar">
                <input type="search" class="v-control v-control--sm" x-model="q"
                       placeholder="Search this library" aria-label="Search this library">

                <select class="v-control v-control--sm" x-model="sort" @change="resort(sort)" aria-label="Sort by">
                    <option value="modified">Newest</option>
                    <option value="name">Name</option>
                </select>

                {{-- What is actually on screen. It read "30 files" above a
                     grid of forty-two folders, because the count only ever
                     counted files and the folders were the thing you were
                     looking at. --}}
                <span class="v-mpick__count mono" x-text="tally"></span>

                <span class="v-mpick__acts">
                    <template x-if="able('folder')">
                        <span class="v-actrow">
                            <input type="text" class="v-control v-control--sm" x-model="newFolder"
                                   placeholder="New folder" aria-label="New folder name"
                                   @keydown.enter.prevent="makeFolder()">
                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="makeFolder()"
                                    :disabled="!newFolder.trim()">Create</button>
                        </span>
                    </template>

                    <template x-if="able('url')">
                        <span class="v-actrow" x-data="{ url: '' }">
                            <input type="url" class="v-control v-control--sm" x-model="url"
                                   placeholder="Paste an image URL" aria-label="Image URL"
                                   @keydown.enter.prevent="importUrl(url); url = ''">
                            <button type="button" class="v-btn v-btn--ghost v-btn--sm"
                                    @click="importUrl(url); url = ''" :disabled="!url.trim()">Fetch</button>
                        </span>
                    </template>

                    <template x-if="able('upload')">
                        <label class="v-btn v-btn--secondary v-btn--sm">
                            <span x-text="busy || 'Upload here'"></span>
                            <input type="file" accept="image/*" multiple class="v-sr" @change="upload($event)">
                        </label>
                    </template>
                </span>
            </div>

            <div class="v-mpick__grid" @dragover.prevent @drop.prevent="able('upload') && upload($event)">
                <template x-if="parent !== null && !searching">
                    <button type="button" class="v-mediatile v-mediatile--up" @click="browse(parent, 1)" aria-label="Up one folder">
                        <x-admin.icon name="folder" class="v-mediatile__folder" />
                        <span class="v-mediatile__name">..</span>
                    </button>
                </template>

                <template x-for="folder in folders" :key="folder.path">
                    <button type="button" class="v-mediatile" @click="browse(folder.path, 1)">
                        <x-admin.icon name="folder" class="v-mediatile__folder" />
                        <span class="v-mediatile__name" x-text="folder.name"></span>
                    </button>
                </template>

                <template x-for="file in files" :key="file.path">
                    <span class="v-tilewrap">
                        {{-- State is never carried by an outline alone: the mark
                             is a shape as well as a colour. --}}
                        <button type="button" class="v-mediatile"
                                :class="{ 'is-added': isChosen(file), 'is-picked': isPicked(file) }"
                                @click="choose(file)"
                                :aria-pressed="(isChosen(file) || isPicked(file)) ? 'true' : 'false'">
                            <span class="v-mediatile__frame">
                                {{-- Not draggable. There is no reordering in the
                                     library and no moving between folders, so a
                                     thumbnail you can pick up offers a rearrange
                                     that does not exist. It also competes with
                                     the grid's real drag, which is dropping
                                     files IN from the desktop to upload. --}}
                                <img :src="file.thumb" :alt="file.name" draggable="false"
                                     loading="lazy" decoding="async">
                            </span>
                            <span class="v-mediatile__name" x-text="file.name"></span>
                            <span class="v-tilemark" x-show="isChosen(file)" aria-hidden="true">&check;</span>
                            <span class="v-tilemark v-tilemark--pick" x-show="isPicked(file) && !isChosen(file)" aria-hidden="true">+</span>
                        </button>

                        <span class="v-tileacts" x-show="able('delete')">
                            <button type="button" class="v-row__x" @click.stop="askDelete(file)"
                                    :aria-label="'Delete ' + file.name">
                                <x-admin.icon name="trash" />
                            </button>
                        </span>

                        {{-- What it would cost, before anyone reaches for delete. --}}
                        <span class="v-tileused mono" x-show="file.usedBy > 0" x-cloak
                              :title="file.usedBy + ' products use this'"
                              x-text="'in ' + file.usedBy"></span>
                    </span>
                </template>
            </div>

            <p class="v-mpick__note" x-show="loading">Loading&hellip;</p>
            <p class="v-mpick__note" x-show="!loading && folders.length === 0 && files.length === 0"
               x-text="searching ? 'Nothing here matches ' + q : 'Empty folder'"></p>

            <div class="v-mpick__foot">
                <span class="v-pager__pages" x-show="lastPage > 1" x-cloak>
                    <button type="button" class="v-pager__n" @click="browse(path, page - 1)" :disabled="page === 1">&larr;</button>
                    <span class="mono" x-text="page + ' of ' + lastPage"></span>
                    <button type="button" class="v-pager__n" @click="browse(path, page + 1)" :disabled="page === lastPage">&rarr;</button>
                </span>

                <span class="v-mpick__footacts" x-show="mode === 'multi'" x-cloak>
                    <span class="v-cell-quiet" x-text="picked.length + ' selected'"></span>
                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="picked = []"
                            :disabled="!picked.length">Clear</button>

                    <template x-if="commitAction === 'add'">
                        <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="take()"
                                :disabled="!picked.length" x-text="'Add ' + picked.length"></button>
                    </template>

                    <template x-if="commitAction === 'manage'">
                        <button type="button" class="v-btn v-btn--danger-fill v-btn--sm" @click="askDeleteMany()"
                                :disabled="!picked.length" x-text="'Delete ' + picked.length"></button>
                    </template>
                </span>
            </div>

            {{-- Nested inside the panel so a click outside the confirm is not
                 read as a click outside the browser. --}}
            <div class="v-overlay" x-show="confirming" x-cloak @click.self="confirming = null">
                <div class="v-modal" role="alertdialog" aria-modal="true" aria-label="Delete files">
                    <h2 class="v-modal__title" x-text="confirmTitle"></h2>
                    <p x-text="confirmNames"></p>
                    <p class="v-cell-quiet" x-text="deleteWarning"></p>
                    <div class="v-modal__foot">
                        <button type="button" class="v-btn v-btn--ghost" @click="confirming = null">Cancel</button>
                        <button type="button" class="v-btn v-btn--danger-fill" @click="confirmDelete()">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
