@extends('layouts.app')
@section('title', 'Ücretli Kullanım Geçmişi')
@section('content')
    <a href="{{ route('admin.checkout-journeys.index') }}" class="text-sm font-semibold text-emerald-700">← Ücretli Kullanıma Geçişler</a>
    <h1 class="mt-3 text-2xl font-bold text-slate-900">{{ $journey->reference }} · Ücretli Kullanım Geçmişi</h1>
    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 text-sm">
        <p class="break-words font-semibold">{{ $journey->user?->name ?? 'Silinmiş müşteri' }} · {{ $journey->apartment?->name ?? 'Apartman bilgisi yok' }}</p>
        <p class="mt-2">{{ $journey->progress_label }} · Son aşama: {{ $journey->stage_label }}</p>
        @if ($journey->user)<a href="{{ route('admin.customers.show', $journey->user) }}" class="mt-3 inline-block font-semibold text-emerald-700">Müşteriye git</a>@endif
        @if ($journey->order?->order_number && ! $journey->order->trashed())<a href="{{ route('admin.orders.show', $journey->order) }}" class="ml-3 font-semibold text-emerald-700">{{ $journey->order->order_number }}</a>@endif
    </section>
    <h2 class="mt-6 text-lg font-semibold">Adım geçmişi</h2>
    <p class="mt-1 text-xs text-slate-500">Görüntülenen değerler ön seçimler içerebilir. Kullanıcının yaptığı değişiklikler ayrıca kaydedilir.</p>
    <ol class="mt-3 space-y-3">
        @foreach ($journey->events as $event)
            @php
                $details = $event->details ?? [];
                $eventStage = $details['stage'] ?? match ($event->type) {
                    'billing_saved' => 'billing',
                    'card_screen', 'card_expired' => 'payment',
                    default => null,
                };
                if ($event->source === 'browser' && $eventStage) {
                    $stageFields = match ($eventStage) {
                        'plan' => ['period', 'amount'],
                        'billing' => ['billing_mode', 'billing_profile_label', 'billing_party_type'],
                        'payment' => ['payment_method', 'payment_method_automatic'],
                        default => [],
                    };
                    $details = array_intersect_key($details, array_flip(array_merge($stageFields, ['fields'])));
                }
                // Older browser entries also included defaults from later, hidden panels.
                if ($event->source === 'browser' && ($details['stage'] ?? null) === 'plan') {
                    unset($details['billing_mode'], $details['billing_profile_id'], $details['billing_profile_label'], $details['billing_party_type']);
                }
                if ($event->source === 'browser' && in_array($details['stage'] ?? null, ['plan', 'billing'], true)) {
                    unset($details['payment_method']);
                }
                $stages = ['plan' => 'Dönem seçimi', 'billing' => 'Fatura bilgileri', 'payment' => 'Ödeme', 'distance' => 'Satış sözleşmesi', 'pre' => 'Ön bilgilendirme'];
                $values = ['monthly' => 'Aylık', 'yearly' => 'Yıllık', 'kredi_kartı' => 'Kredi kartı', 'havale' => 'Havale / EFT', 'existing' => 'Kayıtlı profil', 'new' => 'Yeni profil', 'individual' => 'Bireysel', 'corporate' => 'Kurumsal', 'other' => 'Diğer', 'pending' => 'Bekliyor', 'unknown' => 'Sonuç belirsiz', 'active' => 'Etkinleşti', 'cancelled' => 'İptal edildi', 'succeeded' => 'Başarılı', 'failed' => 'Başarısız', 'success' => 'Başarılı', 'duplicate' => 'Tekrarlanan bildirim', 'review' => 'İnceleme gerekli'];
                $fields = ['period' => 'Dönem', 'payment_method' => 'Ödeme yöntemi', 'billing_mode' => 'Fatura seçimi', 'billing_profile_id' => 'Fatura profili kayıt no', 'billing_profile_label' => 'Profil adı', 'billing_party_type' => 'Fatura türü'];
                $action = match ($event->type) {
                    'viewed' => 'görüntülendi',
                    'changed' => 'Seçim değiştirildi',
                    'accepted' => 'Onay verildi',
                    'billing_saved' => 'Profil kaydedildi',
                    'validation_failed' => 'Doğrulama başarısız',
                    'closed' => 'Kullanıcı çıktı',
                    'timed_out' => 'Süre doldu',
                    'timeout_prompt' => 'Süre uzatma penceresi görüntülendi',
                    'timeout_dismissed' => 'Kullanıcı süre uzatma penceresinden işlemi kapattı',
                    'renewed' => 'Süre uzatıldı',
                    default => null,
                };
                $stageTitle = $eventStage && $action ? 'Aşama: '.($stages[$eventStage] ?? $eventStage).' ('.$action.')' : null;
                if ($event->type === 'accepted' && $eventStage) {
                    $stageTitle = 'Aşama: '.($stages[$eventStage] ?? $eventStage);
                }
            @endphp
            <li class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <strong>{{ $stageTitle ?? match ($event->type) { 'timeout_closed' => 'Süre yenileme sorusuna yanıt verilmedi; işlem kapatıldı', 'timed_out' => 'Form aşamasının süresi doldu', 'renewed' => 'Kullanıcı süreyi 2 dakika uzattı', 'started' => 'Geçiş ekranı açıldı', 'viewed' => 'Aşama: '.($stages[$details['stage'] ?? ''] ?? ($details['stage'] ?? 'Belirtilmedi')).' (görüntülendi)', 'changed' => 'Seçim değiştirildi', 'accepted' => 'Sözleşme onayı seçildi', 'sale_accepted' => 'Satış sözleşmesi onayları kaydedildi', 'closed' => 'Kullanıcı ekrandan çıktı', 'billing_saved' => 'Fatura profili kaydedildi', 'validation_failed' => 'Form doğrulaması başarısız', 'submitted' => 'Ödeme talebi oluşturuldu', 'order_updated' => 'Sipariş durumu güncellendi', 'payment_attempt' => 'Ödeme denemesi güncellendi', 'payment_notification' => 'Ödeme bildirimi alındı', 'payment_proof' => 'Havale bilgisi gönderildi', 'superseded' => 'Müşteri yeni deneme başlattı; önceki süreç kapatıldı', 'card_expired' => 'PayTR ödeme ekranının süresi doldu', 'card_screen' => 'Kart ödeme ekranı görüntülendi', 'historical' => 'Önceki ödeme kaydı', default => $event->type } }}</strong>
                    <time class="text-xs text-slate-500">{{ $event->created_at->format('d.m.Y H:i:s') }}</time>
                </div>
                @if ($event->type === 'historical')<p class="mt-2 text-slate-500">Bu kayıtta form adımları izlenmiyordu. Mevcut ödeme denemeleri aşağıdadır.</p>@endif
                @if ($event->type === 'accepted' && $eventStage)<p class="mt-2 font-semibold text-slate-700">{{ $stages[$eventStage] ?? $eventStage }} (Onay verildi)</p>@endif
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 break-words text-slate-600">
                    @if ($event->type !== 'viewed' && isset($details['stage']))<span>Aşama: {{ $stages[$details['stage']] ?? $details['stage'] }}</span>@endif
                    @if (isset($details['field']))<span>Değişen: {{ $fields[$details['field']] ?? $details['field'] }}</span>@endif
                    @foreach ($fields as $key => $label)
                        @if ($key !== 'billing_profile_id' && isset($details[$key]))<span>{{ $label }}: {{ $values[$details[$key]] ?? $details[$key] }}@if ($key === 'payment_method' && !empty($details['payment_method_automatic'])) (Tek seçim otomatik tanımlandı)@endif</span>@endif
                    @endforeach
                    @if (isset($details['amount']))<span>Tutar: {{ number_format((float) $details['amount'], 2, ',', '.') }} ₺</span>@endif
                    @foreach (['provider_status' => 'Bildirilen sonuç', 'status' => 'Durum', 'outcome' => 'Sonuç', 'order_number' => 'Sipariş', 'token_failure_code' => 'Ödeme ekranı hatası', 'failed_reason_code' => 'Hata kodu', 'reason' => 'İnceleme nedeni', 'deliveries' => 'Teslim sayısı'] as $key => $label)
                        @if (isset($details[$key]))<span class="break-all">{{ $label }}: {{ $values[$details[$key]] ?? $details[$key] }}@if ($key === 'payment_method' && !empty($details['payment_method_automatic'])) (Tek seçim otomatik tanımlandı)@endif</span>@endif
                    @endforeach
                    @if (!empty($details['review_required']))<span class="text-red-700">İnceleme gerekli</span>@endif
                    @if (isset($details['is_test']))<span>{{ $details['is_test'] ? 'Test' : 'Canlı' }}</span>@endif
                    @if (isset($details['fields']))<span>Kontrol edilecek alanlar: {{ implode(', ', array_map(fn ($field) => $fields[$field] ?? match ($field) { 'accept_sales' => 'Sözleşme onayları', 'quote_signature' => 'Güncel fiyat', 'apartment_ids' => 'Apartman seçimi', 'payer_name' => 'Ödeyen adı', 'payer_email' => 'Ödeyen e-postası', 'payer_phone' => 'Ödeyen telefonu', 'payer_address' => 'Ödeyen adresi', default => $field }, $details['fields'])) }}</span>@endif
                </div>
            </li>
        @endforeach
    </ol>
    <h2 class="mt-6 text-lg font-semibold">Ödeme denemeleri</h2>
    <div class="mt-3 space-y-3">
        @forelse ($journey->order?->paymentAttempts ?? [] as $attempt)
            <article class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap gap-3"><strong>{{ $attempt->transaction_number }}</strong><span>{{ $attempt->provider === 'paytr' ? 'Kredi kartı' : 'Havale / EFT' }}</span>@include('admin.card-transactions.status')</div>
                @if ($attempt->provider === 'paytr')<a href="{{ route('admin.card-transactions.show', $attempt) }}" class="mt-2 inline-block font-semibold text-emerald-700">Bildirim ve hata detayları ({{ $attempt->notifications->count() }})</a>@endif
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">Ödeme denemesi başlatılmadı.</p>
        @endforelse
    </div>
@endsection
