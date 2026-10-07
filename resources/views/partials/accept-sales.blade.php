<label class="flex items-start gap-2 text-sm text-slate-600">
    <input type="checkbox" name="accept_sales" value="1" class="mt-0.5" @checked(old('accept_sales'))>
    <span>
        <a href="{{ route('legal.distance-sales') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline">Mesafeli satış sözleşmesini</a>
        ve
        <a href="{{ route('legal.pre-information') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline">ön bilgilendirme formunu</a>
        okudum, kabul ediyorum.
    </span>
</label>
@error('accept_sales')
    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
@enderror
