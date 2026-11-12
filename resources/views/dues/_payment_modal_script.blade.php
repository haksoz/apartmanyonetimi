<script>
        function openDuePaymentModal(dueId, amount, description, accountName, unitNo) {
            const modal = document.getElementById('due-payment-modal');
            const form = document.getElementById('due-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrl || '').replace('__DUE_ID__', dueId);
            document.getElementById('due-payment-due-id').value = dueId;
            document.getElementById('due-payment-amount-input').value = amount;
            document.getElementById('due-payment-description').textContent = description || 'Aidat';
            document.getElementById('due-payment-amount').textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
            document.getElementById('due-payment-account').textContent = 'No:' + (unitNo || '-') + ' ' + (accountName || '-');

            const descInput = document.getElementById('due-payment-description-input');
            if (descInput) {
                descInput.value = (description ? description + ' Tahsilatı' : 'Aidat Tahsilatı');
            }

            modal.classList.remove('hidden');
        }

        function closeDuePaymentModal() {
            document.getElementById('due-payment-modal')?.classList.add('hidden');
        }

        document.getElementById('due-payment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeDuePaymentModal();
        });
</script>
