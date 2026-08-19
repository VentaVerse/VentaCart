<x-admin.shell title="Email">
    @push('head')
        @vite('extensions/email-campaigns/resources/css/email-campaigns.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Email</span>
            </div>
            <h1 class="v-head__title">Email</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.email-campaigns.create') }}" class="v-btn v-btn--primary">New campaign</a>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Audience</th>
                        <th class="v-cell-num">Recipients</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td><a href="{{ route('admin.email-campaigns.edit', $campaign) }}" class="v-rowlink">{{ $campaign->subject }}</a></td>
                            <td class="v-cell-quiet">{{ $audienceLabels[$campaign->audience_type] ?? $campaign->audience_type }}</td>
                            <td class="v-cell-num v-mono">{{ $campaign->recipients_total }}</td>
                            <td>
                                <span class="v-badge v-badge--{{ ['sent' => 'good', 'queued' => 'info', 'failed' => 'bad', 'draft' => 'quiet'][$campaign->status] ?? 'quiet' }}">{{ ucfirst($campaign->status) }}</span>
                            </td>
                            <td class="v-cell-quiet">{{ $campaign->sent_at?->format('M j, Y g:ia') ?? '-' }}</td>
                            <td class="v-cell-act">
                                <a href="{{ route('admin.email-campaigns.edit', $campaign) }}" class="v-btn v-btn--ghost v-btn--sm">Open</a>
                                @if ($campaign->status !== 'sent')
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-campaign-{{ $campaign->id }}')">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="v-empty">
                                    <p class="v-empty__line">No campaigns yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.pager :paginator="$campaigns" />
    </div>

    @foreach ($campaigns as $campaign)
        @if ($campaign->status !== 'sent')
            <x-admin.confirm name="delete-campaign-{{ $campaign->id }}" title="Delete &ldquo;{{ $campaign->subject }}&rdquo;?"
                message="This permanently removes the campaign. This cannot be undone."
                confirm="Delete" :action="route('admin.email-campaigns.destroy', $campaign)" method="DELETE" />
        @endif
    @endforeach
</x-admin.shell>
