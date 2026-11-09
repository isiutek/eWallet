<div class="overflow-x-auto">
<table class="w-full text-sm">
    <thead class="text-left text-slate-500 border-b">
        <tr>
            <th class="py-2 pr-3">Tanggal</th>
            @isset($showOwner)<th class="py-2 pr-3">Pemilik</th>@endisset
            <th class="py-2 pr-3">Keterangan</th><th class="py-2 pr-3">Jenis</th>
            <th class="py-2 pr-3 text-right">Nominal</th><th class="py-2 text-right">Saldo</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($items as $t)
        <tr class="border-b last:border-0 hover:bg-slate-50">
            <td class="py-2 pr-3 whitespace-nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
            @isset($showOwner)<td class="py-2 pr-3">{{ $t->wallet->user->name }}</td>@endisset
            <td class="py-2 pr-3">
                @if (! isset($showOwner))<a class="hover:underline" href="{{ route('transactions.show', $t) }}">{{ $t->description }}</a>@else{{ $t->description }}@endif
                <div class="text-xs text-slate-400">{{ $t->reference }}</div>
            </td>
            <td class="py-2 pr-3">{{ $t->typeLabel() }}</td>
            <td class="py-2 pr-3 text-right font-semibold whitespace-nowrap {{ $t->isCredit() ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $t->isCredit() ? '+' : '−' }}{{ rupiah($t->amount) }}
            </td>
            <td class="py-2 text-right whitespace-nowrap text-slate-500">{{ rupiah($t->balance_after) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="py-6 text-center text-slate-400">Belum ada transaksi.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
