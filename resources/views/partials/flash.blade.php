@if (session('status'))
    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
        @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif
