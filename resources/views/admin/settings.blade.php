@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
@include('admin._nav')
<form method="POST" class="rounded-2xl bg-white p-6 shadow-sm max-w-2xl space-y-4">@csrf
    <h3 class="font-semibold">Isi saldo</h3>
    <fieldset class="space-y-2 text-sm">
        @foreach (['manual' => 'Manual saja (transfer bank + verifikasi admin)', 'xendit' => 'Otomatis saja (Xendit)', 'both' => 'Keduanya (pengguna memilih)'] as $k => $v)
            <label class="flex gap-2"><input type="radio" name="topup_mode" value="{{ $k }}" @checked($s['topup_mode'] === $k)> {{ $v }}</label>
        @endforeach
    </fieldset>
    <p class="text-xs {{ $xenditConfigured ? 'text-emerald-700' : 'text-rose-600' }}">
        {{ $xenditConfigured ? '✔ Kunci API Xendit terpasang.' : '✖ XENDIT_SECRET_KEY belum diisi di file .env, metode otomatis tidak akan tampil.' }}
        URL webhook: <code>{{ route('webhooks.xendit') }}</code>
    </p>
    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">Min. isi saldo<input type="number" name="topup_min" value="{{ $s['topup_min'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        <label class="block text-sm">Maks. isi saldo<input type="number" name="topup_max" value="{{ $s['topup_max'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    </div>
    <label class="block text-sm">Info rekening untuk transfer manual<textarea name="manual_bank_info" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ $s['manual_bank_info'] }}</textarea></label>
    <h3 class="font-semibold pt-2">Batas transaksi</h3>
    <div class="grid grid-cols-2 gap-4">
        <label class="block text-sm">Min. transfer<input type="number" name="transfer_min" value="{{ $s['transfer_min'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
        <label class="block text-sm">Batas keluar per hari per pengguna (0 = tanpa batas)<input type="number" name="daily_debit_limit" value="{{ $s['daily_debit_limit'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    </div>
    <button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">Simpan</button>
</form>
@endsection
