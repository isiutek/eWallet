@extends('layouts.app')
@section('title', 'Keamanan')
@section('content')
<div class="grid gap-4 md:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="font-semibold">PIN transaksi {!! $user->hasPin() ? '<span class="text-xs text-emerald-600">● aktif</span>' : '<span class="text-xs text-rose-600">● belum dibuat</span>' !!}</h3>
        <p class="text-sm text-slate-500 mb-3">Wajib untuk setiap transaksi keluar. Terkunci 30 menit setelah 5 kali salah.</p>
        <form method="POST" action="{{ route('security.pin') }}" class="space-y-3">@csrf
            <label class="block text-sm">Password akun<input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <label class="block text-sm">PIN baru (6 digit)<input type="password" name="pin" inputmode="numeric" maxlength="6" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <label class="block text-sm">Ulangi PIN<input type="password" name="pin_confirmation" inputmode="numeric" maxlength="6" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">{{ $user->hasPin() ? 'Ganti PIN' : 'Buat PIN' }}</button>
        </form>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="font-semibold">Autentikasi dua faktor (2FA) {!! $user->hasTwoFactor() ? '<span class="text-xs text-emerald-600">● aktif</span>' : '<span class="text-xs text-rose-600">● nonaktif</span>' !!}</h3>
        <p class="text-sm text-slate-500 mb-3">Kode dari Google Authenticator / Authy diminta setiap login.</p>
        @if ($user->hasTwoFactor())
            <form method="POST" action="{{ route('security.2fa.disable') }}" class="space-y-3">@csrf
                <label class="block text-sm">Password untuk menonaktifkan<input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
                <button class="rounded-lg border border-rose-300 text-rose-700 px-4 py-2">Nonaktifkan 2FA</button>
            </form>
        @elseif ($setupSecret)
            <div class="text-center">{!! $setupQr !!}</div>
            <p class="text-xs text-slate-500 text-center break-all">Kunci manual: <code>{{ $setupSecret }}</code></p>
            <form method="POST" action="{{ route('security.2fa.confirm') }}" class="space-y-3 mt-3">@csrf
                <label class="block text-sm">Kode 6 digit dari aplikasi<input name="code" inputmode="numeric" maxlength="6" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
                <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">Aktifkan</button>
            </form>
        @else
            <form method="POST" action="{{ route('security.2fa.start') }}">@csrf<button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">Mulai pengaturan 2FA</button></form>
        @endif
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="font-semibold mb-3">Ganti password</h3>
        <form method="POST" action="{{ route('security.password') }}" class="space-y-3">@csrf
            <label class="block text-sm">Password saat ini<input type="password" name="current_password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <label class="block text-sm">Password baru<input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <label class="block text-sm">Ulangi password baru<input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">Simpan</button>
        </form>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="font-semibold mb-3">Aktivitas keamanan terakhir</h3>
        <ul class="text-sm divide-y">
            @foreach ($logs as $l)
                <li class="py-1.5 flex justify-between gap-2"><span>{{ $l->action }}</span><span class="text-xs text-slate-400 text-right">{{ $l->ip }} · {{ $l->created_at->format('d/m H:i') }}</span></li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
