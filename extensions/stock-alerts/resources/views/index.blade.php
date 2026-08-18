<x-admin.shell title="Stock Alerts">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Stock Alerts</span>
            </div>
            <h1 class="v-head__title">Stock Alerts</h1>
            <p class="v-head__standing">Who is waiting for what, and the email they get when it returns.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="stock-alerts" />
        </div>
    </div>

    <div class="v-stats v-settle">
        <div class="v-stat"><div class="v-stat__label">Waiting</div><div class="v-stat__value">{{ number_format($totalWaiting) }}</div></div>
        <div class="v-stat"><div class="v-stat__label">Notified</div><div class="v-stat__value">{{ number_format($totalNotified) }}</div></div>
        <div class="v-stat"><div class="v-stat__label">Failed</div><div class="v-stat__value">{{ number_format($totalFailed) }}</div></div>
    </div>

    <div x-data="{ tab: @js($tab ?: 'waitlist'),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v-modgrid">
        <nav class="v-snav" aria-label="Sections">
            <button type="button" class="v-snav__item" :aria-current="tab === 'waitlist' ? 'page' : false" @click="pick('waitlist')">
                <x-admin.icon name="clock" />
                Waitlist
            </button>
            <button type="button" class="v-snav__item" :aria-current="tab === 'notified' ? 'page' : false" @click="pick('notified')">
                <x-admin.icon name="check" />
                Notified
            </button>
            <button type="button" class="v-snav__item" :aria-current="tab === 'template' ? 'page' : false" @click="pick('template')">
                <x-admin.icon name="mail" />
                Email template
            </button>
        </nav>
        <div class="v-modmain">

        {{-- ==================== WAITLIST ==================== --}}
        <div x-show="tab === 'waitlist'" class="v-settle" x-data="{ selected: [] }">
            <x-admin.panel title="Waitlist" flush>
                <x-slot:acts>
                    <form method="GET" class="v-actrow">
                        <input type="hidden" name="tab" value="waitlist">
                        <input type="search" name="search" value="{{ $search }}" aria-label="Search the waitlist"
                               placeholder="Search product, name, or email…" class="v-control v-narrowsearch">
                        <button type="submit" class="v-btn v-btn--secondary v-btn--sm">Search</button>
                        @if ($search)
                            <a href="{{ route('admin.stock-notifications.index', ['tab' => 'waitlist']) }}" class="v-btn v-btn--ghost v-btn--sm">Clear</a>
                        @endif
                    </form>
                    <button type="button" class="v-btn v-btn--danger v-btn--sm" x-show="selected.length > 0" x-cloak
                            @click="$dispatch('v-confirm-bulk')">
                        Delete <span x-text="selected.length"></span> selected
                    </button>
                </x-slot:acts>

                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>
                                    <label class="v-check">
                                        <input type="checkbox" aria-label="Select every row"
                                               @change="selected = $event.target.checked ? @js($waitlist->pluck('id')->values()) : []">
                                    </label>
                                </th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Requested</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($waitlist as $item)
                                <tr>
                                    <td>
                                        <label class="v-check">
                                            <input type="checkbox" :value="{{ $item->id }}" x-model.number="selected" aria-label="Select {{ $item->email }}">
                                        </label>
                                    </td>
                                    <td>{{ $item->name }}</td>
                                    <td class="v-cell-quiet">{{ $item->email }}</td>
                                    <td>{{ $item->product?->name ?? 'Deleted' }}</td>
                                    <td class="v-cell-quiet">{{ $item->variant?->name ?? '-' }}</td>
                                    <td class="v-cell-quiet">{{ $item->created_at->format('M j, Y') }}</td>
                                    <td class="v-cell-act">
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                @click="$dispatch('v-confirm', 'delete-waitlist-{{ $item->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="v-empty">
                                            <p class="v-empty__title">No subscribers waiting</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($waitlist->hasPages())
                    <x-admin.pager :paginator="$waitlist" />
                @endif
            </x-admin.panel>

            {{-- Bulk delete confirm carries the live selection, so it is its
                 own overlay rather than the fixed-form confirm component. --}}
            <div x-data="{ open: false }" x-on:v-confirm-bulk.window="open = true" x-on:keydown.escape.window="open = false">
                <template x-if="open">
                    <div class="v-overlay" @click.self="open = false">
                        <div class="v-modal" role="dialog" aria-modal="true" aria-labelledby="vc-bulk">
                            <h2 class="v-modal__title" id="vc-bulk">Delete the selected notifications?</h2>
                            <p class="v-modal__body">Deletes <span x-text="selected.length"></span> waitlist entries. This cannot be undone.</p>
                            <form method="POST" action="{{ route('admin.stock-notifications.bulk-delete') }}" class="v-modal__acts">
                                @csrf
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="ids[]" :value="id">
                                </template>
                                <button type="button" class="v-btn v-btn--ghost" @click="open = false">Cancel</button>
                                <button type="submit" class="v-btn v-btn--danger-fill">Delete selected</button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

            @foreach ($waitlist as $item)
                <x-admin.confirm name="delete-waitlist-{{ $item->id }}" title="Remove this notification?"
                    message="{{ $item->email }} stops waiting for {{ $item->product?->name ?? 'this product' }}."
                    confirm="Delete" :action="route('admin.stock-notifications.destroy', $item)" method="DELETE" />
            @endforeach

            @if ($topProducts->isNotEmpty())
                <x-admin.panel title="Most requested" flush>
                    <div class="v-tablewrap">
                        <table class="v-table">
                            <thead>
                                <tr><th>Product</th><th>Total requests</th><th>Currently waiting</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($topProducts as $tp)
                                    <tr>
                                        <td>{{ $tp->name }}</td>
                                        <td class="v-mono">{{ $tp->total_requests }}</td>
                                        <td><span class="v-badge {{ $tp->waiting_count > 0 ? 'v-badge--warn' : 'v-badge--quiet' }}">{{ $tp->waiting_count }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.panel>
            @endif
        </div>

        {{-- ==================== NOTIFIED ==================== --}}
        <div x-show="tab === 'notified'" x-cloak>
            <x-admin.panel title="Notified" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Customer</th><th>Email</th><th>Product</th><th>Variant</th><th>Requested</th><th>Notified</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($notified as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td class="v-cell-quiet">{{ $item->email }}</td>
                                    <td>{{ $item->product?->name ?? 'Deleted' }}</td>
                                    <td class="v-cell-quiet">{{ $item->variant?->name ?? '-' }}</td>
                                    <td class="v-cell-quiet">{{ $item->created_at->format('M j, Y') }}</td>
                                    <td class="v-mono">{{ $item->notified_at?->format('M j, Y g:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="v-empty">
                                            <p class="v-empty__title">No notifications sent yet</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($notified->hasPages())
                    <x-admin.pager :paginator="$notified" />
                @endif
            </x-admin.panel>
        </div>

        {{-- ==================== EMAIL TEMPLATE ==================== --}}
        <div x-show="tab === 'template'" x-cloak class="v-settle">
            <x-admin.form :action="route('admin.stock-notifications.update-template')" id="v-stock-template-form">
                <x-admin.panel title="Email template">
                    <x-admin.field name="subject" label="Subject" required>
                        <input type="text" name="subject" required class="v-control"
                               placeholder="Good news! {product_name} is back in stock"
                               value="{{ old('subject', $template?->subject ?? '') }}">
                    </x-admin.field>
                    <div class="v-gap">
                        <x-admin.field name="body" label="Body" required>
                            <textarea name="body" id="v-stock-template-body" rows="12" class="v-control">{{ old('body', $template?->body ?? '') }}</textarea>
                        </x-admin.field>
                    </div>
                    <div class="v-gap">
                        <span class="v-label v-gap-sm v-block">Placeholders</span>
                        @foreach (['{customer_name}', '{product_name}', '{product_url}', '{variant_name}', '{product_image}', '{store_name}'] as $ph)
                            <button type="button" class="v-chip v-chip--inline"
                                    @click="vCopy(@js($ph)).then(ok => { if (!ok) return; const t = $el.textContent; $el.textContent = 'Copied'; setTimeout(() => $el.textContent = t, 1500); })">{{ $ph }}</button>
                        @endforeach
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Send a test" note="Uses the first product's real data, so every placeholder resolves.">
                    <div x-data="{ sending: false }">
                        <button type="button" class="v-btn v-btn--secondary" :disabled="sending"
                                @click="sending = true; vPost(@js(route('admin.stock-notifications.send-test'))).finally(() => sending = false)">
                            <span x-text="sending ? 'Sending…' : @js('Send a test to ' . auth()->user()->email)">Send a test to {{ auth()->user()->email }}</span>
                        </button>
                    </div>
                </x-admin.panel>
            </x-admin.form>
        </div>
        </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v-stock-template-body',
            height: 350,
            menubar: false,
            plugins: 'lists link autolink code table',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table | removeformat code',
            content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; }',
            branding: false,
            promotion: false,
            license_key: 'gpl',
            convert_urls: false,
            setup: function (editor) {
                editor.on('input change undo redo', function () {
                    editor.save();
                    var form = document.getElementById('v-stock-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin.shell>
