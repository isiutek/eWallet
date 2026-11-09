@extends('layouts.base')
@section('body')
@php
    $nav = [
        ['dashboard', 'Dashboard'], ['transfer.create', 'Transfer'], ['topup.index', 'Isi Saldo'],
        ['qr.scan', 'Bayar QR'], ['qr.receive', 'Terima QR'], ['transactions.index', 'Laporan'], ['security.index', 'Keamanan'],
    ];
@endphp
<header class="bg-indigo-700 text-white">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-4 flex-wrap">
        <a href="{{ route('dashboard') }}" class="font-bold text-lg">{{ config('app.name') }}</a>
        <nav class="flex gap-1 flex-wrap text-sm flex-1">
            @foreach ($nav as [$r, $label])
                <a href="{{ route($r) }}" class="px-3 py-1.5 rounded-lg {{ (request()->routeIs($r) || ($r === 'transactions.index' && request()->routeIs('transactions.*')) || ($r === 'qr.scan' && request()->routeIs('qr.confirm'))) ? 'bg-white/20' : 'hover:bg-white/10' }}">{{ $label }}</a>
            @endforeach
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-lg bg-amber-400 text-slate-900 font-semibold">Admin</a>
            @endif
        </nav>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="text-sm hover:underline">Keluar ({{ auth()->user()->name }})</button>
        </form>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
    @include('partials.flash')
    @yield('content')
</main>
@endsection
