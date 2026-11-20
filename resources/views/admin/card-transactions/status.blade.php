<span class="font-semibold {{ $attempt->review_required ? 'text-red-700' : 'text-slate-700' }}">{{ match ($attempt->status) {
    'pending' => 'Sonuç bekleniyor', 'unknown' => 'Sonuç belirsiz', 'succeeded' => 'Başarılı', 'failed' => 'Başarısız', 'cancelled' => 'Kapatıldı', default => $attempt->status,
} }}</span>
@if ($attempt->review_required)<span class="text-xs font-semibold text-red-700">İnceleme gerekli</span>@endif
@if ($attempt->subscription?->checkout_abandoned_at)<span class="text-xs text-amber-700">Ödeme ekranından çıkıldı</span>@endif
<span class="text-xs text-slate-500">{{ $attempt->is_test ? 'Test' : 'Canlı' }}</span>
