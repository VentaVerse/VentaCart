{{-- A search of the shop, through core's search page. --}}
@if ($data['searchUrl'])
    <search class="nf__search" data-block-id="{{ $block['id'] }}" data-block-type="search" @if ($block['disabled']) hidden @endif>
        <form class="search-field" action="{{ $data['searchUrl'] }}" method="get" role="search">
            <label class="visually-hidden" for="{{ $block['id'] }}-q">Search the shop</label>
            <input class="search-field__input" id="{{ $block['id'] }}-q" type="search" name="q" autocomplete="off" maxlength="120"
                   placeholder="{{ $block['settings']['placeholder'] }}" data-setting-attr="placeholder:placeholder">
            <button class="search-field__submit" type="submit" aria-label="Search"><svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg></button>
        </form>
    </search>
@endif
