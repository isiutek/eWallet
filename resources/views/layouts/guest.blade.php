@extends('layouts.base')
@section('body')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 text-white text-2xl font-bold">₩</div>
            <h1 class="mt-3 text-2xl font-bold">{{ config('app.name') }}</h1>
        </div>
        <div class="bg-white rounded-2xl shadow p-6">
            @include('partials.flash')
            @yield('content')
        </div>
    </div>
</div>
@endsection
