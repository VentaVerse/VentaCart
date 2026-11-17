{{-- Links to each blog category with published posts. --}}
@if ($data['categories'] !== [] || $context->preview)
    <nav class="chips" data-block-id="{{ $block['id'] }}" data-block-type="categories" data-show-counts="{{ $block['settings']['show_counts'] ? 'true' : 'false' }}"
         aria-label="Blog categories" @if ($block['disabled']) hidden @endif>
        @if ($data['categories'] === [])
            @include('xelga::snippets.editor-note', ['note' => 'Category links show once posts are filed in two or more categories.'])
        @else
            <ul>
                @foreach ($data['categories'] as $link)
                    <li><a class="chip" href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['label'] }}@if ($link['count'] !== null) <span class="chip__count">{{ $link['count'] }}</span>@endif</a></li>
                @endforeach
            </ul>
        @endif
    </nav>
@endif
