<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5" aria-labelledby="plan-info-title">
    <h2 id="plan-info-title" class="mb-4 text-sm font-semibold text-slate-700">Plan Bilgileri</h2>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="name" class="mb-2 block text-sm font-medium text-slate-600">Plan Adı</label>
            <input id="name" name="name" value="{{ old('name', $plan->name ?? '') }}" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @unless (isset($plan))
            <div>
                <label for="total_amount" class="mb-2 block text-sm font-medium text-slate-600">Toplam Tutar</label>
                <input id="total_amount" type="number" name="total_amount" step="0.01" min="0.01" value="{{ old('total_amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('total_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @else
            <div>
                <p class="mb-2 text-sm font-medium text-slate-600">Toplam Tutar</p>
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">{{ number_format($plan->total_amount, 2, ',', '.') }} ₺ <span class="ml-2 text-xs font-normal text-slate-500">Değiştirilemez</span></p>
            </div>
        @endunless
        @include('assessment-plans._work-category')
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5" aria-labelledby="description-title">
    <h2 id="description-title" class="mb-4 text-sm font-semibold text-slate-700">Açıklama</h2>
    <div class="rounded-xl bg-slate-50 p-3">
        <p class="mb-1 text-xs font-medium text-slate-500">Oluşacak Borç Açıklaması</p>
        <p id="assessment-description-example" class="break-words text-sm font-semibold text-slate-800" aria-live="polite">{{ old('name', $plan->name ?? '') ?: 'Plan adı' }} 1/{{ old('installment_count', $plan->installment_count ?? 5) }}</p>
        <p class="mt-2 text-xs text-slate-500">Plan adı ve taksit sırasından otomatik oluşur. Açıklama ayrıca değiştirilemez.</p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const name = document.getElementById('name');
            const count = document.getElementById('installment_count');
            const example = document.getElementById('assessment-description-example');
            const update = () => {
                const total = count ? (count.validity.valid && count.value ? count.value : '…') : @json($plan->installment_count ?? 5);
                example.textContent = (name.value.trim() || 'Plan adı') + ' 1/' + total;
            };
            name.addEventListener('input', update);
            count?.addEventListener('input', update);
            update();
        });
    </script>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5" aria-labelledby="debt-info-title">
    <h2 id="debt-info-title" class="mb-4 text-sm font-semibold text-slate-700">İşlem Bilgileri</h2>
    <div class="grid gap-4 md:grid-cols-2">
        @isset($plan)
            <div class="md:col-span-2">
                <p class="mb-2 text-sm font-medium text-slate-600">Dağıtım Yöntemi</p>
                <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ match ($plan->distribution_type) { 'square_meters' => 'Metrekareye göre', 'share_coefficient' => 'Pay çarpanına göre', default => 'Eşit dağıtım' } }}</p>
            </div>
            <div class="md:col-span-2">
                <p class="mb-2 text-sm font-medium text-slate-600">Borçlanacak Kişiler</p>
                <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $plan->target_audience === 'owner_only' ? 'Yalnızca malikler' : 'Kiracı öncelikli' }}</p>
            </div>
            <div><p class="mb-2 text-sm font-medium text-slate-600">Borç Türü</p><p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $plan->due_type->label() }}</p></div>
        @else
                <div>
                    <label for="installment_count" class="mb-2 block text-sm font-medium text-slate-600">Taksit Sayısı</label>
                    <input id="installment_count" type="number" name="installment_count" min="1" max="60" value="{{ old('installment_count', 5) }}" required class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
                    @error('installment_count')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            <div>
                <label for="distribution_type" class="mb-2 block text-sm font-medium text-slate-600">Dağıtım Yöntemi</label>
                <select id="distribution_type" name="distribution_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    @foreach (['equal' => 'Eşit dağıtım', 'square_meters' => 'Metrekareye göre', 'share_coefficient' => 'Pay çarpanına göre'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('distribution_type', 'equal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('distribution_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0 md:col-span-2">
                @include('assessment-plans._distribution-preview')
            </div>
            <fieldset class="min-w-0 md:col-span-2">
                <legend class="mb-2 text-sm font-medium text-slate-600">Borçlanacak Kişiler</legend>
                <div class="flex gap-3">
                    @foreach (['tenant_priority' => ['Kiracı Öncelikli', 'Varsa kiracıya, yoksa sahibine'], 'owner_only' => ['Sadece Sahipler', 'Tüm borçlar kat maliklerine']] as $value => $option)
                        <label class="min-w-0 flex-1 cursor-pointer">
                            <input type="radio" name="target_audience" value="{{ $value }}" @checked(old('target_audience', 'owner_only') === $value) class="peer sr-only" required>
                            <span class="block h-full rounded-xl border-2 border-slate-200 p-3 transition-all hover:bg-slate-50 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600"><span class="block text-sm font-semibold text-slate-800">{{ $option[0] }}</span><span class="mt-1 block text-xs text-slate-500">{{ $option[1] }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('target_audience')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="due_type" class="mb-2 block text-sm font-medium text-slate-600">Borç Türü <span class="text-red-500">*</span></label>
                <select id="due_type" name="due_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    @foreach (\App\Models\AssessmentPlan::debtTypes() as $type)
                        <option value="{{ $type->value }}" data-selects-category="{{ $type->selectsCategory() ? '1' : '0' }}" @selected(old('due_type', 'masraf_yansitma') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('due_type')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endisset
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5" aria-labelledby="calendar-title">
    <h2 id="calendar-title" class="mb-4 text-sm font-semibold text-slate-700">Takvim</h2>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="generate_day" class="mb-2 block text-sm font-medium text-slate-600">Oluşturma Günü (1–28)</label>
            <input id="generate_day" type="number" name="generate_day" min="1" max="28" value="{{ old('generate_day', $plan->generate_day ?? 1) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <p class="mt-2 text-xs text-slate-500">Zamanlı planda her taksit, vade ayının bu gününde oluşturulur.</p>
            @error('generate_day')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="due_day" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Günü</label>
            @isset($plan)
                <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $plan->installments->first()?->due_date?->day ?? '—' }}</p>
                <p class="mt-2 text-xs text-slate-500">Mevcut taksitlerin vadeleri korunur.</p>
            @else
                <input id="due_day" type="number" name="due_day" min="1" max="28" value="{{ old('due_day', 10) }}" required aria-describedby="due-day-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <p id="due-day-help" class="mt-2 text-xs text-slate-500">Her taksidin son ödeme günü (1–28).</p>
                @error('due_day')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @endisset
        </div>
        @isset($plan)
            <div class="col-span-2">
                <p class="mb-3 text-xs text-slate-500">Taksit sayısı ve vadeler değiştirilemez.</p>
                <dl class="grid grid-cols-2 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Başlangıç Ayı</dt><dd class="mt-1 text-slate-700">{{ $plan->installments->first()?->due_date?->format('m.Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Son Taksit Tarihi</dt><dd class="mt-1 text-slate-700">{{ $plan->installments->last()?->due_date?->format('d.m.Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Taksit Sayısı</dt><dd class="mt-1 text-slate-700">{{ $plan->installment_count }}</dd></div>
                    @if ($plan->installments->count() > 1)
                        <div><dt class="text-xs text-slate-500">Taksit Aralığı (ay)</dt><dd class="mt-1 text-slate-700">{{ (int) $plan->installments->first()->due_date->copy()->startOfMonth()->diffInMonths($plan->installments->get(1)->due_date->copy()->startOfMonth()) }}</dd></div>
                    @endif
                </dl>
            </div>
        @else
                <div>
                    <label for="due_interval_months" class="mb-2 block text-sm font-medium text-slate-600">Taksit Aralığı (ay)</label>
                    <input id="due_interval_months" type="number" name="due_interval_months" min="1" max="12" value="{{ old('due_interval_months', 1) }}" required class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm">
                    @error('due_interval_months')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            <div class="col-span-2">
                <label for="start_period" class="mb-2 block text-sm font-medium text-slate-600">Başlangıç Ayı</label>
                <input id="start_period" type="month" name="start_period" value="{{ old('start_period', now()->addMonth()->format('Y-m')) }}" required class="min-w-0 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <p class="mt-2 text-xs text-slate-500">İlk taksit bu ay başlar. Diğer taksitler seçilen ay aralığıyla devam eder.</p>
                @error('start_period')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endisset
    </div>
