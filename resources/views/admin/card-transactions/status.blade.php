<span class="font-semibold {{ $attempt->review_required ? 'text-red-700' : 'text-slate-700' }}">{{ match (true) {
    $attempt->review_required => 'İnceleme gerekli',
    $attempt->status === 'succeeded' => 'Başarılı',
    $attempt->status === 'failed' => 'Başarısız',
    $attempt->subscription?->checkout_abandoned_at !== null => 'Yarım bırakıldı',
    $attempt->status === 'cancelled' => 'Kapatıldı',
    $attempt->status === 'unknown' => 'Sonuç belirsiz',
    default => 'Sonuç bekleniyor',
} }}</span>
@if ($attempt->subscription?->checkout_abandoned_at)
    <span class="text-xs text-amber-700">Ödeme ekranından çıkıldı</span>
    @if (in_array($attempt->status, ['pending', 'unknown'], true))<span class="text-xs text-slate-500">PayTR bildirimi alınmadı</span>@endif
@endif
<span class="text-xs text-slate-500">{{ $attempt->is_test ? 'Test' : 'Canlı' }}</span>
