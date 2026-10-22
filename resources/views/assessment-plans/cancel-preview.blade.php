@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('assessment-plans.show', $plan) }}" class="text-sm text-slate-500 hover:underline">← {{ $plan->name }}</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Gelecek Taksitleri İptal Et</h1>
        <p class="mt-1 text-sm text-slate-500">İptal kalıcıdır. Tahsilatı olan taksitler iptal edilemez. Soft-delete edilen borçlardan farklı olarak iptal edilen taksitler yeniden oluşturulmaz.</p>
    </div>

    @if (empty($cancellable))
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">İptal edilecek uygun taksit yok.</div>
    @else
        <form method="POST" action="{{ route('assessment-plans.cancel-future', $plan) }}" class="max-w-2xl space-y-4 rounded-2xl bg-white p-6 shadow-sm">
            @csrf
            <ul class="divide-y divide-slate-100">
                @foreach ($cancellable as $item)
                    <li class="flex items-center gap-3 py-3">
                        <input type="checkbox" name="installment_ids[]" value="{{ $item['id'] }}" checked class="rounded border-slate-300">
                        <div class="flex-1 text-sm">
                            <div class="font-medium text-slate-900">Taksit {{ $item['sequence'] }} — {{ $item['due_date'] }}</div>
                            <div class="text-xs text-slate-500">{{ $item['status'] }} · {{ number_format((float) $item['amount'], 2, ',', '.') }} ₺</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if (! empty($blocked))
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    Tahsilat nedeniyle atlanacak: {{ collect($blocked)->pluck('sequence')->join(', ') }}
                </div>
            @endif
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="confirm" value="1" required class="mt-1">
                Seçili taksitlerin kalıcı olarak iptal edileceğini onaylıyorum.
            </label>
            <div class="flex justify-end gap-3">
                <a href="{{ route('assessment-plans.show', $plan) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Vazgeç</a>
                <button class="rounded-xl bg-red-700 px-5 py-2 text-sm font-semibold text-white">İptal Et</button>
            </div>
        </form>
    @endif
@endsection
