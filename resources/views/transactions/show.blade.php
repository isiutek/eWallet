@extends('layouts.app')
@section('title', 'Detail Transaksi')
@section('content')
<div class="max-w-lg mx-auto rounded-2xl bg-white p-6 shadow-sm">
    <div class="text-center">
        <div class="inline-flex w-12 h-12 items-center justify-center rounded-full {{ $trx->isCredit() ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' }} text-xl">{{ $trx->isCredit() ? '↓' : '↑' }}</div>
        <div class="text-3xl font-bold mt-2 {{ $trx->isCredit() ? 'text-emerald-600' : 'text-rose-600' }}">{{ $trx->isCredit() ? '+' : '−' }}{{ rupiah($trx->amount) }}</div>
        <div class="text-sm text-slate-500">{{ $trx->typeLabel() }} · {{ $trx->status === 'success' ? 'Berhasil' : $trx->status }}</div>
    </div>
    <dl class="mt-6 text-sm divide-y">
        <div class="flex justify-between py-2"><dt class="text-slate-500">Referensi</dt><dd class="font-mono">{{ $trx->reference }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Waktu</dt><dd>{{ $trx->created_at->format('d/m/Y H:i:s') }}</dd></div>
        <div class="flex justify-between py-2 gap-4"><dt class="text-slate-500">Keterangan</dt><dd class="text-right">{{ $trx->description }}</dd></div>
        @if ($trx->counterparty)
            <div class="flex justify-between py-2"><dt class="text-slate-500">{{ $trx->isCredit() ? 'Dari' : 'Ke' }}</dt><dd>{{ $trx->counterparty->user->name }} ({{ $trx->counterparty->number }})</dd></div>
        @endif
        <div class="flex justify-between py-2"><dt class="text-slate-500">Saldo sebelum</dt><dd>{{ rupiah($trx->balance_before) }}</dd></div>
        <div class="flex justify-between py-2"><dt class="text-slate-500">Saldo sesudah</dt><dd>{{ rupiah($trx->balance_after) }}</dd></div>
    </dl>
    <div class="mt-4 flex gap-2 justify-center text-sm">
        <button onclick="window.print()" class="rounded-lg border px-4 py-2">Cetak bukti</button>
        <a href="{{ route('transactions.index') }}" class="rounded-lg border px-4 py-2">Kembali</a>
    </div>
</div>
@endsection
