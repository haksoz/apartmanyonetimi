@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesabı Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->name }} hesabını güncelleyin.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Bu hesabı silmek istediğinize emin misiniz? Hesapta hareket varsa silinemez.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                    Hesabı Sil
                </button>
            </form>
            <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detaya Dön</a>
            @if ($account->type === App\Models\Account::TYPE_OWNER)
                <button type="button" onclick="openTerminateModal('owner', {{ $account->id }}, '{{ $account->name }}')" 
                    class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">
                    Malikliği Sonlandır
                </button>
            @endif
            @if ($account->type === App\Models\Account::TYPE_TENANT && ! $account->activeTenantAssignment?->move_out_date)
                <button type="button" onclick="openTerminateModal('tenant', {{ $account->id }}, '{{ $account->name }}')" 
                    class="rounded-xl bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">
                    Kiralamayı Sonlandır
                </button>
            @endif
        </div>
    </div>

    @include('accounts.form', [
        'action' => route('accounts.update', $account),
        'method' => 'PUT',
        'account' => $account,
        'units' => $units,
        'buttonText' => 'Hesabı Güncelle',
    ])

    {{-- Sonlandırma Modal --}}
    <div id="terminate-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900 mb-1" id="terminate-title">Sonlandır</h3>
            <p class="text-sm text-slate-500 mb-4" id="terminate-desc"></p>

            <form method="POST" action="" id="terminate-form">
                @csrf
                @method('PATCH')
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-600">Sonlandırma Tarihi</label>
                        <input type="date" name="termination_date" id="termination-date" required
                            value="{{ date('Y-m-d') }}"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>
                    
                    <div id="new-owner-section" class="hidden">
                        <p class="text-sm text-slate-600 bg-slate-50 rounded-lg p-3">
                            <span class="font-medium text-slate-900">{{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) : 'XX' }} nolu dairenin yeni kat maliki hesabı otomatik oluşturulacak.</span>
                            <span class="block mt-1 text-slate-500">Düzenlemeniz için ilgili sayfaya yönlendirileceksiniz.</span>
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="submit" class="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        Sonlandır
                    </button>
                    <button type="button" onclick="closeTerminateModal()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTerminateModal(type, accountId, accountName) {
            const modal = document.getElementById('terminate-modal');
            const title = document.getElementById('terminate-title');
            const desc = document.getElementById('terminate-desc');
            const form = document.getElementById('terminate-form');
            const newOwnerSection = document.getElementById('new-owner-section');
            
            if (type === 'tenant') {
                title.textContent = 'Kiralamayı Sonlandır';
                desc.textContent = accountName + ' için kiralama sonlandırılacak.';
                form.action = '/accounts/' + accountId + '/terminate-tenancy';
                newOwnerSection.classList.add('hidden');
            } else {
                title.textContent = 'Malikliği Sonlandır';
                desc.textContent = accountName + ' için maliklik sonlandırılacak. Daireye yeni kat maliki atanmalı.';
                form.action = '/accounts/' + accountId + '/terminate-ownership';
                newOwnerSection.classList.remove('hidden');
            }
            
            modal.classList.remove('hidden');
        }

        function closeTerminateModal() {
            document.getElementById('terminate-modal').classList.add('hidden');
        }

        // Modal dışına tıklayınca kapat
        document.getElementById('terminate-modal').addEventListener('click', function(e) {
            if (e.target === this) closeTerminateModal();
        });
    </script>
@endsection
