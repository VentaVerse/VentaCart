{{-- Search the blog: core's blog search, keeping the chosen category, tag and view. --}}
<search class="blog-search" data-block-id="{{ $block['id'] }}" data-block-type="search" @if ($block['disabled']) hidden @endif>
    <form class="search-field" action="{{ route('blog.index') }}" method="get" role="search">
        @foreach (['category', 'tag', 'view'] as $keep)
            @if (is_string(request()->query($keep)) && request()->query($keep) !== '')
                <input type="hidden" name="{{ $keep }}" value="{{ request()->query($keep) }}">
            @endif
        @endforeach
        <label class="visually-hidden" for="{{ $block['id'] }}-q">Search the blog</label>
        <input class="search-field__input" id="{{ $block['id'] }}-q" type="search" name="q" value="{{ old('q', $data['search']) }}"
               minlength="{{ $data['searchMin'] }}" maxlength="{{ $data['searchMax'] }}" autocomplete="off"
               placeholder="{{ $block['settings']['placeholder'] }}" data-setting-attr="placeholder:placeholder"
               @error('q') aria-invalid="true" aria-describedby="{{ $block['id'] }}-error" @enderror>
        <button class="search-field__submit" type="submit" aria-label="Search posts"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></button>
    </form>
    @error('q')
        <p class="field__error" id="{{ $block['id'] }}-error"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $message }}</span></p>
    @enderror
</search>
