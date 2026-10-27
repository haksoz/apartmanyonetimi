
        (() => {
            const typeSelect = document.getElementById('due_type');
            const audienceInputs = document.querySelectorAll('input[name="target_audience"]');
            const selectedAudience = () => document.querySelector('input[name="target_audience"]:checked')?.value || '';
            const accountSelect = document.getElementById('account_id');
            const accountOptions = Array.from(accountSelect.options);
            const lockedAccountInput = document.getElementById('locked-account-id');
            const syncDueTypeOptions = () => {
                const residentScope = selectedAudience() === 'tenant_priority';
                Array.from(typeSelect.options).forEach(option => {
                    const blocked = residentScope && option.value === 'malik_payi';
                    option.disabled = blocked;
                    option.hidden = blocked;
                });
                if (residentScope && typeSelect.value === 'malik_payi') typeSelect.value = '';
            };
            const syncAccountOptions = () => {
                const ownerOnly = typeSelect.value === 'malik_payi' || selectedAudience() === 'owner_only';
                const lockedId = lockedAccountInput?.value;
                const options = accountOptions.filter(option => !option.value || !ownerOnly || option.dataset.accountType === 'owner' || option.value === lockedId);
                const selectedValue = lockedId || accountSelect.value;
                accountSelect.replaceChildren(...options);
                accountSelect.value = options.some(option => option.value === selectedValue) ? selectedValue : '';
                accountSelect.disabled = Boolean(lockedId);
                accountSelect.name = lockedId ? '' : 'account_id';
                accountSelect.classList.toggle('bg-slate-100', Boolean(lockedId));
                accountSelect.classList.toggle('cursor-not-allowed', Boolean(lockedId));
                if (lockedAccountInput) lockedAccountInput.disabled = false;
                const incompatible = ownerOnly && accountSelect.selectedOptions[0]?.dataset.accountType === 'tenant';
                accountSelect.setCustomValidity(incompatible ? 'Bu kapsam veya borç türü için kat maliki hesabı gerekir.' : '');
                document.getElementById('account-scope-note').hidden = !incompatible;
            };
            const topicSelect = document.getElementById('category_id');
            const categoryField = document.getElementById('category-field');
            const periodInput = document.getElementById('period');
            const createdAtInput = document.getElementById('created_at_manual');
            const dueDateInput = document.getElementById('due_date');
            const descriptionInput = document.getElementById('description');
            let isDueDateManuallySet = false;

            const months = {
                '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
                '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
                '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
            };

            const syncPeriodFromCreatedAt = () => {
                const dateVal = createdAtInput?.value;
                if (dateVal) {
                    periodInput.value = dateVal.substring(0, 7);
                }
            };

            const syncDueDate = () => {
                const dateVal = createdAtInput?.value;
                if (dateVal && !isDueDateManuallySet) {
                    const dueDate = new Date(dateVal);
                    dueDate.setDate(dueDate.getDate() + 15);
                    const year = dueDate.getFullYear();
                    const month = String(dueDate.getMonth() + 1).padStart(2, '0');
                    const day = String(dueDate.getDate()).padStart(2, '0');
                    dueDateInput.value = `${year}-${month}-${day}`;
                }
            };

            const updateDescription = () => {
                if (descriptionInput.value && descriptionInput.dataset.userEdited) return;
                const period = periodInput.value;
                const typeOption = typeSelect.options[typeSelect.selectedIndex];
                const typeName = typeOption?.value ? typeOption.text : '';
                const topicOption = topicSelect?.options[topicSelect.selectedIndex];
                const topicName = topicOption?.value ? topicOption.text : '';

                if (period && typeName) {
                    const [year, month] = period.split('-');
                    const suffix = topicName ? ` / ${topicName}` : '';
                    descriptionInput.value = `${year} ${months[month] || month} - ${typeName}${suffix}`;
                }
            };

            descriptionInput.addEventListener('input', () => {
                descriptionInput.dataset.userEdited = '1';
            });

            const syncCategoryField = () => {
                if (!topicSelect) return;
                let selectedVisible = false;
                Array.from(topicSelect.options).forEach(option => {
                    if (!option.value) return;
                    const visible = (option.dataset.usages || '').split(',').includes(typeSelect.value);
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible && option.selected) selectedVisible = true;
                });
                if (!selectedVisible) topicSelect.value = '';
            };

            audienceInputs.forEach(input => input.addEventListener('change', () => {
                syncDueTypeOptions();
                syncCategoryField();
                document.querySelectorAll('[data-individual-heading]').forEach(heading => {
                    heading.textContent = selectedAudience() === 'owner_only' ? 'Kat Maliki Borçlandırması' : selectedAudience() === 'tenant_priority' ? 'Daire Sakini Borçlandırması' : 'Borçlandır';
                });
                syncAccountOptions();
            }));
            accountSelect.addEventListener('change', syncAccountOptions);
            typeSelect.addEventListener('change', () => {
                syncCategoryField();
                syncAccountOptions();
                descriptionInput.dataset.userEdited = '';
                updateDescription();
            });
            topicSelect?.addEventListener('change', () => {
                descriptionInput.dataset.userEdited = '';
                updateDescription();
            });
            createdAtInput?.addEventListener('change', () => {
                syncPeriodFromCreatedAt();
                syncDueDate();
                descriptionInput.dataset.userEdited = '';
                updateDescription();
            });

            dueDateInput?.addEventListener('input', () => {
                isDueDateManuallySet = dueDateInput.value !== '';
            });

            // Init
            syncDueTypeOptions();
            syncAccountOptions();
            syncCategoryField();
            syncPeriodFromCreatedAt();
            syncDueDate();
            if (!descriptionInput.value) {
                updateDescription();
            }
        })();
    