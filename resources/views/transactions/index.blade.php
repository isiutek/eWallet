@extends('layouts.app')
@section('title', 'Laporan Transaksi')
@section('content')
<h2 class="text-xl font-semibold mb-4">Laporan transaksi</h2>
@include('partials.report-filter')
<div class="rounded-2xl bg-white p-4 shadow-sm">
    @include('partials.trx-table', ['items' => $transactions])
    <div class="mt-3">{{ $transactions->links() }}</div>
</div>
@endsection
