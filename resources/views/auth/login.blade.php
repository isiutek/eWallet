@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<h2 class="text-lg font-semibold mb-4">Masuk</h2>
<form method="POST" action="{{ route('login') }}" class="space-y-4">@csrf
    <label class="block text-sm">Email<input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label class="block text-sm">Password<input name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Ingat saya</label>
    <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Masuk</button>
</form>
<p class="mt-4 text-xs text-center text-slate-500">Lupa password? Hubungi admin untuk mereset password Anda.</p>
<p class="mt-2 text-sm text-center">Belum punya akun? <a href="{{ route('register') }}" class="text-indigo-600 font-semibold">Daftar</a></p>
@endsection
