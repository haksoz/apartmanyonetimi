@php $isEdit = !is_null($plan); @endphp

{{-- Errors --}}
@if ($errors->any())
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Plan Adı</label>
    <input type="text" name="name" value="{{ old('name', $plan?->name) }}"
           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Yıl</label>
        <input type="number" name="year" value="{{ old('year', $plan?->year ?? now()->year) }}"
               min="2000" max="2100"
               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Vade Günü (1-28)</label>
        <input type="number" name="due_day" value="{{ old('due_day', $plan?->due_day ?? 1) }}"
               min="1" max="28"
               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none" required>
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Tutar Türü</label>
    <div class="flex gap-4" id="amount-type-group">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="amount_type" value="monthly" id="amount_monthly"
                   {{ old('amount_type', $plan?->amount_type ?? 'monthly') === 'monthly' ? 'checked' : '' }}>
            <span class="text-sm text-slate-700">Aylık tutar</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="amount_type" value="yearly" id="amount_yearly"
                   {{ old('amount_type', $plan?->amount_type) === 'yearly' ? 'checked' : '' }}>
            <span class="text-sm text-slate-700">Yıllık toplam</span>
        </label>
    </div>
</div>

<div id="field-monthly" class="{{ old('amount_type', $plan?->amount_type ?? 'monthly') === 'monthly' ? '' : 'hidden' }}">
    <label class="block text-sm font-medium text-slate-700 mb-1">Aylık Tutar (TL)</label>
    <input type="number" name="monthly_amount" value="{{ old('monthly_amount', $plan?->monthly_amount) }}"
           step="0.01" min="0.01"
           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
    <p class="mt-1 text-xs text-slate-500">Her ay bu tutar dairelere dağıtılır.</p>
</div>

<div id="field-yearly" class="{{ old('amount_type', $plan?->amount_type ?? 'monthly') === 'yearly' ? '' : 'hidden' }}">
    <label class="block text-sm font-medium text-slate-700 mb-1">Yıllık Toplam (TL)</label>
    <input type="number" name="yearly_amount" value="{{ old('yearly_amount', $plan?->yearly_amount) }}"
           step="0.01" min="0.01"
           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
    <p class="mt-1 text-xs text-slate-500">12'ye bölünerek aylık tutar hesaplanır.</p>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Dağıtım Yöntemi</label>
    <select name="distribution_type" id="distribution_type" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
        <option value="equal" {{ old('distribution_type', $plan?->distribution_type ?? 'equal') === 'equal' ? 'selected' : '' }}>Eşit dağıtım</option>
        <option value="square_meters" {{ old('distribution_type', $plan?->distribution_type) === 'square_meters' ? 'selected' : '' }}>Metrekareye göre</option>
        <option value="share_coefficient" {{ old('distribution_type', $plan?->distribution_type) === 'share_coefficient' ? 'selected' : '' }}>Pay çarpanına göre</option>
    </select>
</div>

{{-- Canlı Dağıtım Önizlemesi --}}
<div id="distribution-preview" class="hidden">
    <div class="rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dağıtım Önizlemesi</span>
            <span id="preview-total-label" class="text-xs text-slate-500"></span>
        </div>
        <div id="preview-warning" class="hidden px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-700"></div>
        <div id="preview-groups" class="divide-y divide-slate-100"></div>
    </div>
</div>

