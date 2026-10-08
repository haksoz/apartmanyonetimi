@php
    $acceptances = $subscription->legalAcceptances->filter(function ($acceptance) use ($subscription) {
        return (int) $acceptance->user_subscription_id === (int) $subscription->id
            && in_array($acceptance->document_key, \App\Support\LegalConsent::SALE_DOCUMENTS, true);
    });
@endphp
<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Sözleşme kabul kayıtları</h2>
    @if ($acceptances->isEmpty())
        <p class="mt-3 text-sm text-slate-600">Bu siparişe bağlı sözleşme kabul kaydı yok.</p>
    @else
        <p class="mt-1 text-sm text-slate-500">Yalnızca bu sipariş oluşturulurken kaydedilen kabuller.</p>
        <dl class="mt-4 divide-y divide-slate-200 text-sm">
            @foreach ($acceptances as $acceptance)
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-slate-500">Belge</dt>
                        <dd class="font-medium text-slate-900">{{ \App\Support\LegalConsent::label($acceptance->document_key) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Sürüm</dt>
                        <dd class="font-medium text-slate-900">{{ $acceptance->document_version }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Kabul tarihi</dt>
                        <dd class="font-medium text-slate-900">{{ $acceptance->accepted_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    @if (filled($acceptance->accepted_content))
                        <div class="sm:col-span-3 pt-2">
                            <details class="rounded-lg border border-slate-200 bg-slate-50">
                                <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700">Kabul edilen metin</summary>
                                <div class="whitespace-pre-wrap border-t border-slate-200 px-3 py-3 font-medium text-slate-900">{{ $acceptance->accepted_content }}</div>
                            </details>
                        </div>
                    @else
                        <p class="text-slate-600 sm:col-span-3">Bu kabul sırasında belge metni saklanmamış.</p>
                    @endif
                </div>
            @endforeach
        </dl>
    @endif
</section>
