@extends('layouts.app')
@section('title', 'Admin')
@section('content')
@include('admin._nav')
<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
    @foreach ([['Pengguna', $users], ['Total saldo beredar', rupiah($total_balance)], ['Isi saldo manual menunggu', $pending_topups],
               ['Isi saldo masuk hari ini', rupiah($today_topup)], ['Volume transaksi keluar hari ini', rupiah($today_volume)], ['Jumlah mutasi hari ini', $today_count]] as [$l, $v])
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">{{ $l }}</div><div class="text-xl font-bold">{{ $v }}</div></div>
    @endforeach
</div>
<div class="rounded-2xl bg-white p-5 shadow-sm mt-4">
    <h3 class="font-semibold mb-2">Aktivitas terbaru</h3>
    @include('admin._logs', ['logs' => $recent_logs])
</div>
@endsection