<script>
(function() {
    @php
        $unitsJson = $units->map(function($u) {
            return [
                'label' => ($u->block ? $u->block . '/' : '') . $u->unit_no,
                'sqm'   => (float) ($u->square_meters ?? 0),
                'coef'  => (float) ($u->share_coefficient ?? 0),
            ];
        });
    @endphp
    var units = {!! json_encode($unitsJson) !!};

    function getMonthlyAmount() {
        var type = document.querySelector('input[name="amount_type"]:checked')?.value;
        if (type === 'monthly') {
            return parseFloat(document.querySelector('input[name="monthly_amount"]')?.value) || 0;
        } else {
            var y = parseFloat(document.querySelector('input[name="yearly_amount"]')?.value) || 0;
            return Math.round(y / 12 * 100) / 100;
        }
    }

    function fmt(n) {
        return n.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function updatePreview() {
        var distType  = document.getElementById('distribution_type')?.value;
        var amount    = getMonthlyAmount();
        var preview   = document.getElementById('distribution-preview');
        var groupsEl  = document.getElementById('preview-groups');
        var warning   = document.getElementById('preview-warning');
        var totalLbl  = document.getElementById('preview-total-label');

        if (!amount || amount <= 0 || !units.length) {
            preview.classList.add('hidden');
            return;
        }
        preview.classList.remove('hidden');

        // Aktif daireler + ağırlıklar
        var activeUnits = units.filter(function(u) {
            if (distType === 'square_meters')     return u.sqm > 0;
            if (distType === 'share_coefficient') return u.coef > 0;
            return true;
        });

        var zeroCount = units.length - activeUnits.length;
        if (distType !== 'equal' && zeroCount > 0) {
            warning.textContent = zeroCount + ' dairenin ' + (distType === 'square_meters' ? 'metrekare' : 'pay çarpanı') + ' bilgisi 0 veya boş — bu daireler dağıtımdan hariç tutulur.';
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }

        var totalWeight = activeUnits.reduce(function(s, u) {
            if (distType === 'square_meters')     return s + u.sqm;
            if (distType === 'share_coefficient') return s + u.coef;
            return s + 1;
        }, 0);

        // Her daireye düşen pay
        var shares = [];
        var allocated = 0;
        activeUnits.forEach(function(u, idx) {
            var w = distType === 'equal' ? 1 : (distType === 'square_meters' ? u.sqm : u.coef);
            var share = (idx === activeUnits.length - 1)
                ? Math.round((amount - allocated) * 100) / 100
                : Math.round(amount * w / totalWeight * 100) / 100;
            allocated += share;
            shares.push({unit: u, w: w, share: share});
        });

        // Gruplama: aynı ağırlık → aynı grup
        var groups = {};
        shares.forEach(function(s) {
            var key = distType === 'equal' ? 'equal' : s.w.toString();
            if (!groups[key]) groups[key] = {w: s.w, share: s.share, count: 0};
            groups[key].count++;
        });

        // Grupları sırala (ağırlığa göre artan)
        var sortedGroups = Object.values(groups).sort(function(a, b) { return a.w - b.w; });

        // HTML oluştur
        var html = '';
        sortedGroups.forEach(function(g) {
            var weightLabel = '';
            if (distType === 'equal') {
                weightLabel = 'Eşit dağıtım';
            } else if (distType === 'square_meters') {
                weightLabel = g.w.toLocaleString('tr-TR') + ' m²';
            } else {
                weightLabel = g.w.toLocaleString('tr-TR') + ' çarpan';
            }

            html += '<div class="flex items-center justify-between px-4 py-3 text-sm">'
                + '<div class="text-slate-700">'
                + '<span class="font-medium">' + weightLabel + '</span>'
                + ' &mdash; <span class="text-slate-500">' + g.count + ' daire</span>'
                + '</div>'
                + '<div class="font-bold text-slate-900 tabular-nums">' + fmt(g.share) + ' TL / daire</div>'
                + '</div>';
        });

        groupsEl.innerHTML = html;
        totalLbl.textContent = fmt(amount) + ' TL · ' + activeUnits.length + ' daire';
    }

    // Event listeners
    document.addEventListener('change', function(e) {
        if (e.target.name === 'amount_type' || e.target.name === 'distribution_type') updatePreview();
    });
    document.addEventListener('input', function(e) {
        if (e.target.name === 'monthly_amount' || e.target.name === 'yearly_amount') updatePreview();
    });

    // İlk yükleme
    document.addEventListener('DOMContentLoaded', updatePreview);
})();
</script>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Hedef Kitle</label>
    <select name="target_audience" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
        <option value="tenant_priority" {{ old('target_audience', $plan?->target_audience ?? 'tenant_priority') === 'tenant_priority' ? 'selected' : '' }}>Kiracı öncelikli (kiracı yoksa sahibi)</option>
        <option value="owner_only" {{ old('target_audience', $plan?->target_audience) === 'owner_only' ? 'selected' : '' }}>Sadece sahipler</option>
    </select>
</div>

<div>
    <label class="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
    <select name="category_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
        <option value="">— Kategori seçin —</option>
        @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" {{ old('category_id', $plan?->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
        @endforeach
    </select>
</div>


<div class="flex items-center gap-2">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" id="is_active" value="1"
           {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}
           class="rounded border-slate-300">
    <label for="is_active" class="text-sm text-slate-700">Aktif plan</label>
</div>

<script>
    document.querySelectorAll('input[name="amount_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('field-monthly').classList.toggle('hidden', this.value !== 'monthly');
            document.getElementById('field-yearly').classList.toggle('hidden', this.value !== 'yearly');
        });
    });
</script>
