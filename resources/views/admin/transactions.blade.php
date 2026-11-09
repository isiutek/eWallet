@extends('layouts.app')
@section('title', 'Semua Transaksi')
@section('content')
@include('admin._nav')
@include('partials.report-filter')
<div class="rounded-2xl bg-white p-4 shadow-sm">
    @include('partials.trx-table', ['items' => $transactions, 'showOwner' => true])
    <div class="mt-3">{{ $transactions->links() }}</div>
</div>
@endsection
