<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dompet') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none}</style>
    @stack('head')
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    @yield('body')
    @stack('scripts')
</body>
</html>