</section>
<section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5" aria-labelledby="generation-title">
    <h2 id="generation-title" class="mb-4 text-sm font-semibold text-slate-700">Oluşturma ve Durum</h2>
    <div class="grid gap-4 md:grid-cols-2">
        @unless (isset($plan))
            <fieldset class="min-w-0 md:col-span-2">
                <legend class="mb-3 text-sm font-semibold text-slate-700">Oluşturma Yöntemi</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['on_schedule' => ['Zamanı geldikçe oluştur', 'Taksitler, vade ayının oluşturma gününde oluşur.'], 'create_all_now' => ['Tümünü şimdi oluştur', 'Seçilen taksitler son onayınızla oluşturulur.']] as $value => $option)
                        <label class="cursor-pointer">
                            <input type="radio" name="generation_mode" value="{{ $value }}" @checked(old('generation_mode', 'on_schedule') === $value) class="peer sr-only">
                            <span class="block h-full rounded-xl border border-slate-200 p-3 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600"><span class="block text-sm font-semibold text-slate-800">{{ $option[0] }}</span><span class="mt-1 block text-xs text-slate-500">{{ $option[1] }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('generation_mode')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </fieldset>
        @else
            <div class="md:col-span-2">
                <p class="mb-2 text-sm font-medium text-slate-600">Oluşturma Yöntemi</p>
                <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">{{ $plan->createsOnSchedule() ? 'Zamanı geldikçe oluştur' : 'Tümünü şimdi oluştur' }}</p>
            </div>
        @endunless
        <p class="text-xs text-slate-500 md:col-span-2">Geçmiş taksitler ve oluşturulacak borçlar, Kaydet sonrası işlem özetinde seçilir. Mevcut borçlar değişmez.</p>
        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3 md:col-span-2">
            <div><label for="is_active" class="text-sm font-medium text-slate-700">Plan Durumu</label><p class="mt-1 text-xs text-slate-500">Pasif planda yeni borç oluşturulmaz.</p></div>
            <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" role="switch" aria-label="Plan aktif" @checked(old('is_active', $plan->is_active ?? true)) class="peer sr-only">
                <span aria-hidden="true" class="h-6 w-11 rounded-full bg-slate-300 peer-checked:bg-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></span>
            </label>
        </div>
        @error('is_active')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</section>
<div class="flex flex-wrap items-center justify-end gap-3">
    <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar kayıt yapılmaz.</p>
    <a href="{{ isset($plan) ? route('assessment-plans.show', $plan) : route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</a>
    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Kaydet</button>
</div>
