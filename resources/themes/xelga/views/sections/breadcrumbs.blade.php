{{-- Breadcrumbs: the trail from the home page to this page. --}}
@if (count($data['crumbs']) > 1)
    <nav class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs') data-pad="none" aria-label="Breadcrumb">
        <div class="s__inner">
            <ol class="crumbs">
                @foreach ($data['crumbs'] as $crumb)
                    <li>
                        @if ($crumb['url'] !== null)
                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else
                            <span aria-current="page">{{ $crumb['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>
@endif
