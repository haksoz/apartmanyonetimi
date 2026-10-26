@isset($plan)
    <div class="md:col-span-2">
        <p class="mb-2 text-sm font-medium text-slate-600">İşin Kategorisi</p>
        <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $plan->category?->name ?? '—' }}</p>
        <p class="mt-2 text-xs text-slate-500">Plan adını değiştirmek kategorinin adını değiştirmez.</p>
    </div>
@else
    @php $categoryMode = old('category_mode', 'new'); @endphp
    <div class="grid min-w-0 gap-4 md:col-span-2 md:grid-cols-2">
        <fieldset class="min-w-0">
        <legend class="mb-2 text-sm font-medium text-slate-600">İşin Kategorisi</legend>
        <p class="mb-3 text-xs text-slate-500">Bu kategori, planın borçlarında ve ilgili giderlerde kullanılabilir.</p>
        <div class="mb-3 flex flex-wrap gap-4 text-sm text-slate-700">
            <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name="category_mode" value="new" @checked($categoryMode === 'new')> Yeni kategori</label>
            <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name="category_mode" value="existing" @checked($categoryMode === 'existing')> Mevcut kategoriyi kullan</label>
        </div>
        @error('category_mode')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </fieldset>
        <div class="min-w-0">
        <div id="new-work-category" @class(['hidden' => $categoryMode !== 'new'])>
            <label for="category_name" class="mb-2 block text-sm font-medium text-slate-600">Kategori Adı <span class="text-red-500">*</span></label>
            <input id="category_name" name="category_name" maxlength="255" value="{{ old('category_name', old('name', '')) }}" @required($categoryMode === 'new') @disabled($categoryMode !== 'new') aria-describedby="work-category-help" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <p id="work-category-help" role="status" class="mt-2 text-xs text-slate-500">Plan adından önerilir; değiştirebilirsiniz. Yeni kategori son onayda Tümü tipinde oluşturulur.</p>
            @error('category_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div id="existing-work-category" @class(['hidden' => $categoryMode !== 'existing'])>
            <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Mevcut Kategori <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" @required($categoryMode === 'existing') @disabled($categoryMode !== 'existing') class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="">Kategori seçin</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" data-usages="{{ collect(['malik_payi', 'masraf_yansitma'])->filter(fn ($usage) => $category->{'allow_'.$usage})->implode(',') }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-xs text-slate-500">Gelir ve giderde kullanılabilen aktif kategoriler listelenir. Mevcut kategorinin adı ve tipi değiştirilmez.</p>
            @error('category_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        </div>
    </div>
    <script>
        (() => {
            const planName = document.getElementById('name');
            const categoryName = document.getElementById('category_name');
            const existing = document.getElementById('category_id');
            const dueType = document.getElementById('due_type');
            const help = document.getElementById('work-category-help');
            const categories = @json($categoryNames);
            const normalize = value => value.trim().toLocaleLowerCase('tr-TR');
            let followsPlanName = !categoryName.value || categoryName.value === planName.value;
            const updateHelp = () => {
                const match = categories.find(category => normalize(category.name) === normalize(categoryName.value));
                const blocked = match && (!match.is_active || match.deleted_at || !match['allow_' + dueType?.value]);
                categoryName.setCustomValidity(blocked ? 'Bu adla farklı tipte, pasif veya silinmiş bir kategori var. Başka bir ad kullanın.' : '');
                help.textContent = blocked
                    ? 'Bu ad kullanılamıyor. Mevcut kategoriyi değiştirmeden devam etmek için başka bir ad yazın.'
                    : match ? 'Bu kategori zaten var. Yeni kayıt açılmadan mevcut kategori kullanılacak.'
                    : 'Plan adından önerilir; değiştirebilirsiniz. Yeni kategori son onayda Tümü tipinde oluşturulur.';
            };
            const updateMode = () => {
                const isNew = document.querySelector('input[name="category_mode"]:checked')?.value === 'new';
                document.getElementById('new-work-category').classList.toggle('hidden', !isNew);
                document.getElementById('existing-work-category').classList.toggle('hidden', isNew);
                categoryName.disabled = !isNew;
                categoryName.required = isNew;
                existing.disabled = isNew;
                existing.required = !isNew;
                updateHelp();
            };
            planName.addEventListener('input', () => {
                if (followsPlanName) categoryName.value = planName.value;
                updateHelp();
            });
            categoryName.addEventListener('input', () => {
                followsPlanName = categoryName.value === planName.value;
                updateHelp();
            });
            document.querySelectorAll('input[name="category_mode"]').forEach(input => input.addEventListener('change', updateMode));
            const filterExisting = () => {
                Array.from(existing.options).forEach(option => {
                    if (!option.value) return;
                    const visible = (option.dataset.usages || '').split(',').includes(dueType?.value);
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (!visible && option.selected) existing.value = '';
                });
                updateHelp();
            };
            dueType?.addEventListener('change', filterExisting);
            if (followsPlanName) categoryName.value = planName.value;
            filterExisting();
            updateMode();
        })();
    </script>
@endisset
