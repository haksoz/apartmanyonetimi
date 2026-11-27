@php
    $lateScope = app(\App\Support\LateCompensationScope::class);
    $lateSources = $sources ?? ['default' => $source];
    $lateAllowed = [];
    if ($lateScope->selectionAvailable($apartment)) {
        foreach ($lateSources as $key => $productionSource) {
            $lateAllowed[$key] = array_values(array_filter(\App\Enums\DueType::values(),
                fn ($type) => $lateScope->allows($productionSource, $type)));
        }
    }
    $initiallyAvailable = in_array(old('due_type', $dueType), $lateAllowed['default'] ?? $lateAllowed[$initialSource ?? ''] ?? [], true);
@endphp
@if (array_filter($lateAllowed))
    <div class="rounded-2xl bg-white p-4 text-sm text-slate-700 shadow-sm" data-late-compensation-choice @if (! $initiallyAvailable) hidden @endif>
        <input type="hidden" name="late_compensation_applies" value="0" @disabled(! $initiallyAvailable)>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="late_compensation_applies" value="1"
                @checked(old('late_compensation_applies', $preference ?? false)) @disabled(! $initiallyAvailable)>
            Gecikme tazminatı uygulansın
        </label>
        @error('late_compensation_applies')<p class="mt-1 text-red-600">{{ $message }}</p>@enderror
    </div>
    <script>
        (() => {
            const field = document.currentScript.previousElementSibling;
            const form = field.closest('form');
            const allowed = @json($lateAllowed);
            const defaultType = @json($dueType);
            const sync = () => {
                const source = form.querySelector('[name="source_type"]:checked')?.value;
                const distribution = form.querySelector('select[name="distribution_type"]')?.value;
                const key = distribution === 'consumption' && ['manual', 'expenses'].includes(source)
                    ? 'consumption' : source;
                const type = form.querySelector('[name="due_type"]')?.value ?? defaultType;
                const available = (allowed.default ?? allowed[key] ?? []).includes(type);
                field.hidden = !available;
                field.querySelectorAll('input').forEach(input => input.disabled = !available);
            };
            form.addEventListener('change', sync);
            document.addEventListener('DOMContentLoaded', sync);
            sync();
        })();
    </script>
@endif
