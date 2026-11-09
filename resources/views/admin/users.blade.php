@extends('layouts.app')
@section('title', 'Pengguna')
@section('content')
@include('admin._nav')
@if ($t = session('temp_password'))
    <div class="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm">
        Password <b>{{ $t['name'] }}</b> ({{ $t['email'] }}) sudah direset. Berikan password sementara ini kepada pengguna:
        <div class="my-2 flex items-center gap-2">
            <code id="tmppw" class="text-lg font-mono bg-white border rounded px-3 py-1 select-all">{{ $t['password'] }}</code>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('tmppw').textContent)" class="rounded border px-2 py-1 text-xs bg-white">Salin</button>
        </div>
        Password ini hanya ditampilkan sekali. Pengguna wajib menggantinya saat login berikutnya, dan semua sesi lamanya sudah dikeluarkan.
    </div>
@endif
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
            <td>{{ $u->status }}@if ($u->must_change_password)<div class="text-xs text-amber-600">wajib ganti password</div>@endif @if ($u->login_locked_until?->isFuture())<div class="text-xs text-rose-600">login terkunci</div>@endif</td>
            <td class="whitespace-nowrap">@if ($u->id !== auth()->id())
                <div class="flex flex-wrap gap-1">
                    <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" onsubmit="return confirm('Reset password {{ $u->email }}? Pengguna akan dikeluarkan dari semua perangkat.')">@csrf
                        <button class="text-xs rounded border border-indigo-300 text-indigo-700 px-2 py-1">Reset password</button></form>
                    @if ($u->hasPin())
                    <form method="POST" action="{{ route('admin.users.reset-pin', $u) }}" onsubmit="return confirm('Reset PIN transaksi {{ $u->email }}?')">@csrf
                        <button class="text-xs rounded border px-2 py-1">Reset PIN</button></form>
                    @endif
                    @if ($u->hasTwoFactor())
                    <form method="POST" action="{{ route('admin.users.reset-2fa', $u) }}" onsubmit="return confirm('Nonaktifkan 2FA {{ $u->email }}? Lakukan hanya setelah identitas pengguna diverifikasi.')">@csrf
                        <button class="text-xs rounded border px-2 py-1">Reset 2FA</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf<button class="text-xs rounded border px-2 py-1">{{ $u->status === 'active' ? 'Bekukan' : 'Aktifkan' }}</button></form>
                </div>
            @endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="mt-3">{{ $users->links() }}</div>
</div>
@endsection
