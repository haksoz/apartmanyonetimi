@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Toplu Hesap İçe Aktar — Önizleme</h1>
        <p class="mt-1 text-sm text-slate-500">Hesapları ve tiplerini kontrol edin, sonra içe aktarın.</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Yeni Hesap</div>
            <div class="text-xl font-bold text-slate-900">{{ count(array_filter($accounts, fn($a) => empty($a['existing_account_id']))) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Mevcut Hesapla Eşleşen</div>
            <div class="text-xl font-bold text-amber-600">{{ count(array_filter($accounts, fn($a) => !empty($a['existing_account_id']))) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Toplam Cari Hareket</div>
            <div class="text-xl font-bold text-slate-900">{{ count($transactions) }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('accounts.bulk-import-confirm') }}">
        @csrf

        {{-- Accounts Table --}}
        <div class="rounded-2xl bg-white shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">
                    Tespit Edilen Hesaplar ({{ count($accounts) }})
                </h3>
                <span class="text-xs text-slate-500">Tip seçimlerini yapın</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Excel'den Gelen</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Sistemdeki Eşleşen Hesap</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Daire</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Tarih Aralığı</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Hareket</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Tip</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($accounts as $accountKey => $account)
                            @php
                                // Yeni hesaplarda (existing_account_id yok) tip seçilmemişse beyaz/boş renk
                                $rowBgClass = !$account['existing_account_id']
                                    ? 'bg-white'
                                    : match($account['suggested_type']) {
                                        'owner' => 'bg-blue-100',
                                        'tenant' => 'bg-emerald-100',
                                        'former_tenant' => 'bg-purple-100',
                                        default => 'bg-amber-100'
                                    };
                                $accountKeyEncoded = e($accountKey);
                            @endphp
                            <tr class="{{ $rowBgClass }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $account['name'] }}</div>
                                    @if ($account['unit_no'])
                                        <div class="text-xs text-slate-500">Daire: {{ $account['unit_no'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <select name="account_mapping[{{ $accountKey }}]"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                            onchange="toggleRenameCheckbox('{{ $accountKeyEncoded }}', this.value); updateUnitFromAccount('{{ $accountKeyEncoded }}', this.value)">
                                        <option value="">-- Yeni Hesap Oluştur --</option>
                                        @foreach ($allAccounts as $sysAccount)
                                            @php
                                                $isMatched = $account['existing_account_id'] == $sysAccount['id'];
                                                $unitLabel = $sysAccount['unit_no'] ? ' (Daire ' . $sysAccount['unit_no'] . ')' : '';
                                                $typeLabel = match($sysAccount['type']) {
                                                    'owner' => 'Kat Maliki',
                                                    'tenant' => 'Kiracı',
                                                    default => 'Tedarikçi'
                                                };
                                            @endphp
                                            <option value="{{ $sysAccount['id'] }}" {{ $isMatched ? 'selected' : '' }}>
                                                {{ $sysAccount['name'] }}{{ $unitLabel }} - {{ $typeLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-2 mt-2 text-xs cursor-pointer {{ $account['existing_account_id'] ? '' : 'opacity-50' }}" id="rename-label-{{ $accountKeyEncoded }}">
                                        <input type="checkbox" name="rename_accounts[{{ $accountKey }}]" value="1"
                                               class="rounded text-emerald-600 focus:ring-emerald-500"
                                               {{ $account['existing_account_id'] ? '' : 'disabled' }}
                                               id="rename-check-{{ $accountKeyEncoded }}"
                                               onchange="handleRenameCheck('{{ $accountKeyEncoded }}', this)">
                                        <span class="text-slate-600">Hesap adını güncelle: <strong>"{{ $account['name'] }}"</strong></span>
                                    </label>
                                </td>
                                <td class="px-4 py-3">
                                    {{-- Sistemdeki eşleşen hesabın daire bilgisi --}}
                                    <div id="system-unit-display-{{ $accountKeyEncoded }}" class="text-xs mb-1">
                                        @php
                                            $sysUnitNo = $allAccounts[$account['existing_account_id']]['unit_no'] ?? null;
                                        @endphp
                                        @if ($sysUnitNo)
                                            <span class="text-emerald-600 font-medium">Sistem: Daire {{ $sysUnitNo }}</span>
                                        @else
                                            <span class="text-slate-400">Sistem: Daire yok</span>
                                        @endif
                                    </div>

                                    <div id="unit-select-wrapper-{{ $accountKeyEncoded }}" class="{{ $account['suggested_type'] == 'supplier' ? 'hidden' : '' }}">
                                        <select name="unit_mapping[{{ $accountKey }}]"
                                                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                            <option value="">-- Daire Seç --</option>
                                            @foreach ($allUnits as $unitId => $unitNo)
                                                <option value="{{ $unitId }}" {{ $account['unit_id'] == $unitId ? 'selected' : '' }}>
                                                    Daire {{ $unitNo }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($account['unit_id'])
                                            <div class="text-xs text-slate-500 mt-1">Excel: Daire {{ $account['unit_no'] ?? '-' }}</div>
                                        @endif
                                    </div>
                                    <div id="unit-supplier-msg-{{ $accountKeyEncoded }}" class="text-xs text-slate-400 {{ $account['suggested_type'] == 'supplier' ? '' : 'hidden' }}">
                                        Tedarikçi için daire seçilmez
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 text-xs">
                                    {{ $account['date_range'] }}
                                </td>
                                <td class="px-4 py-3 text-slate-700 text-center">
                                    {{ $account['transaction_count'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <select name="account_types[{{ $accountKey }}]"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                            onchange="toggleUnitSelect('{{ $accountKeyEncoded }}', this.value); updateRowColor('{{ $accountKeyEncoded }}', this.value)"
                                            required>
                                        <option value="" {{ !$account['existing_account_id'] ? 'selected' : '' }}>-- Tip Seçin --</option>
                                        <option value="supplier" {{ $account['existing_account_id'] && $account['suggested_type'] == 'supplier' ? 'selected' : '' }}>Tedarikçi</option>
                                        <option value="owner" {{ $account['existing_account_id'] && $account['suggested_type'] == 'owner' ? 'selected' : '' }}>Kat Maliki</option>
                                        <option value="tenant" {{ $account['existing_account_id'] && $account['suggested_type'] == 'tenant' ? 'selected' : '' }}>Kiracı</option>
                                        <option value="former_tenant" {{ $account['existing_account_id'] && $account['suggested_type'] == 'former_tenant' ? 'selected' : '' }}>Eski Kiracı</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Transactions Preview --}}
        <div class="rounded-2xl bg-white shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">Cari Hareketler</h3>
                @if (count($transactions) > 20)
                    <span class="text-xs text-amber-600">İlk 20 kayıt gösteriliyor (toplam {{ count($transactions) }})</span>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Tarih</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Hesap</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Kategori</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500">Açıklama</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Alacak</th>
                            <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Borç</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach (array_slice($transactions, 0, 20) as $t)
                            <tr>
                                <td class="px-4 py-3 text-slate-700 tabular-nums">{{ $t['display_date'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $t['account_name'] }}</div>
                                    @if ($t['unit_no'])
                                        <div class="text-xs text-slate-500">Daire: {{ $t['unit_no'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $t['category_name'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $t['description'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $t['credit'] > 0 ? 'text-emerald-600' : '' }}">
                                    {{ $t['credit'] > 0 ? number_format($t['credit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $t['debit'] > 0 ? 'text-red-600' : '' }}">
                                    {{ $t['debit'] > 0 ? number_format($t['debit'], 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Warning for units --}}
        @php
            $missingUnits = collect($accounts)->filter(fn($a) => !empty($a['unit_no']) && empty($a['unit_id']))->count();
        @endphp
        @if ($missingUnits > 0)
            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-6">
                <h3 class="text-sm font-semibold text-amber-800 mb-1">Uyarı</h3>
                <p class="text-xs text-amber-700">
                    {{ $missingUnits }} hesap için belirtilen daire numarası sistemde bulunamadı.
                    Bu hesaplar daire ilişkisi olmadan oluşturulacak.
                </p>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex gap-3">
            <button type="submit" class="flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 text-sm font-semibold">
                {{ count($accounts) }} Hesabı ve {{ count($transactions) }} Hareketi İçe Aktar
            </button>
            <a href="{{ route('accounts.bulk-import') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Geri Dön
            </a>
            <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Vazgeç
            </a>
        </div>
    </form>

    <script>
        // Sistem hesapları verisi (ID -> unit_no mapping)
        const accountUnitMap = {};
        const accountTypeMap = {};
        @foreach($allAccounts as $account)
            accountUnitMap[{{ $account['id'] }}] = '{{ $account['unit_no'] ?? '' }}';
            accountTypeMap[{{ $account['id'] }}] = '{{ $account['type'] ?? 'supplier' }}';
        @endforeach

        function toggleRenameCheckbox(accountName, value) {
            const checkbox = document.getElementById('rename-check-' + accountName);
            const label = document.getElementById('rename-label-' + accountName);
            const hasValue = value && value !== '';
            if (hasValue) {
                checkbox.disabled = false;
                label.classList.remove('opacity-50');
            } else {
                checkbox.disabled = true;
                checkbox.checked = false;
                label.classList.add('opacity-50');
            }
            // Eşleşme değiştiğinde daireyi ve tipi güncelle
            updateUnitFromAccount(accountName, value);
            updateTypeFromAccount(accountName, value);
        }

        function updateTypeFromAccount(accountName, accountId) {
            const typeSelect = document.querySelector('select[name="account_types[' + accountName + ']"]');
            if (!typeSelect || !accountId) return;

            const accountType = accountTypeMap[accountId];
            if (accountType) {
                typeSelect.value = accountType;
                // Tip değiştiğinde unit select ve row color da güncellenmeli
                toggleUnitSelect(accountName, accountType);
                updateRowColor(accountName, accountType);
            }
        }

        function toggleUnitSelect(accountName, type) {
            const unitWrapper = document.getElementById('unit-select-wrapper-' + accountName);
            const supplierMsg = document.getElementById('unit-supplier-msg-' + accountName);
            if (type === 'supplier') {
                unitWrapper.classList.add('hidden');
                supplierMsg.classList.remove('hidden');
            } else {
                unitWrapper.classList.remove('hidden');
                supplierMsg.classList.add('hidden');
            }
        }

        // Kullanıcı "hesap adını güncelle" checkbox'ını işaretlediğinde
        // aynı sistem hesabına map edilmiş diğer checkbox'ları devre dışı bırak
        function handleRenameCheck(accountName, checkbox) {
            const mappedSelect = document.querySelector('select[name="account_mapping[' + accountName + ']"]');
            if (!mappedSelect || !mappedSelect.value) return;

            const mappedId = mappedSelect.value;
            const allRenames = document.querySelectorAll('input[name^="rename_accounts["]');

            allRenames.forEach(cb => {
                if (cb === checkbox) return; // Kendisi hariç

                const otherName = cb.name.match(/\[(.+?)\]$/)[1];
                const otherSelect = document.querySelector('select[name="account_mapping[' + otherName + ']"]');
                const otherLabel = document.getElementById('rename-label-' + otherName);

                if (otherSelect && otherSelect.value === mappedId && otherLabel) {
                    if (checkbox.checked) {
                        // Bu işaretlendi, diğerini devre dışı bırak
                        cb.disabled = true;
                        cb.checked = false;
                        otherLabel.classList.add('opacity-50');
                        otherLabel.title = '"' + accountName + '" seçildi, sadece biri güncellenebilir';
                    } else {
                        // Bu işareti kaldırıldı, diğerini tekrar aktif yap
                        cb.disabled = false;
                        otherLabel.classList.remove('opacity-50');
                        otherLabel.title = '';
                    }
                }
            });
        }

        // Sayfa yüklendiğinde checkbox'lara event listener ekle
        document.addEventListener('DOMContentLoaded', function() {
            // Başlangıçta mevcut hesaplar için checkbox'ları aktif et
            document.querySelectorAll('select[name^="account_mapping["]').forEach(select => {
                const accountName = select.name.match(/\[(.+?)\]$/)[1];
                toggleRenameCheckbox(accountName, select.value);
            });

            // Checkbox'lara event listener ekle
            document.querySelectorAll('input[name^="rename_accounts["]').forEach(cb => {
                cb.addEventListener('change', function() {
                    const accountName = this.name.match(/\[(.+?)\]$/)[1];
                    handleRenameCheck(accountName, this);
                });
            });
        });

        // Hesap değiştiğinde daire bilgisini güncelle
        function updateUnitFromAccount(accountName, accountId) {
            const unitDisplay = document.getElementById('system-unit-display-' + accountName);
            const unitSelect = document.querySelector('select[name="unit_mapping[' + accountName + ']"]');

            if (!accountId) {
                // Yeni hesap oluştur seçildi
                unitDisplay.innerHTML = '<span class="text-slate-400">Yeni hesap - daire seçilebilir</span>';
                return;
            }

            const unitNo = accountUnitMap[accountId];
            if (unitNo && unitNo !== '') {
                unitDisplay.innerHTML = '<span class="text-emerald-600 font-medium">Sistem: Daire ' + unitNo + '</span>';
                // Dropdown'u da otomatik seç
                if (unitSelect) {
                    // unitNo'dan unitId'yi bul
                    const options = Array.from(unitSelect.options);
                    const matchingOption = options.find(opt => opt.text.includes('Daire ' + unitNo));
                    if (matchingOption) {
                        unitSelect.value = matchingOption.value;
                    }
                }
            } else {
                unitDisplay.innerHTML = '<span class="text-slate-400">Sistem: Daire yok</span>';
                if (unitSelect) {
                    unitSelect.value = ''; // Seçimi temizle
                }
            }
        }

        // Satır rengini tip değiştiğinde güncelle
        function updateRowColor(accountName, type) {
            const row = document.querySelector('select[name="account_types[' + accountName + ']"]').closest('tr');
            // Önce tüm renk class'larını kaldır
            row.classList.remove('bg-blue-100', 'bg-emerald-100', 'bg-amber-100', 'bg-purple-100', 'bg-white');
            // Boş seçim ise beyaz bırak
            if (!type || type === '') {
                row.classList.add('bg-white');
                return;
            }
            // Yeni rengi ekle
            const newClass = type === 'owner' ? 'bg-blue-100' :
                            type === 'tenant' ? 'bg-emerald-100' :
                            type === 'former_tenant' ? 'bg-purple-100' :
                            'bg-amber-100';
            row.classList.add(newClass);
        }
    </script>
@endsection
