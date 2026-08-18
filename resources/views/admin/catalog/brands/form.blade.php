<x-admin.shell :title="$brand ? 'Edit Brand' : 'New Brand'" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.brands.index') }}">Brands</a>
                <span aria-hidden="true">/</span>
                <span>{{ $brand->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $brand ? $brand->name : 'New brand' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$brand ? route('admin.brands.update', $brand) : route('admin.brands.store')"
                   :method="$brand ? 'PUT' : 'POST'" class="v-settle">
        <input type="hidden" name="store_id" value="1">

        <x-admin.panel title="Brand">
            <x-admin.field name="name" label="Brand name" required>
                <input type="text" name="name" required class="v-control" value="{{ old('name', $brand?->name) }}">
            </x-admin.field>
            <div class="v-gap">
                @include('admin.partials.single_image_picker', [
                    'fieldName' => 'image',
                    'label' => 'Logo / image',
                    'currentImage' => $brand?->image,
                    'root' => 'brands',
                    'defaultFolder' => 'Brands',
                    'hint' => 'Recommended: transparent PNG, square or horizontal.',
                ])
                @error('image') <span class="v-field__error">{{ $message }}</span> @enderror
            </div>
            <div class="v-gap">
                <x-admin.field name="description" label="Description" hint="Shown on the storefront brand page.">
                    <textarea name="description" rows="4" class="v-control">{{ old('description', $brand?->description) }}</textarea>
                </x-admin.field>
            </div>
            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                    <x-admin.help text="An inactive brand is hidden from the storefront." />
                </label>
                <x-admin.field name="sort_order" label="Sort order" hint="Lower numbers appear first.">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono v-w-sort" value="{{ old('sort_order', $brand?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="SEO">
            <x-admin.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name. Public URL: /brand/{slug}">
                <input type="text" name="slug" class="v-control v-mono" value="{{ old('slug', $brand?->slug) }}">
            </x-admin.field>
            <div class="v-gap v-grid v-grid--2">
                <x-admin.field name="meta_title" label="Meta title" hint="Recommended: 50 to 60 characters.">
                    <input type="text" name="meta_title" class="v-control" value="{{ old('meta_title', $brand?->meta_title) }}">
                </x-admin.field>
                <x-admin.field name="meta_keywords" label="Meta keywords">
                    <input type="text" name="meta_keywords" class="v-control" value="{{ old('meta_keywords', $brand?->meta_keywords) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="meta_description" label="Meta description" hint="Recommended: 120 to 160 characters.">
                    <textarea name="meta_description" rows="2" class="v-control">{{ old('meta_description', $brand?->meta_description) }}</textarea>
                </x-admin.field>
            </div>
            @include('admin.partials.seo_optimizer', ['entityType' => 'brand', 'entityId' => $brand?->id])
        </x-admin.panel>

        @if ($brand)
            <x-admin.panel title="Info">
                <dl class="v-facts">
                    <dt>Products</dt>
                    <dd class="v-mono">{{ $brand->products()->count() }}</dd>
                    <dt>Slug</dt>
                    <dd class="v-mono">{{ $brand->slug }}</dd>
                    <dt>Created</dt>
                    <dd>{{ $brand->created_at->format('M d, Y') }}</dd>
                </dl>
            </x-admin.panel>
        @endif
    </x-admin.form>
</x-admin.shell>
