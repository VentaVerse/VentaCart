<x-admin.shell title="Upload a package">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Upload</span>
            </div>
            <h1 class="v-head__title">Upload a theme or extension</h1>
            <p class="v-head__standing">Install a package from its .zip, or update one installed this way.</p>
        </div>
    </div>

    <div class="v-settle">
        @if ($result)
            <div class="v-notice {{ $result['ok'] ? 'v-notice--good' : 'v-notice--bad' }}" role="status">
                <p class="v-notice__title">
                    @if (! $result['ok'])
                        The package was not installed
                    @elseif ($result['updated'] && $result['previous_version'] !== $result['version'])
                        Updated from version {{ $result['previous_version'] }} to {{ $result['version'] }}
                    @elseif ($result['updated'])
                        Version {{ $result['version'] }} installed again
                    @else
                        Installed version {{ $result['version'] }}
                    @endif
                </p>
                <p class="v-notice__text">{{ $result['message'] }}</p>
                @if ($result['ok'])
                    <p class="v-noticerow">
                        @if ($result['kind'] === 'theme' && \Illuminate\Support\Facades\Route::has('admin.theme.index'))
                            <a href="{{ route('admin.theme.index') }}" class="v-btn v-btn--sm">Go to Themes</a>
                        @else
                            <a href="{{ route('admin.extensions.index') }}" class="v-btn v-btn--sm">Go to Extensions</a>
                        @endif
                    </p>
                @endif
            </div>
        @endif

        <x-admin.panel title="Package file" :note="'A .zip of one folder named after the package, with its theme.json or extension.json inside. At most ' . $uploadLimit . '.'">
            <p class="v-field__hint">Installing runs the package's code on this store: its migrations at once, and its provider on every request after. Upload only packages from someone you trust.</p>
            <form method="POST" action="{{ route('admin.extensions.upload.store') }}" enctype="multipart/form-data"
                  class="v-upload" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <x-admin.field name="package" label="Theme or extension (.zip)" required>
                    <input type="file" name="package" accept=".zip,application/zip" required class="v-file">
                </x-admin.field>
                @if ($serverCapsUpload)
                    <p class="v-field__hint">This server accepts uploads of up to {{ $uploadLimit }}.</p>
                @endif
                <div class="v-upload__acts">
                    <button type="submit" class="v-btn v-btn--primary" :disabled="busy">
                        <span x-text="busy ? 'Installing…' : 'Upload and install'">Upload and install</span>
                    </button>
                </div>
            </form>
        </x-admin.panel>

        <x-admin.panel title="Recent uploads" flush>
            <div class="v-tablewrap">
                <table class="v-table v-table--cards">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>File</th>
                            <th>Package</th>
                            <th>Version</th>
                            <th>Result</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent as $upload)
                            <tr>
                                <td data-label="When" class="v-mono">{{ $upload->created_at?->format('Y-m-d H:i') }}</td>
                                <td data-label="File" class="v-cell-wide"><span class="v-mono v-upload__file">{{ $upload->filename }}</span></td>
                                <td data-label="Package">{{ $upload->slug ? ucfirst($upload->kind ?? '') . ' ' . $upload->slug : 'Not read' }}</td>
                                <td data-label="Version" class="v-mono">
                                    @if ($upload->previous_version && $upload->previous_version !== $upload->version)
                                        {{ $upload->previous_version }} to {{ $upload->version }}
                                    @else
                                        {{ $upload->version ?? 'None' }}
                                    @endif
                                </td>
                                <td data-label="Result" class="v-cell-wide">
                                    <span class="v-badge v-badge--{{ match ($upload->result) { 'installed', 'updated' => 'good', 'pending' => 'warn', default => 'bad' } }}">{{ $upload->result === 'pending' ? 'In progress' : ucfirst($upload->result) }}</span>
                                    @if (in_array($upload->result, ['refused', 'failed'], true) && $upload->message)
                                        <span class="v-upload__why">{{ \Illuminate\Support\Str::limit($upload->message, 160) }}</span>
                                    @endif
                                </td>
                                <td data-label="By">{{ $upload->user?->name ?? 'Removed user' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No uploads yet</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    </div>
</x-admin.shell>
