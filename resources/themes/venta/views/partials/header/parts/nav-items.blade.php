            @foreach ($navItems as $item)
                @php $subs = $navSubcategories[$item->category_id] ?? []; @endphp
                @if (count($subs) > 0)
                    <div class="nav-dropdown-wrap">
                        <a href="{{ $item->resolved_url }}" class="nav-dropdown-trigger">
                            {{ $item->label }}
                            <svg class="nav-dropdown-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </a>
                        <div class="nav-dropdown">
                            <a href="{{ $item->resolved_url }}" class="nav-dropdown-item nav-dropdown-all">All {{ $item->label }}</a>
                            @foreach ($subs as $sub)
                                @if (!empty($sub['children']))
                                    <div class="nav-dropdown-parent">
                                        <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item nav-dropdown-has-children">
                                            {{ $sub['name'] }}
                                            <svg class="nav-submenu-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                        <div class="nav-submenu">
                                            <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item nav-dropdown-all">All {{ $sub['name'] }}</a>
                                            @foreach ($sub['children'] as $grandchild)
                                                <a href="{{ route('category.show', $grandchild['slug']) }}" class="nav-dropdown-item">{{ $grandchild['name'] }}</a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route('category.show', $sub['slug']) }}" class="nav-dropdown-item">{{ $sub['name'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $item->resolved_url }}" @if ($item->new_tab) target="_blank" rel="noopener" @endif>
                        {{ $item->label }}
                    </a>
                @endif
            @endforeach
