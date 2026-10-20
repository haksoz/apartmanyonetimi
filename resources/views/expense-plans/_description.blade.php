<div class="min-w-0 md:col-span-2">
    <div class="grid gap-4 md:grid-cols-2">
        <div class="min-w-0 rounded-xl border border-emerald-100 bg-emerald-50 p-3">
            <p class="mb-2 text-sm font-medium text-emerald-700">Oluşacak Gider Açıklaması</p>
            <p id="description-preview" class="break-words text-sm font-medium text-emerald-900" aria-live="polite">Kategori ve tarih seçin.</p>
            <p class="mt-1 text-xs text-emerald-700">Dönem değişir, ek metniniz korunur.</p>
        </div>
        <div class="min-w-0">
            <label for="description_suffix" class="mb-2 block text-sm font-medium text-slate-600">Açıklamaya Eklenecek Metin <span class="font-normal text-slate-400">(opsiyonel)</span></label>
            <input id="description_suffix" name="description_suffix" value="{{ old('description_suffix', $plan->description_suffix ?? '') }}" maxlength="100" placeholder="Örn. ortak alan tüketimi" aria-describedby="description-suffix-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
            <p id="description-suffix-help" class="mt-1 text-xs text-slate-500">Açıklamanın sonuna eklenir. En fazla 100 karakter.</p>
            @error('description_suffix')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const suffix = document.getElementById('description_suffix');
        const preview = document.getElementById('description-preview');
        const category = document.getElementById('category_id');
        const start = document.getElementById('start_date');
        const legacyDescription = document.getElementById('description');
        const savedCategory = @json(isset($plan) ? ($plan->category?->name ?: $plan->name) : '');
        const savedStart = @json(isset($plan) ? $plan->start_date->format('Y-m-d') : '');
        const months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        const updatePreview = () => {
            const [year, month] = (start?.value ?? savedStart).split('-');
            const categoryName = category ? (category.value ? category.selectedOptions[0].textContent.trim() : '') : savedCategory;
            const automatic = categoryName && year && months[Number(month) - 1]
                ? year + ' ' + months[Number(month) - 1] + ' - ' + categoryName
                : '';
            const base = legacyDescription?.value.trim() || automatic;
            preview.textContent = base
                ? base + (suffix.value.trim() ? ' ' + suffix.value.trim() : '')
                : 'Kategori ve tarih seçin.';
        };
        suffix.addEventListener('input', updatePreview);
        category?.addEventListener('change', updatePreview);
        start?.addEventListener('input', updatePreview);
        legacyDescription?.addEventListener('input', updatePreview);
        updatePreview();
    });
</script>
