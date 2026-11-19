<section id="customer-apartments" class="mt-6">
    <h2 class="text-lg font-semibold text-slate-900">Apartmanlar ve abonelikler</h2>
    <div class="mt-3 space-y-2 md:hidden">
        @forelse ($apartmentSubscriptions as $row)
            <article class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-semibold text-slate-900">{{ $row['name'] }}</p>
                    <p class="font-mono text-xs text-slate-500">{{ $row['subscription']?->subscription_no ?: '—' }}</p>
                    <p class="text-xs text-slate-500">{{ $row['role'] ? $membershipLabel($row['role']) : ($row['management_history']->isNotEmpty() ? 'Eski yönetici' : 'Geçmiş kayıt') }}</p>
                </div>
                @unless ($row['is_former_manager'])
                <p class="mt-1 text-sm text-slate-600">{{ $periodText($row['period']) }}</p>
                @endunless
                @include('admin.customers.partials.management-periods', ['row' => $row])
                @unless ($row['is_former_manager'])
                <div class="mt-2 flex flex-wrap items-center gap-1">
                    @include('admin.managers.partials.plan-badge', ['plan' => $planLabel($row['period']?->plan)])
                    @include('admin.managers.partials.status-badges', ['status' => $row['subscription'] ? $subscriptionStatus($row['subscription']->status) : '—', 'pending' => false])
                </div>
                @include('admin.customers.partials.apartment-subscription-notes', ['row' => $row])
                <div class="mt-2 flex flex-wrap gap-3">@include('admin.customers.partials.apartment-subscription-actions', ['row' => $row])</div>
                @endunless
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Apartman veya abonelik kaydı yok.</div>
        @endforelse
    </div>
    <div class="mt-3 hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman / Abonelik No</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Rol</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Yönetim dönemi</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Hizmet dönemi</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($apartmentSubscriptions as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><div class="font-medium text-slate-900">{{ $row['name'] }}</div><div class="mt-1 font-mono text-xs text-slate-500">{{ $row['subscription']?->subscription_no ?: '—' }}</div></td>
                        <td class="px-4 py-3 text-slate-700">{{ $row['role'] ? $membershipLabel($row['role']) : ($row['management_history']->isNotEmpty() ? 'Eski yönetici' : 'Geçmiş kayıt') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">@include('admin.customers.partials.management-periods', ['row' => $row])</td>
                        <td class="px-4 py-3">@unless ($row['is_former_manager'])@include('admin.managers.partials.plan-badge', ['plan' => $planLabel($row['period']?->plan)])@endunless</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">@unless ($row['is_former_manager']){{ $periodText($row['period']) }}@endunless</td>
                        <td class="px-4 py-3">
                            @unless ($row['is_former_manager'])
                            @include('admin.managers.partials.status-badges', ['status' => $row['subscription'] ? $subscriptionStatus($row['subscription']->status) : '—', 'pending' => false])
                            @include('admin.customers.partials.apartment-subscription-notes', ['row' => $row])
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-right">@unless ($row['is_former_manager'])<div class="flex flex-wrap justify-end gap-3">@include('admin.customers.partials.apartment-subscription-actions', ['row' => $row])</div>@endunless</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Apartman veya abonelik kaydı yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
