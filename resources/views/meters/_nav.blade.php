<div class="mb-6 space-y-4">
    <h1 class="text-2xl font-bold text-slate-950">Sayaç ve Pay Ölçer</h1>
    <nav class="flex flex-wrap gap-2" aria-label="Sayaç modülü">
        @foreach(['index' => 'Tüketim Girişi', 'history' => 'Tüketim Geçmişi'] as $page => $label)
            <a href="{{ route('meters.'.$page) }}" class="rounded-xl border px-4 py-2 text-sm font-semibold {{ request()->routeIs('meters.'.$page) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-white text-slate-700 border-slate-300' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>
@if($errors->any())
    <div role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
