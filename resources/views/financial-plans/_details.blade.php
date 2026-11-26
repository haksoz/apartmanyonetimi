@if($collapsible ?? false)
<details class="group mb-8 rounded-2xl bg-white p-6 shadow-sm" aria-label="Plan bilgileri">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
        <span class="text-base font-semibold text-slate-900">Plan Bilgileri</span>
        <span class="ml-auto rounded-full px-3 py-1 text-xs font-semibold {{ $status === 'Aktif' ? 'bg-emerald-50 text-emerald-700' : ($status === 'Süresi Doldu' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $status }}</span>
        <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </summary>
@else
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
    @endif
    <dl class="mt-4 grid gap-x-8 gap-y-4 md:grid-cols-2">
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
@if($collapsible ?? false)</details>@else</section>@endif
