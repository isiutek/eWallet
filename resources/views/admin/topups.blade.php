@extends('layouts.app')
@section('title', 'Verifikasi Isi Saldo')
@section('content')
@include('admin._nav')
<div class="flex gap-2 mb-3 text-sm">
    @foreach (['pending' => 'Menunggu', 'paid' => 'Berhasil', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa', 'all' => 'Semua'] as $k => $v)
        <a href="?status={{ $k }}" class="px-3 py-1 rounded-full {{ $status === $k ? 'bg-indigo-600 text-white' : 'bg-white' }}">{{ $v }}</a>
    @endforeach
</div>
<div class="rounded-2xl bg-white p-4 shadow-sm overflow-x-auto">
<table class="w-full text-sm">
    <thead class="text-left text-slate-500 border-b"><tr><th class="py-2">Waktu</th><th>Pengguna</th><th>Nominal</th><th>Metode</th><th>Pengirim / Bukti</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    @forelse ($topups as $t)
        <tr class="border-b last:border-0 align-top">
            <td class="py-2 whitespace-nowrap">{{ $t->created_at->format('d/m H:i') }}<div class="text-xs text-slate-400">{{ $t->reference }}</div></td>
            <td>{{ $t->user->name }}<div class="text-xs text-slate-400">{{ $t->user->email }}</div></td>
            <td class="font-semibold">{{ rupiah($t->amount) }}</td>
            <td>{{ $t->method === 'xendit' ? 'Xendit'.($t->payment_channel ? ' · '.$t->payment_channel : '') : 'Manual' }}</td>
            <td>{{ $t->sender_name }} @if ($t->proof_path)<a target="_blank" href="{{ route('admin.topups.proof', $t) }}" class="text-indigo-600 block">Lihat bukti</a>@endif</td>
            <td>{{ $t->status }}@if ($t->processor)<div class="text-xs text-slate-400">oleh {{ $t->processor->name }}</div>@endif @if ($t->admin_note)<div class="text-xs text-slate-500">{{ $t->admin_note }}</div>@endif</td>
            <td class="min-w-[220px]">
                @if ($t->status === 'pending' && $t->method === 'manual')
                    <form method="POST" action="{{ route('admin.topups.approve', $t) }}" onsubmit="return confirm('Setujui dan tambahkan saldo {{ rupiah($t->amount) }}?')">@csrf
                        <button class="rounded bg-emerald-600 text-white px-3 py-1 text-xs">Setujui</button></form>
                    <form method="POST" action="{{ route('admin.topups.reject', $t) }}" class="mt-1 flex gap-1">@csrf
                        <input name="note" placeholder="Alasan tolak" required class="border rounded px-2 py-1 text-xs flex-1">
                        <button class="rounded bg-rose-600 text-white px-3 py-1 text-xs">Tolak</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="py-6 text-center text-slate-400">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="mt-3">{{ $topups->links() }}</div>
</div>
@endsection
