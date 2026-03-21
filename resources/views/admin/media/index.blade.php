<x-admin-layout>
    <x-slot name="header">Media Manager</x-slot>

    <div x-data="mediaLibrary()" x-init="init()" class="flex gap-6" style="min-height: 70vh;">
        {{-- Sidebar: Store Picker + Folders --}}
        <div class="w-64 flex-shrink-0 space-y-4">

            {{-- Folders --}}
            <div x-show="storeId" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-gray-500 uppercase">Folders</span>
                    <button @click="showNewFolder = true" class="text-indigo-600 hover:text-indigo-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>

                {{-- New Folder Input --}}
                <div x-show="showNewFolder" x-transition class="mb-3">
                    <form @submit.prevent="createFolder()">
                        <input type="text" x-model="newFolderName" placeholder="Folder name" x-ref="newFolderInput"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 mb-2">
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-1 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700">Create</button>
                            <button type="button" @click="showNewFolder = false; newFolderName = ''" class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded-lg hover:bg-gray-300">Cancel</button>
                        </div>
                    </form>
                </div>

                {{-- Folder List --}}
                <nav class="space-y-0.5">
                    <button @click="selectFolder(null, 'all')"
                        :class="activeView === 'all' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            All Images
                        </span>
                    </button>
                    <button @click="selectFolder(null, 'unsorted')"
                        :class="activeView === 'unsorted' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                        class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            Unsorted
                        </span>
                    </button>

                    <template x-for="folder in folders" :key="folder.id">
                        <div class="group relative">
                            <button @click="selectFolder(folder.id, 'folder')"
                                :class="activeFolderId === folder.id ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
                                class="w-full flex items-center justify-between px-3 py-2 text-sm rounded-lg transition-colors">
                                <span class="flex items-center truncate">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" :class="folder.is_auto ? 'text-blue-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                    <span x-text="folder.name" class="truncate"></span>
                                </span>
                                <span x-text="folder.media_count" class="text-xs text-gray-400 ml-2 flex-shrink-0"></span>
                            </button>
                            <div class="absolute right-1 top-1 hidden group-hover:flex gap-1">
                                <button @click.stop="startRenameFolder(folder)" class="p-1 text-gray-400 hover:text-gray-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button @click.stop="deleteFolder(folder)" class="p-1 text-gray-400 hover:text-red-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </nav>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex-1">
            {{-- Top Bar --}}
            <div x-show="storeId" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-1">
                        <input type="text" x-model="searchQuery" @input.debounce.400ms="loadMedia(1)" placeholder="Search images..."
                            class="w-64 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <select x-model="sortBy" @change="loadMedia(1)"
                            class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="name">By Name</option>
                            <option value="size">By Size</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="selectedIds.length > 0">
                            <button @click="bulkDelete()" class="px-3 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition-colors">
                                Delete (<span x-text="selectedIds.length"></span>)
                            </button>
                        </template>
                        <label class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors cursor-pointer">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Upload
                            <input type="file" multiple accept="image/*" @change="uploadFiles($event)" class="sr-only">
                        </label>
                    </div>
                </div>

                {{-- Upload Progress --}}
                <div x-show="uploading" x-transition class="mt-3">
                    <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
                        <span>Uploading...</span>
                        <span x-text="uploadProgress + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                    </div>
                </div>
            </div>

            {{-- Drop Zone + Image Grid --}}
            <div x-show="storeId"
                 @dragover.prevent="dragover = true"
                 @dragleave.prevent="dragover = false"
                 @drop.prevent="dragover = false; handleDrop($event)"
                 :class="dragover ? 'ring-2 ring-indigo-500 ring-offset-2' : ''"
                 class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 transition-all min-h-96">

                {{-- Loading --}}
                <div x-show="loading" class="flex items-center justify-center py-20">
                    <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>

                {{-- Empty State --}}
                <div x-show="!loading && mediaItems.length === 0" class="text-center py-20 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p>No images found. Upload some or drag and drop here.</p>
                </div>

                {{-- Image Grid --}}
                <div x-show="!loading && mediaItems.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <template x-for="item in mediaItems" :key="item.id">
                        <div class="group relative border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                            {{-- Checkbox --}}
                            <label class="absolute top-2 left-2 z-10">
                                <input type="checkbox" :value="item.id" x-model="selectedIds"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shadow-sm">
                            </label>

                            {{-- In Use Badge --}}
                            <span x-show="item.is_used" class="absolute top-2 right-2 z-10 px-1.5 py-0.5 bg-green-500 text-white text-xs rounded-full">In use</span>

                            {{-- Thumbnail --}}
                            <div class="aspect-square bg-gray-100">
                                <img :src="item.thumbnail_url" :alt="item.original_filename" class="w-full h-full object-cover" loading="lazy">
                            </div>

                            {{-- Info --}}
                            <div class="p-2">
                                <p class="text-xs text-gray-700 truncate" x-text="item.original_filename"></p>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-400" x-text="item.file_size"></span>
                                    <span x-show="item.dimensions" class="text-xs text-gray-400" x-text="item.dimensions"></span>
                                </div>
                            </div>

                            {{-- Delete Button --}}
                            <button x-show="!item.is_used" @click="deleteMedia(item)"
                                class="absolute bottom-10 right-2 hidden group-hover:block p-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Pagination --}}
                <div x-show="!loading && meta.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                    <span class="text-sm text-gray-500">
                        Page <span x-text="meta.current_page"></span> of <span x-text="meta.last_page"></span>
                        (<span x-text="meta.total"></span> images)
                    </span>
                    <div class="flex gap-2">
                        <button @click="loadMedia(meta.current_page - 1)" :disabled="meta.current_page <= 1"
                            :class="meta.current_page <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-300'"
                            class="px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-lg transition-colors">Prev</button>
                        <button @click="loadMedia(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page"
                            :class="meta.current_page >= meta.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-300'"
                            class="px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-lg transition-colors">Next</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rename Folder Modal --}}
        <div x-show="renamingFolder" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" @click.self="renamingFolder = null">
            <div class="bg-white rounded-lg shadow-xl p-6 w-96">
                <h3 class="text-lg font-semibold mb-4">Rename Folder</h3>
                <form @submit.prevent="renameFolder()">
                    <input type="text" x-model="renameFolderName"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 mb-4">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="renamingFolder = null" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Rename</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function mediaLibrary() {
            return {
                storeId: '1',
                folders: [],
                mediaItems: [],
                meta: {},
                loading: false,
                uploading: false,
                uploadProgress: 0,
                dragover: false,
                searchQuery: '',
                sortBy: 'newest',
                activeView: 'all',
                activeFolderId: null,
                selectedIds: [],
                showNewFolder: false,
                newFolderName: '',
                renamingFolder: null,
                renameFolderName: '',

                init() {
                    window.addEventListener('beforeunload', () => {
                        navigator.sendBeacon(
                            '{{ route("admin.media.cleanup-temp") }}',
                            new URLSearchParams({ store_id: this.storeId, _token: '{{ csrf_token() }}' })
                        );
                    });
                    this.onStoreChange();
                },

                async onStoreChange() {
                    if (!this.storeId) return;
                    this.activeView = 'all';
                    this.activeFolderId = null;
                    this.selectedIds = [];
                    await Promise.all([this.loadFolders(), this.loadMedia(1)]);
                },

                selectFolder(folderId, view) {
                    this.activeFolderId = folderId;
                    this.activeView = view;
                    this.selectedIds = [];
                    this.loadMedia(1);
                },

                async loadFolders() {
                    const res = await fetch(`{{ route('admin.media.folders') }}?store_id=${this.storeId}`);
                    this.folders = await res.json();
                },

                async loadMedia(page) {
                    this.loading = true;
                    const params = new URLSearchParams({
                        store_id: this.storeId,
                        sort: this.sortBy,
                        page: page,
                    });

                    if (this.searchQuery) params.set('search', this.searchQuery);
                    if (this.activeView === 'folder' && this.activeFolderId) params.set('folder_id', this.activeFolderId);
                    if (this.activeView === 'unsorted') params.set('unsorted', '1');

                    const res = await fetch(`{{ route('admin.media.list') }}?${params}`);
                    const data = await res.json();
                    this.mediaItems = data.data;
                    this.meta = data.meta;
                    this.loading = false;
                },

                async createFolder() {
                    if (!this.newFolderName.trim()) return;

                    const res = await fetch('{{ route("admin.media.folders.store") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ store_id: this.storeId, name: this.newFolderName }),
                    });

                    if (res.ok) {
                        this.newFolderName = '';
                        this.showNewFolder = false;
                        await this.loadFolders();
                    }
                },

                startRenameFolder(folder) {
                    this.renamingFolder = folder;
                    this.renameFolderName = folder.name;
                },

                async renameFolder() {
                    if (!this.renameFolderName.trim() || !this.renamingFolder) return;

                    const url = `{{ url('admin/media/folders') }}/${this.renamingFolder.id}`;
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ name: this.renameFolderName }),
                    });

                    if (res.ok) {
                        this.renamingFolder = null;
                        await this.loadFolders();
                    }
                },

                async deleteFolder(folder) {
                    if (!confirm(`Delete folder "${folder.name}" and all its images?`)) return;

                    const url = `{{ url('admin/media/folders') }}/${folder.id}`;
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    });

                    if (res.ok) {
                        if (this.activeFolderId === folder.id) {
                            this.activeView = 'all';
                            this.activeFolderId = null;
                        }
                        await Promise.all([this.loadFolders(), this.loadMedia(1)]);
                    } else {
                        const data = await res.json();
                        alert(data.message);
                    }
                },

                async uploadFiles(event) {
                    const files = event.target.files;
                    if (!files.length) return;
                    await this.doUpload(files);
                    event.target.value = '';
                },

                async handleDrop(event) {
                    const files = event.dataTransfer.files;
                    if (!files.length) return;
                    await this.doUpload(files);
                },

                async doUpload(files) {
                    this.uploading = true;
                    this.uploadProgress = 0;

                    const formData = new FormData();
                    formData.append('store_id', this.storeId);
                    if (this.activeFolderId) formData.append('folder_id', this.activeFolderId);

                    for (let i = 0; i < Math.min(files.length, 20); i++) {
                        formData.append('files[]', files[i]);
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    });

                    xhr.onload = async () => {
                        this.uploading = false;
                        this.uploadProgress = 0;
                        if (xhr.status === 201) {
                            await Promise.all([this.loadFolders(), this.loadMedia(1)]);
                        } else {
                            alert('Upload failed. Max 3MB per image, max 20 files.');
                        }
                    };

                    xhr.onerror = () => {
                        this.uploading = false;
                        alert('Upload failed.');
                    };

                    xhr.open('POST', '{{ route("admin.media.upload") }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                    xhr.send(formData);
                },

                async deleteMedia(item) {
                    if (!confirm(`Delete "${item.original_filename}"?`)) return;

                    const url = `{{ url('admin/media') }}/${item.id}`;
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    });

                    if (res.ok) {
                        await Promise.all([this.loadFolders(), this.loadMedia(this.meta.current_page)]);
                    } else {
                        const data = await res.json();
                        alert(data.message);
                    }
                },

                async bulkDelete() {
                    if (!confirm(`Delete ${this.selectedIds.length} selected images?`)) return;

                    for (const id of this.selectedIds) {
                        await fetch(`{{ url('admin/media') }}/${id}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        });
                    }

                    this.selectedIds = [];
                    await Promise.all([this.loadFolders(), this.loadMedia(1)]);
                },
            }
        }
    </script>
    @endpush
</x-admin-layout>
