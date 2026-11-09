@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
@include('admin._nav')
<form class="mb-3"><input name="q" value="{{ request('q') }}" placeholder="Cari nama / email" class="rounded-lg border px-3 py-2 text-sm"></form>
<div class="rounded-2xl bg-white p-4 shadow-sm overflow-x-auto">
<table class="w-full text-sm">
    <thead class="text-left text-slate-500 border-b"><tr><th class="py-2">Nama</th><th>No. dompet</th><th>Saldo</th><th>Keamanan</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $u)
        <tr class="border-b last:border-0">
            <td class="py-2">{{ $u->name }} @if ($u->isAdmin())<span class="text-xs bg-amber-200 rounded px-1">admin</span>@endif<div class="text-xs text-slate-400">{{ $u->email }}</div></td>
            <td class="font-mono">{{ $u->wallet?->number }}</td>
            <td>{{ rupiah($u->wallet?->balance) }}</td>
            <td class="text-xs">PIN {{ $u->hasPin() ? '✔' : '✖' }} · 2FA {{ $u->hasTwoFactor() ? '✔' : '✖' }}</td>
            <td class="text-xs">{{ $u->last_login_at?->format('d/m H:i') }} {{ $u->last_login_ip }}</td>
            <td>{{ $u->status }}</td>
            <td>@if ($u->id !== auth()->id())
                <form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf<button class="text-xs rounded border px-2 py-1">{{ $u->status === 'active' ? 'Bekukan' : 'Aktifkan' }}</button></form>
            @endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="mt-3">{{ $users->links() }}</div>
</div>
@endsection
