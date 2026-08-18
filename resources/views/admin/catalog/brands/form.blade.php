<x-admin3.shell :title="$brand ? 'Edit Brand' : 'New Brand'" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.brands.index') }}">Brands</a>
                <span aria-hidden="true">/</span>
                <span>{{ $brand->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $brand ? $brand->name : 'New brand' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$brand ? route('admin.brands.update', $brand) : route('admin.brands.store')"
                   :method="$brand ? 'PUT' : 'POST'" class="v3-settle">
        <input type="hidden" name="store_id" value="1">

        <x-admin3.panel title="Brand">
            <x-admin3.field name="name" label="Brand name" required>
                <input type="text" name="name" required class="v3-control" value="{{ old('name', $brand?->name) }}">
            </x-admin3.field>
            <div class="v3-gap">
                @include('admin.partials.v3_single_image_picker', [
                    'fieldName' => 'image',
                    'label' => 'Logo / image',
                    'currentImage' => $brand?->image,
                    'root' => 'brands',
                    'defaultFolder' => 'Brands',
                    'hint' => 'Recommended: transparent PNG, square or horizontal.',
                ])
                @error('image') <span class="v3-field__error">{{ $message }}</span> @enderror
            </div>
            <div class="v3-gap">
                <x-admin3.field name="description" label="Description" hint="Shown on the storefront brand page.">
                    <textarea name="description" rows="4" class="v3-control">{{ old('description', $brand?->description) }}</textarea>
                </x-admin3.field>
            </div>
            <div class="v3-gap v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                    <x-admin3.help text="An inactive brand is hidden from the storefront." />
                </label>
                <x-admin3.field name="sort_order" label="Sort order" hint="Lower numbers appear first.">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $brand?->sort_order ?? 0) }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="SEO">
            <x-admin3.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name. Public URL: /brand/{slug}">
                <input type="text" name="slug" class="v3-control v3-mono" value="{{ old('slug', $brand?->slug) }}">
            </x-admin3.field>
            <div class="v3-gap v3-grid v3-grid--2">
                <x-admin3.field name="meta_title" label="Meta title" hint="Recommended: 50 to 60 characters.">
                    <input type="text" name="meta_title" class="v3-control" value="{{ old('meta_title', $brand?->meta_title) }}">
                </x-admin3.field>
                <x-admin3.field name="meta_keywords" label="Meta keywords">
                    <input type="text" name="meta_keywords" class="v3-control" value="{{ old('meta_keywords', $brand?->meta_keywords) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="meta_description" label="Meta description" hint="Recommended: 120 to 160 characters.">
                    <textarea name="meta_description" rows="2" class="v3-control">{{ old('meta_description', $brand?->meta_description) }}</textarea>
                </x-admin3.field>
            </div>
            @include('admin.partials.v3_seo_optimizer', ['entityType' => 'brand', 'entityId' => $brand?->id])
        </x-admin3.panel>

        @if ($brand)
            <x-admin3.panel title="Info">
                <dl class="v3-facts">
                    <dt>Products</dt>
                    <dd class="v3-mono">{{ $brand->products()->count() }}</dd>
                    <dt>Slug</dt>
                    <dd class="v3-mono">{{ $brand->slug }}</dd>
                    <dt>Created</dt>
                    <dd>{{ $brand->created_at->format('M d, Y') }}</dd>
                </dl>
            </x-admin3.panel>
        @endif
    </x-admin3.form>
</x-admin3.shell>
