<section class="mb-8 rounded-2xl bg-white p-6 shadow-sm" aria-label="Plan bilgileri">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-slate-900">Plan Bilgileri</h2>
        <span @class([
            'rounded-full px-3 py-1 text-xs font-semibold',
            'bg-emerald-50 text-emerald-700' => $status === 'Aktif',
            'bg-amber-50 text-amber-700' => $status === 'Süresi Doldu',
            'bg-slate-100 text-slate-600' => $status === 'Pasif',
        ])>{{ $status }}</span>
    </div>
    <dl class="grid gap-x-8 gap-y-4 md:grid-cols-2">
        @foreach ($details as $label => $value)
            <div class="min-w-0 border-t border-slate-100 pt-3">
                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">
                    @if ($value === 'Zamanı geldikçe oluştur')
                        <span class="inline-flex items-center gap-2">@include('financial-plans._clock') {{ $value }}</span>
                    @else
                        {{ $value === null || $value === '' ? '—' : $value }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</section>
