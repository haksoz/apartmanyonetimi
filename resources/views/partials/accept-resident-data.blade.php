<label class="flex items-start gap-2 text-sm text-slate-600">
    <input type="checkbox" name="accept_resident_data" value="1" class="mt-0.5" @checked(old('accept_resident_data')) required>
    <span>
        <a href="{{ route('legal.resident-data') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline">Daire sakini verisi</a>
        bildirimine göre bu apartmandaki daire sakini bilgilerini girmeye yetkiliyim.
        <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-900 underline">Gizlilik ve KVKK aydınlatması</a>
    </span>
</label>
@error('accept_resident_data')
    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
@enderror
