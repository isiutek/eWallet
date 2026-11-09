@extends('layouts.app')
@section('title', 'Konfirmasi Pembayaran')
@section('content')
<div class="max-w-lg mx-auto rounded-2xl bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold mb-4">Konfirmasi pembayaran</h2>
    <dl class="text-sm space-y-2 mb-4">
        <div class="flex justify-between"><dt class="text-slate-500">Penerima</dt><dd class="font-semibold">{{ $pr->wallet->user->name }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">No. dompet</dt><dd class="font-mono">{{ $pr->wallet->number }}</dd></div>
        @if ($pr->description)<div class="flex justify-between"><dt class="text-slate-500">Keterangan</dt><dd>{{ $pr->description }}</dd></div>@endif
        @if ($pr->amount)<div class="flex justify-between"><dt class="text-slate-500">Nominal</dt><dd class="text-xl font-bold">{{ rupiah($pr->amount) }}</dd></div>@endif
    </dl>
    <form method="POST" action="{{ route('qr.pay') }}" class="space-y-4" onsubmit="this.querySelector('button').disabled=true">@csrf
        <input type="hidden" name="payload" value="{{ $payload }}">
        <input type="hidden" name="idempotency_key" value="{{ $idempotency }}">
        @unless ($pr->amount)
            <label class="block text-sm">Nominal (Rp)<input name="amount" type="number" min="1" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        @endunless
        <p class="text-sm text-slate-500">Saldo Anda: {{ rupiah(auth()->user()->wallet->balance) }}</p>
        @include('partials.pin')
        <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">Bayar</button>
    </form>
</div>
@endsection
