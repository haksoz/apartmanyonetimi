@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('financial-plans.index') }}" class="text-sm text-slate-500 hover:underline">← Finansal Planlama</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $plan->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $plan->account?->name }} · {{ number_format((float) $plan->amount, 2, ',', '.') }} ₺ / ay
                · {{ $plan->is_active ? 'Aktif' : 'Pasif' }}
            </p>
        </div>
        @if ($plan->is_active)
            <form method="POST" action="{{ route('expense-plans.deactivate', $plan) }}">
                @csrf
                @method('PATCH')
                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Planı Durdur</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-8 max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('expense-plans.update', $plan) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Plan adı</label>
                <input name="name" value="{{ old('name', $plan->name) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Açıklama</label>
                <input name="description" value="{{ old('description', $plan->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Tutar</label>
                    <input type="number" step="0.01" name="amount" value="{{ old('amount', $plan->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Bitiş</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $plan->end_date?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-600">Oluşturma günü</label>
                    <input type="number" min="1" max="28" name="generate_day" value="{{ old('generate_day', $plan->generate_day) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                </div>
            </div>
            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 1 : 0 }}">
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Güncelle</button>
        </form>
    </div>

    <h2 class="mb-3 text-base font-semibold text-slate-900">Plan Dönemleri</h2>
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden max-w-3xl">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Dönem</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Durum</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($periods as $p)
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $p['label'] }}</td>
                        <td class="px-5 py-3">
                            @if ($p['status'] === 'complete')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Oluşturuldu</span>
                            @elseif ($p['status'] === 'stopped')
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Durduruldu</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Oluşturulmadı</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($p['status'] === 'complete' && $p['expense_id'])
                                <a href="{{ route('expenses.show', $p['expense_id']) }}" class="text-xs underline">Görüntüle</a>
                            @elseif ($plan->is_active && $p['status'] === 'not_generated')
                                <form method="POST" action="{{ route('expense-plans.generate-month', $plan) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="period" value="{{ $p['period'] }}">
                                    <button class="text-xs underline">Oluştur</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
