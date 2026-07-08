@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Yeni Aidat Planı</h1>
        <p class="mt-1 text-sm text-slate-500">Yıllık aidat planı oluşturun.</p>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm max-w-2xl">
        <form id="due-plan-form" method="POST" action="{{ route('due-plans.store') }}" class="space-y-5">
            @csrf
            @include('due-plans._form', ['plan' => null])
            <div class="flex gap-3 pt-2">
                <button type="button" id="btn-save"
                        class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Kaydet
                </button>
                <a href="{{ route('due-plans.index') }}"
                   class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    İptal
                </a>
            </div>
        </form>
    </div>

    {{-- Onay Modal --}}
    <div id="confirm-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 mb-2">Otomatik Aidat Oluşturma</h2>
            <p class="text-sm text-slate-600 mb-4" id="confirm-modal-text"></p>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-5 space-y-3">
                <p class="text-sm font-medium text-slate-700">İlk aidat ne zaman oluşturulsun?</p>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="start_choice" value="this_month" id="start_this_month_radio" checked
                           class="text-slate-800">
                    <span class="text-sm text-slate-700">
                        <strong id="this-month-label"></strong> — hemen oluştur
                    </span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="start_choice" value="next_month" id="start_next_month_radio"
                           class="text-slate-800">
                    <span class="text-sm text-slate-700">
                        <strong id="next-month-label"></strong> — bir sonraki aydan başlasın
                    </span>
                </label>
            </div>

            <div class="flex gap-3 justify-end">
                <button id="modal-cancel"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Geri Dön
                </button>
                <button id="modal-confirm"
                        class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Onayla ve Kaydet
                </button>
            </div>
        </div>
    </div>

    <input type="hidden" name="start_this_month" id="start_this_month_input" form="due-plan-form" value="0">

    <script>
        (function () {
            var form             = document.getElementById('due-plan-form');
            var btnSave          = document.getElementById('btn-save');
            var modal            = document.getElementById('confirm-modal');
            var modalText        = document.getElementById('confirm-modal-text');
            var modalCancel      = document.getElementById('modal-cancel');
            var modalConfirm     = document.getElementById('modal-confirm');
            var startThisInput   = document.getElementById('start_this_month_input');
            var thisMonthLabel   = document.getElementById('this-month-label');
            var nextMonthLabel   = document.getElementById('next-month-label');

            var months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran',
                          'Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

            btnSave.addEventListener('click', function () {
                var autoGenerate = document.getElementById('auto_generate')?.checked;

                if (!autoGenerate) {
                    startThisInput.value = '0';
                    form.submit();
                    return;
                }

                var yearInput   = document.getElementById('plan_year');
                var year        = yearInput ? parseInt(yearInput.value) : new Date().getFullYear();
                var now         = new Date();
                var currentMonth = now.getMonth();
                var currentYear  = now.getFullYear();

                var thisMonthName = months[currentMonth];
                var nextMonthDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                var nextMonthName = months[nextMonthDate.getMonth()];
                var nextMonthYear = nextMonthDate.getFullYear();

                thisMonthLabel.textContent = thisMonthName + ' ' + currentYear;
                nextMonthLabel.textContent = nextMonthName + ' ' + nextMonthYear;

                var endMonth = 'Aralık';
                var firstMonth = (year === currentYear) ? thisMonthName : months[0];

                var text = year === currentYear
                    ? firstMonth + ' ' + year + ' ayından ' + endMonth + ' ' + year + ' sonuna kadar her ay belirtilen günde aidat otomatik oluşturulacaktır.'
                    : 'Ocak ' + year + ' – ' + endMonth + ' ' + year + ' arasında her ay belirtilen günde aidat otomatik oluşturulacaktır.';

                modalText.textContent = text;
                document.getElementById('start_this_month_radio').checked = true;
                modal.classList.remove('hidden');
            });

            modalCancel.addEventListener('click', function () {
                modal.classList.add('hidden');
            });

            modalConfirm.addEventListener('click', function () {
                var choice = document.querySelector('input[name="start_choice"]:checked')?.value;
                startThisInput.value = (choice === 'this_month') ? '1' : '0';
                modal.classList.add('hidden');
                form.submit();
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.classList.add('hidden');
            });
        })();
    </script>
@endsection
