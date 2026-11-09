<form method="GET" class="rounded-2xl bg-white p-4 shadow-sm grid gap-3 md:grid-cols-6 items-end text-sm">
    <label>Dari<input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label>Sampai<input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <label>Jenis<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Semua</option>
        @foreach (\App\Models\Transaction::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
    </select></label>
    <label>Arah<select name="direction" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Semua</option>
        <option value="credit" @selected(($filters['direction'] ?? '') === 'credit')>Masuk</option>
        <option value="debit" @selected(($filters['direction'] ?? '') === 'debit')>Keluar</option>
    </select></label>
    <label>Cari<input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Referensi / keterangan" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></label>
    <div class="flex gap-2"><button class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2">Filter</button>
        <button name="export" value="csv" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">CSV</button></div>
</form>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">Total masuk</div><div class="text-lg font-bold text-emerald-600">{{ rupiah($summary['in']) }}</div></div>
    <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">Total keluar</div><div class="text-lg font-bold text-rose-600">{{ rupiah($summary['out']) }}</div></div>
    <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">Selisih</div><div class="text-lg font-bold">{{ rupiah($summary['net']) }}</div></div>
    <div class="rounded-2xl bg-white p-4 shadow-sm"><div class="text-xs text-slate-500">Jumlah transaksi</div><div class="text-lg font-bold">{{ $summary['count'] }}</div></div>
</div>
