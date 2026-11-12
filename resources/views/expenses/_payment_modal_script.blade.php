<script>
        // Gider ödemesi popup
        const expenseMonthNames = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

        function formatMultiExpenseDescription(dateString) {
            if (!dateString) return '';
            const [year, month] = dateString.split('-');
            if (!year || !month) return '';
            return `${expenseMonthNames[parseInt(month, 10) - 1]} ${year} Toplu Ödeme`;
        }

        function isAutoExpenseDescription(current, description) {
            if (!current) return true;
            return current === (description ? description + ' ödemesi' : 'Gider ödemesi');
        }

        function isAutoMultiExpenseDescription(current, dateString) {
            if (!current) return true;
            return current === formatMultiExpenseDescription(dateString);
        }

        function openExpensePaymentModal(expenseId, amount, description, category, supplier) {
            const modal = document.getElementById('expense-payment-modal');
            const form = document.getElementById('expense-payment-form');
            if (!modal || !form) return;

            form.action = (form.dataset.baseUrlSingle || '').replace('__EXPENSE_ID__', expenseId);
            document.getElementById('expense-payment-expense-id').value = expenseId;
            document.getElementById('expense-payment-amount-input').value = amount;
            document.getElementById('expense-payment-expense-ids').value = '';

            document.getElementById('expense-payment-modal-title').textContent = 'Gider Ödemesi';
            document.getElementById('expense-payment-modal-subtitle').textContent = 'Gider ödemesi yapın.';
            document.getElementById('expense-payment-info-single').classList.remove('hidden');
            document.getElementById('expense-payment-info-multi').classList.add('hidden');

            document.getElementById('expense-payment-description').textContent = description || 'Gider';
            document.getElementById('expense-payment-category').textContent = category || '-';
            document.getElementById('expense-payment-amount').textContent = amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TL';
            document.getElementById('expense-payment-supplier').textContent = supplier || '-';

            const descInput = document.getElementById('expense-payment-description-input');
            if (descInput && isAutoExpenseDescription(descInput.value.trim(), description)) {
                descInput.value = description ? description + ' ödemesi' : 'Gider ödemesi';
            }

            modal.classList.remove('hidden');
        }

        function closeExpensePaymentModal() {
            document.getElementById('expense-payment-modal')?.classList.add('hidden');
        }

        document.getElementById('expense-payment-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeExpensePaymentModal();
        });
</script>
