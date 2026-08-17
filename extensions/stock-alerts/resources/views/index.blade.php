<x-admin3.shell title="Stock Alerts">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Stock Alerts</span>
            </div>
            <h1 class="v3-head__title">Stock Alerts</h1>
            <p class="v3-head__standing">Who is waiting for what, and the email they get when it returns.</p>
        </div>
    </div>

    <div class="v3-stats v3-settle">
        <div class="v3-stat"><div class="v3-stat__label">Waiting</div><div class="v3-stat__value">{{ number_format($totalWaiting) }}</div></div>
        <div class="v3-stat"><div class="v3-stat__label">Notified</div><div class="v3-stat__value">{{ number_format($totalNotified) }}</div></div>
        <div class="v3-stat"><div class="v3-stat__label">Failed</div><div class="v3-stat__value">{{ number_format($totalFailed) }}</div></div>
    </div>

    <div x-data="{ tab: @js($tab ?: 'waitlist'),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v3-tabs" role="tablist">
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'waitlist' ? 'true' : 'false'" @click="pick('waitlist')">Waitlist</button>
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'notified' ? 'true' : 'false'" @click="pick('notified')">Notified</button>
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'template' ? 'true' : 'false'" @click="pick('template')">Email template</button>
        </div>

        {{-- ==================== WAITLIST ==================== --}}
        <div x-show="tab === 'waitlist'" class="v3-settle" x-data="{ selected: [] }">
            <x-admin3.panel title="Waitlist" flush>
                <x-slot:acts>
                    <form method="GET" class="v3-actrow">
                        <input type="hidden" name="tab" value="waitlist">
                        <input type="search" name="search" value="{{ $search }}" aria-label="Search the waitlist"
                               placeholder="Search product, name, or email…" class="v3-control v3-narrowsearch">
                        <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Search</button>
                        @if ($search)
                            <a href="{{ route('admin.stock-notifications.index', ['tab' => 'waitlist']) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Clear</a>
                        @endif
                    </form>
                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" x-show="selected.length > 0" x-cloak
                            @click="$dispatch('v3-confirm-bulk')">
                        Delete <span x-text="selected.length"></span> selected
                    </button>
                </x-slot:acts>

                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>
                                    <label class="v3-check">
                                        <input type="checkbox" aria-label="Select every row"
                                               @change="selected = $event.target.checked ? @js($waitlist->pluck('id')->values()) : []">
                                    </label>
                                </th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Requested</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($waitlist as $item)
                                <tr>
                                    <td>
                                        <label class="v3-check">
                                            <input type="checkbox" :value="{{ $item->id }}" x-model.number="selected" aria-label="Select {{ $item->email }}">
                                        </label>
                                    </td>
                                    <td>{{ $item->name }}</td>
                                    <td class="v3-cell-quiet">{{ $item->email }}</td>
                                    <td>{{ $item->product?->name ?? 'Deleted' }}</td>
                                    <td class="v3-cell-quiet">{{ $item->variant?->name ?? '-' }}</td>
                                    <td class="v3-cell-quiet">{{ $item->created_at->format('M j, Y') }}</td>
                                    <td class="v3-cell-act">
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                                @click="$dispatch('v3-confirm', 'delete-waitlist-{{ $item->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="v3-empty">
                                            <p class="v3-empty__title">No subscribers waiting</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($waitlist->hasPages())
                    <x-admin3.pager :paginator="$waitlist" />
                @endif
            </x-admin3.panel>

            {{-- Bulk delete confirm carries the live selection, so it is its
                 own overlay rather than the fixed-form confirm component. --}}
            <div x-data="{ open: false }" x-on:v3-confirm-bulk.window="open = true" x-on:keydown.escape.window="open = false">
                <template x-if="open">
                    <div class="v3-overlay" @click.self="open = false">
                        <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="v3c-bulk">
                            <h2 class="v3-modal__title" id="v3c-bulk">Delete the selected notifications?</h2>
                            <p class="v3-modal__body">Deletes <span x-text="selected.length"></span> waitlist entries. This cannot be undone.</p>
                            <form method="POST" action="{{ route('admin.stock-notifications.bulk-delete') }}" class="v3-modal__acts">
                                @csrf
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="ids[]" :value="id">
                                </template>
                                <button type="button" class="v3-btn v3-btn--ghost" @click="open = false">Cancel</button>
                                <button type="submit" class="v3-btn v3-btn--danger-fill">Delete selected</button>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

            @foreach ($waitlist as $item)
                <x-admin3.confirm name="delete-waitlist-{{ $item->id }}" title="Remove this notification?"
                    message="{{ $item->email }} stops waiting for {{ $item->product?->name ?? 'this product' }}."
                    confirm="Delete" :action="route('admin.stock-notifications.destroy', $item)" method="DELETE" />
            @endforeach

            @if ($topProducts->isNotEmpty())
                <x-admin3.panel title="Most requested" flush>
                    <div class="v3-tablewrap">
                        <table class="v3-table">
                            <thead>
                                <tr><th>Product</th><th>Total requests</th><th>Currently waiting</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($topProducts as $tp)
                                    <tr>
                                        <td>{{ $tp->name }}</td>
                                        <td class="v3-mono">{{ $tp->total_requests }}</td>
                                        <td><span class="v3-badge {{ $tp->waiting_count > 0 ? 'v3-badge--warn' : 'v3-badge--quiet' }}">{{ $tp->waiting_count }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin3.panel>
            @endif
        </div>

        {{-- ==================== NOTIFIED ==================== --}}
        <div x-show="tab === 'notified'" x-cloak>
            <x-admin3.panel title="Notified" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Customer</th><th>Email</th><th>Product</th><th>Variant</th><th>Requested</th><th>Notified</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($notified as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td class="v3-cell-quiet">{{ $item->email }}</td>
                                    <td>{{ $item->product?->name ?? 'Deleted' }}</td>
                                    <td class="v3-cell-quiet">{{ $item->variant?->name ?? '-' }}</td>
                                    <td class="v3-cell-quiet">{{ $item->created_at->format('M j, Y') }}</td>
                                    <td class="v3-mono">{{ $item->notified_at?->format('M j, Y g:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="v3-empty">
                                            <p class="v3-empty__title">No notifications sent yet</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($notified->hasPages())
                    <x-admin3.pager :paginator="$notified" />
                @endif
            </x-admin3.panel>
        </div>

        {{-- ==================== EMAIL TEMPLATE ==================== --}}
        <div x-show="tab === 'template'" x-cloak class="v3-settle">
            <x-admin3.form :action="route('admin.stock-notifications.update-template')" id="v3-stock-template-form">
                <x-admin3.panel title="Email template">
                    <x-admin3.field name="subject" label="Subject" required>
                        <input type="text" name="subject" required class="v3-control"
                               placeholder="Good news! {product_name} is back in stock"
                               value="{{ old('subject', $template?->subject ?? '') }}">
                    </x-admin3.field>
                    <div class="v3-gap">
                        <x-admin3.field name="body" label="Body" required>
                            <textarea name="body" id="v3-stock-template-body" rows="12" class="v3-control">{{ old('body', $template?->body ?? '') }}</textarea>
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap">
                        <span class="v3-label v3-gap-sm v3-block">Placeholders</span>
                        @foreach (['{customer_name}', '{product_name}', '{product_url}', '{variant_name}', '{product_image}', '{store_name}'] as $ph)
                            <button type="button" class="v3-chip v3-chip--inline"
                                    @click="v3Copy(@js($ph)).then(ok => { if (!ok) return; const t = $el.textContent; $el.textContent = 'Copied'; setTimeout(() => $el.textContent = t, 1500); })">{{ $ph }}</button>
                        @endforeach
                    </div>
                </x-admin3.panel>

                <x-admin3.panel title="Send a test" note="Uses the first product's real data, so every placeholder resolves.">
                    <div x-data="{ sending: false }">
                        <button type="button" class="v3-btn v3-btn--secondary" :disabled="sending"
                                @click="sending = true; v3Post(@js(route('admin.stock-notifications.send-test'))).finally(() => sending = false)">
                            <span x-text="sending ? 'Sending…' : @js('Send a test to ' . auth()->user()->email)">Send a test to {{ auth()->user()->email }}</span>
                        </button>
                    </div>
                </x-admin3.panel>
            </x-admin3.form>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#v3-stock-template-body',
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
                    var form = document.getElementById('v3-stock-template-form');
                    if (form) form.dispatchEvent(new Event('input'));
                });
            },
        });
    </script>
    @endpush
</x-admin3.shell>
