<div class="flex gap-2 flex-wrap mb-4 text-sm">
    @foreach ([['admin.dashboard', 'Ringkasan'], ['admin.topups', 'Verifikasi Isi Saldo'], ['admin.transactions', 'Semua Transaksi'], ['admin.users', 'Pengguna'], ['admin.settings', 'Pengaturan'], ['admin.audit', 'Audit Log']] as [$r, $l])
        <a href="{{ route($r) }}" class="px-3 py-1.5 rounded-lg {{ request()->routeIs($r) ? 'bg-slate-800 text-white' : 'bg-white hover:bg-slate-50' }}">{{ $l }}</a>
    @endforeach
</div>
