@extends('layouts.guest')
@section('title', 'Verifikasi 2FA')
@section('content')
<h2 class="text-lg font-semibold mb-1">Verifikasi dua langkah</h2>
<p class="text-sm text-slate-500 mb-4">Masukkan 6 digit kode dari aplikasi authenticator Anda.</p>
<form method="POST" action="{{ route('two-factor.challenge') }}" class="space-y-4">@csrf
    <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Verifikasi</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button class="text-sm text-slate-500 hover:underline">Batal &amp; keluar</button></form>
@endsection
