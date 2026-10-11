<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">When</th>
                    <th scope="col">Action</th>
                    <th scope="col">User</th>
                    <th scope="col">IP address</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('j M Y, H:i:s') }}</td>
                        <td class="text-nowrap"><code>{{ $log->action }}</code></td>
                        <td class="small">{{ $log->user?->email ?? '—' }}</td>
                        <td class="small text-nowrap">{{ $log->ip_address ?? '—' }}</td>
                        <td class="small text-break">
                            @if (! empty($log->metadata))
                                {{ json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No entries.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
