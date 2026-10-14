<form method="POST" action="{{ route('current-apartment.update') }}" class="rounded-2xl bg-white p-5 shadow-sm">
    @csrf
    <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
    <input type="hidden" name="destination" value="{{ $destination }}">
    <div class="text-lg font-semibold text-slate-950">{{ $apartment->name }}</div>
    <div class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</div>
    <div class="mt-4 text-sm text-slate-500">{{ $apartment->unit_count }} daire</div>
    <button type="submit" class="mt-5 w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">{{ $action }}</button>
</form>
