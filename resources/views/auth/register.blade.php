@extends('layouts.guest')
@section('title', 'Daftar')
@section('content')
<h2 class="text-lg font-semibold mb-4">Buat akun</h2>
<form method="POST" action="{{ route('register') }}" class="space-y-4">@csrf
    <label class="block text-sm">Nama lengkap<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label class="block text-sm">Email<input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label class="block text-sm">No. HP (opsional)<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label class="block text-sm">Password<input name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <p class="text-xs text-slate-500 -mt-2">Min. 8 karakter, huruf besar, huruf kecil, dan angka.</p>
    <label class="block text-sm">Ulangi password<input name="password_confirmation" type="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Daftar</button>
</form>
<p class="mt-4 text-sm text-center">Sudah punya akun? <a href="{{ route('login') }}" class="text-indigo-600 font-semibold">Masuk</a></p>
@endsection
