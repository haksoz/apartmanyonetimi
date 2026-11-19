@if ($row['apartment'])
    <a href="{{ route('admin.apartments.show', $row['apartment']) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Apartman</a>
@endif
@if ($row['subscription'])
    <a href="{{ route('admin.subscriptions.show', $row['subscription']) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Abonelik</a>
@endif
@if ($row['can_delete'])
    <button type="button" onclick="openPurgeApartment(@js(route('admin.customers.apartments.destroy', [$customer, $row['apartment']])), @js($row['name']))" class="text-sm font-semibold text-red-700 hover:text-red-800">Kalıcı sil</button>
@endif
