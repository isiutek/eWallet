<div class="overflow-x-auto"><table class="w-full text-sm">
    <thead class="text-left text-slate-500 border-b"><tr><th class="py-2">Waktu</th><th>Pengguna</th><th>Aksi</th><th>IP</th><th>Detail</th></tr></thead>
    <tbody>
    @foreach ($logs as $l)
        <tr class="border-b last:border-0"><td class="py-1.5 whitespace-nowrap">{{ $l->created_at->format('d/m H:i:s') }}</td><td>{{ $l->user?->email ?? '-' }}</td>
            <td class="font-mono text-xs">{{ $l->action }}</td><td class="text-xs">{{ $l->ip }}</td><td class="text-xs text-slate-500 break-all">{{ $l->meta ? json_encode($l->meta) : '' }}</td></tr>
    @endforeach
    </tbody>
</table></div>
