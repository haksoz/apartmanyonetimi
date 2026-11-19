@if ($person && ! $person->isAdminPanelUser())
    <a href="{{ route('admin.customers.show', $person) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">{{ $person->name ?: 'Müşteri' }}</a>
@else
    {{ $person?->name ?: '—' }}
@endif
