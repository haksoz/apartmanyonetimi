@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Yeni Kullanıcı Oluştur</h1>
            <p class="mt-1 text-sm text-slate-500">Yeni kullanıcı bilgilerini girin ve hesaplara bağlayın.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Kullanıcılara Dön</a>
        </div>
    </div>

    <form method="POST" action="{{ route('users.store') }}">
        @csrf

        <div class="space-y-4">
            {{-- Kullanıcı Bilgileri --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-5">Kullanıcı Bilgileri</h5>

                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Ad Soyad</label>
                        <input type="text" name="name" value="{{ old('name') }}" required list="account-names"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                            placeholder="Yazmaya başlayın veya listeden seçin">
                        <datalist id="account-names">
                            @foreach ($accountNames as $name)
                                <option value="{{ $name }}">
                            @endforeach
                        </datalist>
                        @error('name')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">E-posta</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('email')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Telefon</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('phone')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            @if ($availableAccounts->isNotEmpty())
                {{-- Hesap Bağlama --}}
                <div class="rounded-2xl bg-white p-6 shadow-sm">
                    <h5 class="text-sm font-semibold text-slate-900 mb-3">Kullanıcıya Tanımlanabilecek Hesaplar</h5>
                    <p class="text-sm text-slate-500 mb-5">Boş bırakabilirsiniz. İsterseniz sonradan da hesap tanımlayabilirsiniz.</p>

                    <div class="space-y-2 max-h-64 overflow-y-auto border border-slate-200 rounded-xl p-3 mb-4">
                        @foreach ($availableAccounts as $account)
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="account_ids[]" value="{{ $account->id }}"
                                    class="rounded border-slate-300 account-checkbox"
                                    data-name="{{ $account->name }}">
                                <div class="flex-1">
                                    <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' No · ' : '' }}
                                        {{ $account->type_label }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    {{-- Sync uyarısı --}}
                    <div id="sync-warning" class="hidden">
                        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 cursor-pointer">
                            <input type="checkbox" name="sync_account_info" value="1" class="mt-0.5 rounded border-slate-300">
                            <div>
                                <div class="text-sm font-semibold text-amber-800">Hesap bilgilerini güncelle</div>
                                <div id="sync-warning-text" class="text-xs text-amber-700 mt-1"></div>
                            </div>
                        </label>
                    </div>
                </div>
            @endif

            {{-- Kaydet Butonu --}}
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <div class="flex gap-3">
                    <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                        Kullanıcı Oluştur
                    </button>
                    <a href="{{ route('users.index') }}" class="w-full md:w-auto rounded-xl border border-slate-300 px-6 py-3 text-sm text-slate-600 hover:bg-slate-50 text-center">
                        İptal
                    </a>
                </div>
            </div>
        </div>
    </form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.account-checkbox');
        const warning = document.getElementById('sync-warning');
        const warningText = document.getElementById('sync-warning-text');

        function updateWarning() {
            const checked = Array.from(checkboxes).filter(c => c.checked);
            if (checked.length === 0) {
                warning.classList.add('hidden');
                return;
            }
            warning.classList.remove('hidden');
            const names = checked.map(c => '"' + c.dataset.name + '"').join(', ');
            if (checked.length === 1) {
                warningText.textContent = names + ' hesabının ad, telefon ve e-posta bilgileri yukarıdaki kullanıcı bilgileriyle değiştirilecektir.';
            } else {
                warningText.textContent = 'Seçili ' + checked.length + ' hesabın (' + names + ') ad, telefon ve e-posta bilgileri yukarıdaki kullanıcı bilgileriyle değiştirilecektir.';
            }
        }

        checkboxes.forEach(c => c.addEventListener('change', updateWarning));
    });
</script>
@endsection
