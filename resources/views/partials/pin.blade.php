<label class="block text-sm">PIN transaksi
    <input name="pin" type="password" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
</label>
@unless (auth()->user()->hasPin())
    <p class="text-xs text-rose-600">Anda belum membuat PIN. <a class="underline" href="{{ route('security.index') }}">Buat PIN</a></p>
@endunless
