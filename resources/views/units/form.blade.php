<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        {{-- Unit Number --}}
        <div>
            <label for="unit_no" class="mb-2 block text-sm font-semibold text-slate-700">Daire No</label>
            <input id="unit_no" name="unit_no" value="{{ old('unit_no', $unit?->unit_no) }}" required
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                   placeholder="Örn: 1, 2A, B-5">
            @error('unit_no')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>

        {{-- Floor and Block --}}
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="floor" class="mb-2 block text-sm font-semibold text-slate-700">Kat</label>
                <input id="floor" name="floor" value="{{ old('floor', $unit?->floor) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                       placeholder="Örn: 1, 2, Zemin">
                @error('floor')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="block" class="mb-2 block text-sm font-semibold text-slate-700">Blok</label>
                <input id="block" name="block" value="{{ old('block', $unit?->block) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                       placeholder="Örn: A, B, 1">
                @error('block')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Square Meters and Share Coefficient --}}
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="square_meters" class="mb-2 block text-sm font-semibold text-slate-700">Metrekare (m²)</label>
                <input id="square_meters" name="square_meters" type="number" step="0.01" min="0"
                       value="{{ old('square_meters', $unit?->square_meters) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                       placeholder="Örn: 120">
                @error('square_meters')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="share_coefficient" class="mb-2 block text-sm font-semibold text-slate-700">Pay Çarpanı</label>
                <input id="share_coefficient" name="share_coefficient" type="number" step="0.0001" min="0"
                       value="{{ old('share_coefficient', $unit?->share_coefficient) }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                       placeholder="Örn: 1.0000, 0.6667">
                <p class="mt-1 text-xs text-slate-500">Aidat dağıtımında kullanılacak oran</p>
                @error('share_coefficient')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
            {{ $buttonText }}
        </button>
    </div>
</form>
