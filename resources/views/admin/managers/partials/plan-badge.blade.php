@if ($plan === 'Ücretli')
    <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Ücretli</span>
@elseif ($plan === 'Ücretsiz')
    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Ücretsiz</span>
@else
    <span class="text-sm text-slate-500">{{ $plan }}</span>
@endif
