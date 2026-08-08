@extends('layouts.app')

@section('title', $type === 'upgrade' ? 'Paket Yükselt' : 'Abonelik Yenile')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('subscriber.dashboard') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Abone Paneline Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $type === 'upgrade' ? 'Paket Yükselt' : 'Abonelik Yenile' }}</h1>
            <p class="mt-1 text-sm text-slate-500">İhtiyacınıza uygun paket ve dönemi seçin.</p>
        </div>

        @if ($currentSubscription && ! $currentSubscription->isExpired())
            <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                Mevcut aboneliğiniz:
                <span class="font-semibold text-slate-900">{{ $currentSubscription->package->name }}</span>
                — {{ $currentSubscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}
                — {{ $currentSubscription->expires_at?->format('d.m.Y') ?? 'Süresiz' }} tarihine kadar aktif.
            </div>
        @endif

        <form method="POST" action="{{ route('subscriber.subscriptions.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Paket Seçin</h2>

                @if ($packages->isEmpty())
                    <p class="text-sm text-slate-500">Şu anda uygun paket bulunmuyor.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($packages as $package)
                            <label class="flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition-colors hover:bg-slate-50 {{ old('package_id', $requestedPackage?->id) == $package->id ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200' }}">
                                <input type="radio" name="package_id" value="{{ $package->id }}" {{ old('package_id', $requestedPackage?->id) == $package->id ? 'checked' : '' }} class="mt-1 text-emerald-600 focus:ring-emerald-500" required>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-slate-900">{{ $package->name }}</span>
                                        @if ($currentSubscription?->package_id === $package->id)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Mevcut Paket</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ $package->description }}</p>
                                </div>
                                <div class="text-right text-sm font-medium text-slate-700">
                                    <span class="block">{{ number_format($package->monthly_price, 2) }} ₺ / ay</span>
                                    <span class="block">{{ number_format($package->yearly_price, 2) }} ₺ / yıl</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('package_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Ödeme Dönemi</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 {{ old('period', 'monthly') === 'monthly' ? 'border-emerald-500 bg-emerald-50' : '' }}">
                        <input type="radio" name="period" value="monthly" {{ old('period', 'monthly') === 'monthly' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500" required>
                        <div>
                            <div class="font-semibold text-slate-900">Aylık</div>
                            <div class="text-sm text-slate-500">Her ay otomatik yenilenir.</div>
                        </div>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 {{ old('period') === 'yearly' ? 'border-emerald-500 bg-emerald-50' : '' }}">
                        <input type="radio" name="period" value="yearly" {{ old('period') === 'yearly' ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-500" required>
                        <div>
                            <div class="font-semibold text-slate-900">Yıllık</div>
                            <div class="text-sm text-slate-500">Tek seferde 12 ay ödeyin.</div>
                        </div>
                    </label>
                </div>
                @error('period')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Ödeme Yöntemi</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 {{ old('payment_method', 'havale') === 'havale' ? 'border-emerald-500 bg-emerald-50' : '' }}">
                        <input type="radio" name="payment_method" value="havale" {{ old('payment_method', 'havale') === 'havale' ? 'checked' : '' }} onchange="togglePaymentMethod(this.value)" class="text-emerald-600 focus:ring-emerald-500" required>
                        <div>
                            <div class="font-semibold text-slate-900">Havale / EFT</div>
                            <div class="text-xs text-slate-500">Banka hesaplarına havale yapın.</div>
                        </div>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:bg-slate-50 {{ old('payment_method') === 'kredi_kartı' ? 'border-emerald-500 bg-emerald-50' : '' }}">
                        <input type="radio" name="payment_method" value="kredi_kartı" {{ old('payment_method') === 'kredi_kartı' ? 'checked' : '' }} onchange="togglePaymentMethod(this.value)" class="text-emerald-600 focus:ring-emerald-500" required>
                        <div>
                            <div class="font-semibold text-slate-900">Kredi Kartı</div>
                            <div class="text-xs text-slate-500">Güvenli ödeme altyapısı (yakında).</div>
                        </div>
                    </label>
                </div>
                @error('payment_method')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror

                <div id="havale-fields" class="mt-6 {{ old('payment_method', 'havale') === 'havale' ? '' : 'hidden' }}">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Siparişinizi oluşturduktan sonra banka hesap bilgilerimizi görüntüleyebilir ve ödemenizi gerçekleştirebilirsiniz.
                    </div>
                </div>

                <div id="kredi-kartı-fields" class="mt-6 {{ old('payment_method') === 'kredi_kartı' ? '' : 'hidden' }}">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Kredi kartı ödeme altyapısı henüz aktif değil. Siparişiniz alınacak ve ödeme yapıldığında dekont/referans bilginizi bu sayfadan girebileceksiniz.
                    </div>
                </div>
            </div>

            <script>
                function togglePaymentMethod(method) {
                    document.getElementById('havale-fields').classList.toggle('hidden', method !== 'havale');
                    document.getElementById('kredi-kartı-fields').classList.toggle('hidden', method !== 'kredi_kartı');
                }
            </script>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('subscriber.dashboard') }}" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Sipariş Oluştur</button>
            </div>
        </form>
    </div>
@endsection
