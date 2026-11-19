<dialog id="management-transfer-dialog" aria-labelledby="management-transfer-title" class="m-auto max-h-[90vh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl border border-emerald-200 bg-white p-6 shadow-xl backdrop:bg-black/50">
    <form method="POST" action="{{ old('transfer_target_id') ? route('users.role', (int) old('transfer_target_id')) : '#' }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="role" value="owner">
        <input type="hidden" name="transfer_target_id" value="{{ old('transfer_target_id') }}">
        <input type="hidden" name="transfer_target_name" value="{{ old('transfer_target_name') }}">
        <h2 id="management-transfer-title" class="text-lg font-bold text-emerald-950">Yöneticiliği devret</h2>
        <p class="mt-2 text-sm text-slate-600"><strong>{{ $apartment->name }}</strong> için <strong id="management-transfer-person">{{ old('transfer_target_name') }}</strong> kişisine devir daveti oluşturulacak. Davet, kişinin kendi panelinde gösterilir.</p>
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <p class="font-semibold">Kabul edildiği anda bu apartmanın yönetimi sizden çıkacak.</p>
            <p class="mt-2">Diğer yöneticilerin de apartman erişimi kapanır; yalnızca daveti kabul eden kişi yönetici kalır. Kabul edilene kadar yönetim sizde devam eder.</p>
            <p class="mt-2">Apartman, abonelik numarası ve mevcut hizmet süresi korunur. Geçmiş sipariş ve faturalarınız kendi hesabınızda kalır.</p>
        </div>
        <label class="mt-4 flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="confirm_transfer" value="1" required class="mt-1" @checked(old('confirm_transfer'))>
            <span>Kabul sonrası bu apartmana yönetici erişimimin kapanacağını onaylıyorum.</span>
        </label>
        @error('confirm_transfer')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        <label class="mt-4 block text-sm font-medium text-slate-700">Kendi parolanız
            <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
        </label>
        @error('current_password')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="mt-5 flex flex-wrap justify-end gap-2">
            <button type="button" onclick="this.closest('dialog').close()" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Vazgeç</button>
            <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Devir davetini oluştur</button>
        </div>
    </form>
</dialog>
<script>
    function openManagementTransfer(action, userId, name) {
        const dialog = document.getElementById('management-transfer-dialog');
        const form = dialog.querySelector('form');
        form.action = action;
        form.elements.transfer_target_id.value = userId;
        form.elements.transfer_target_name.value = name;
        form.elements.current_password.value = '';
        form.elements.confirm_transfer.checked = false;
        document.getElementById('management-transfer-person').textContent = name;
        dialog.showModal();
    }
    function changeManagementRole(select, action, userId, name) {
        if (select.value === 'owner') {
            select.value = 'member';
            openManagementTransfer(action, userId, name);
        } else {
            select.form.requestSubmit();
        }
    }
</script>
@if (old('transfer_target_id') && old('role') === 'owner')
    <script>document.getElementById('management-transfer-dialog').showModal();</script>
@endif
