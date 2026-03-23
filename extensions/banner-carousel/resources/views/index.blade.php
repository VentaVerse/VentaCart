<x-admin-layout>
    <x-slot name="header">Banner Carousel</x-slot>

    <div x-data="bannerManager()" class="space-y-6">

        {{-- Banner List --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Slides</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Drag to reorder. First active slide is shown first.</p>
                </div>
                <button type="button" @click="showForm = true; editing = null; resetForm()"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    + Add Slide
                </button>
            </div>

            @if ($banners->isEmpty())
                <div class="text-center py-16 text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm font-medium text-gray-500">No banners yet</p>
                    <p class="text-xs mt-1">Click "+ Add Slide" to create your first banner</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($banners as $banner)
                        <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 group">
                            {{-- Thumbnail --}}
                            <div class="w-32 h-16 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 shrink-0">
                                @if ($banner->image)
                                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $banner->title ?: 'Untitled' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $banner->subtitle ?: 'No subtitle' }}</p>
                                @if ($banner->button_text)
                                    <p class="text-xs text-indigo-500 mt-0.5">{{ $banner->button_text }} → {{ $banner->button_url ?: '#' }}</p>
                                @endif
                            </div>

                            {{-- Schedule --}}
                            <div class="text-xs text-gray-400 shrink-0 w-28 text-center">
                                @if ($banner->starts_at || $banner->ends_at)
                                    @if ($banner->starts_at)
                                        <span>{{ $banner->starts_at->format('M d') }}</span>
                                    @endif
                                    <span>—</span>
                                    @if ($banner->ends_at)
                                        <span>{{ $banner->ends_at->format('M d') }}</span>
                                    @else
                                        <span>∞</span>
                                    @endif
                                @else
                                    Always
                                @endif
                            </div>

                            {{-- Status --}}
                            <div class="shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $banner->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $banner->is_active ? 'Active' : 'Draft' }}
                                </span>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button"
                                    @click="editBanner({{ $banner->toJson() }})"
                                    class="text-xs text-indigo-600 hover:text-indigo-900">Edit</button>
                                <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="inline" onsubmit="return confirm('Delete this banner?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Add/Edit Form Modal --}}
        <div x-show="showForm" x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);"
             @keydown.escape.window="showForm = false">

            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden max-h-[90vh] overflow-y-auto"
                 @click.outside="showForm = false">

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-900" x-text="editing ? 'Edit Slide' : 'Add Slide'"></h3>
                    <button type="button" @click="showForm = false" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form :action="editing ? '{{ url('admin/banners') }}/' + editing.id : '{{ route('admin.banners.store') }}'"
                      method="POST" class="p-6 space-y-4">
                    @csrf
                    <template x-if="editing">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    {{-- Image --}}
                    <div x-data="{ imgPath: form.image || '' }" x-effect="form.image = imgPath">
                        @include('admin.partials.single_image_picker', [
                            'fieldName' => 'image',
                            'currentImage' => null,
                            'label' => 'Banner Image',
                            'hint' => 'Recommended: 1920x600px',
                            'required' => true,
                        ])
                    </div>

                    {{-- Mobile Image --}}
                    <div x-data="{ imgPath: form.image_mobile || '' }" x-effect="form.image_mobile = imgPath">
                        @include('admin.partials.single_image_picker', [
                            'fieldName' => 'image_mobile',
                            'currentImage' => null,
                            'label' => 'Mobile Image (optional)',
                            'hint' => 'Shown on phone screens. Recommended: 768x600px',
                        ])
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" x-model="form.title" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. New Arrivals">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subtitle</label>
                            <input type="text" name="subtitle" x-model="form.subtitle" class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. Check out our latest gear">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button Text</label>
                            <input type="text" name="button_text" x-model="form.button_text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="SHOP NOW">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Button URL</label>
                            <input type="text" name="button_url" x-model="form.button_url" class="w-full rounded-lg border-gray-300 text-sm" placeholder="/category/effects-pedals">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="hidden" name="button_new_tab" value="0">
                                <input type="checkbox" name="button_new_tab" value="1" x-model="form.button_new_tab" class="rounded border-gray-300 text-indigo-600">
                                New tab
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Text Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="text_color" x-model="form.text_color" class="w-10 h-8 rounded border-gray-300 cursor-pointer">
                                <span class="text-xs text-gray-500 font-mono" x-text="form.text_color"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" x-model="form.sort_order" min="0" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-gray-300 text-indigo-600">
                                Active
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Starts At <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                            <input type="date" name="starts_at" x-model="form.starts_at" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ends At <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                            <input type="date" name="ends_at" x-model="form.ends_at" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button type="button" @click="showForm = false" class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700" x-text="editing ? 'Update Slide' : 'Create Slide'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function bannerManager() {
        return {
            showForm: false,
            editing: null,
            form: {
                title: '', subtitle: '', button_text: '', button_url: '',
                button_new_tab: false, text_color: '#FFFFFF', sort_order: 0,
                is_active: true, starts_at: '', ends_at: '',
            },

            resetForm() {
                this.form = {
                    title: '', subtitle: '', button_text: '', button_url: '',
                    button_new_tab: false, text_color: '#FFFFFF', sort_order: 0,
                    is_active: true, starts_at: '', ends_at: '',
                };
            },

            editBanner(banner) {
                this.editing = banner;
                this.form = {
                    title: banner.title || '',
                    subtitle: banner.subtitle || '',
                    button_text: banner.button_text || '',
                    button_url: banner.button_url || '',
                    button_new_tab: !!banner.button_new_tab,
                    text_color: banner.text_color || '#FFFFFF',
                    sort_order: banner.sort_order || 0,
                    is_active: !!banner.is_active,
                    starts_at: banner.starts_at ? banner.starts_at.substring(0, 10) : '',
                    ends_at: banner.ends_at ? banner.ends_at.substring(0, 10) : '',
                };
                this.showForm = true;
            },
        };
    }
    </script>
    @endpush
</x-admin-layout>
