@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
@include('admin._nav')
<div class="rounded-2xl bg-white p-5 shadow-sm">@include('admin._logs')<div class="mt-3">{{ $logs->links() }}</div></div>
@endsection
