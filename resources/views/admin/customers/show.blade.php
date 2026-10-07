@extends('layouts.app')

@section('title', 'Müşteri Detayı')

@section('content')
    @php
        $roleLabel = match ($customer->role) {
            \App\Models\User::ROLE_MANAGER => 'Yönetici',
            \App\Models\User::ROLE_RESIDENT => 'Sakin',
            default => $customer->role ?: '—',
        };
        $membershipLabel = function ($role) {
            return match ($role) {
                'owner' => 'Yönetici',
                'member' => 'Üye',
                default => $role ?: '—',
            };
        };
        $planLabel = function ($plan) {
            return match ($plan) {
                \App\Models\SubscriptionItem::PLAN_PAID => 'Ücretli',
                \App\Models\SubscriptionItem::PLAN_FREE => 'Ücretsiz',
                default => '—',
            };
        };
        $subscriptionStatus = function ($status) {
            return match ($status) {
                \App\Models\Subscription::STATUS_ACTIVE => 'Aktif',
                \App\Models\Subscription::STATUS_ENDED => 'Sona erdi',
                default => $status ?: '—',
            };
        };
        $periodText = function ($item) {
            if (! $item?->started_at) {
                return '—';
            }

            return $item->started_at->format('d.m.Y').' - '.($item->expires_at?->format('d.m.Y') ?? 'süresiz');
        };
        $paymentMethodLabel = function ($method) {
            return match ($method) {
                'kredi_kartı' => 'Kredi kartı',
                'nakit' => 'Nakit',
                'havale' => 'Havale / EFT',
                default => $method ?: '—',
            };
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Müşteri</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $customer->name ?: '—' }}</h1>
        </div>
        <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Müşterilere dön</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Müşteri bilgileri</h2>
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            @method('PATCH')
            <label class="block text-sm text-slate-700">
                Ad soyad
                <input type="text" name="name" value="{{ old('name', $customer->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm text-slate-700">
                E-posta
                <input type="email" name="email" value="{{ old('email', $customer->email) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('email')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm text-slate-700">
                Telefon
                <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('phone')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="text-sm">
                <div class="text-slate-500">Kayıt tarihi</div>
                <div class="mt-1 font-medium text-slate-900">{{ $customer->created_at?->format('d.m.Y') ?? '—' }}</div>
            </div>
            <div class="text-sm">
                <div class="text-slate-500">Durum</div>
                <div class="mt-1 font-medium text-slate-900">{{ $roleLabel }}</div>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="button" onclick="document.getElementById('customer-password-modal').classList.remove('hidden')" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Parolayı güncelle</button>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bilgileri kaydet</button>
            </div>
        </form>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Apartmanlar</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['apartments'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Aktif abonelikler</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['active_subscriptions'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Bekleyen siparişler</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['pending_orders'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Toplam siparişler</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['orders'] }}</div>
        </div>
    </section>

    <section id="customer-apartments" class="mt-6">
        <h2 class="text-lg font-semibold text-slate-900">Apartmanlar</h2>
        <div class="mt-3 space-y-2 md:hidden">
            @forelse ($customer->apartments as $apartment)
                @php
                    $record = $subscriptionForApartment->get($apartment->id);
                    $period = $record?->currentItem();
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-slate-900">{{ $apartment->name ?: '—' }}</p>
                        <p class="text-sm text-slate-600">{{ $record?->subscription_no ?: '—' }}</p>
                        <p class="text-sm text-slate-600">{{ $membershipLabel($apartment->pivot->role) }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $periodText($period) }}
                        <span class="text-slate-300"> · </span>
                        <a href="{{ route('apartments.show', $apartment) }}" class="font-semibold text-slate-700 hover:text-slate-900">Apartman</a>
                        @if ($record)
                            <span class="text-slate-300"> · </span>
                            <a href="{{ route('admin.subscriptions.show', $record) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                        @endif
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-1">
                        @include('admin.managers.partials.plan-badge', ['plan' => $planLabel($period?->plan)])
                        @include('admin.managers.partials.status-badges', ['status' => $record ? $subscriptionStatus($record->status) : '—', 'pending' => false])
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Apartman yok.</div>
            @endforelse
        </div>
        <div class="mt-3 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman / Abonelik No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Rol</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($customer->apartments as $apartment)
                        @php
                            $record = $subscriptionForApartment->get($apartment->id);
                            $period = $record?->currentItem();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $apartment->name ?: '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $record?->subscription_no ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $membershipLabel($apartment->pivot->role) }}</td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.plan-badge', ['plan' => $planLabel($period?->plan)])
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $periodText($period) }}</td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.status-badges', ['status' => $record ? $subscriptionStatus($record->status) : '—', 'pending' => false])
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-3">
                                    <a href="{{ route('apartments.show', $apartment) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Apartman</a>
                                    @if ($record)
                                        <a href="{{ route('admin.subscriptions.show', $record) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Apartman yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="customer-subscriptions" class="mt-6">
        <h2 class="text-lg font-semibold text-slate-900">Abonelikler</h2>
        <div class="mt-3 space-y-2 md:hidden">
            @forelse ($subscriptions as $record)
                @php
                    $period = $record->currentItem();
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-semibold text-slate-900">{{ $record->apartment?->name ?: '—' }}</p>
                        <p class="text-sm text-slate-600">{{ $record->subscription_no ?: '—' }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $periodText($period) }}
                        <span class="text-slate-300"> · </span>
                        <a href="{{ route('admin.subscriptions.show', $record) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-1">
                        @include('admin.managers.partials.plan-badge', ['plan' => $planLabel($period?->plan)])
                        @include('admin.managers.partials.status-badges', ['status' => $subscriptionStatus($record->status), 'pending' => false])
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Abonelik yok.</div>
            @endforelse
        </div>
        <div class="mt-3 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman / Abonelik No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Plan</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($subscriptions as $record)
                        @php
                            $period = $record->currentItem();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $record->apartment?->name ?: '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $record->subscription_no ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.plan-badge', ['plan' => $planLabel($period?->plan)])
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $periodText($period) }}</td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.status-badges', ['status' => $subscriptionStatus($record->status), 'pending' => false])
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.subscriptions.show', $record) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Abonelik yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="customer-orders" class="mt-6">
        <h2 class="text-lg font-semibold text-slate-900">Siparişler</h2>
        <div class="mt-3 space-y-2 md:hidden">
            @forelse ($orders as $order)
                @php
                    $apartmentNames = $order->items
                        ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
                        ->filter()
                        ->unique()
                        ->values();
                    $subscriptionNumbers = collect([$order->subscription?->subscription_no])
                        ->merge($order->items->map(fn ($item) => $item->apartmentSubscription?->subscription_no))
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-mono font-semibold text-slate-900">{{ $order->order_number ?: '—' }}</p>
                        <p class="text-xs text-slate-500">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</p>
                        <p class="text-sm text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $apartmentNames->isNotEmpty() ? $apartmentNames->join(', ') : '—' }}
                        <span class="text-slate-300"> · </span>
                        {{ $subscriptionNumbers->isNotEmpty() ? $subscriptionNumbers->join(', ') : '—' }}
                        <span class="text-slate-300"> · </span>
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                    </p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @include('admin.orders.partials.badges', ['order' => $order])
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Sipariş yok.</div>
            @endforelse
        </div>
        <div class="mt-3 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt Tarihi</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($orders as $order)
                        @php
                            $apartmentNames = $order->items
                                ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
                                ->filter()
                                ->unique()
                                ->values();
                            $subscriptionNumbers = collect([$order->subscription?->subscription_no])
                                ->merge($order->items->map(fn ($item) => $item->apartmentSubscription?->subscription_no))
                                ->filter()
                                ->unique()
                                ->values();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-mono font-medium text-slate-900">{{ $order->order_number ?: '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $subscriptionNumbers->isNotEmpty() ? $subscriptionNumbers->join(', ') : '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $apartmentNames->isNotEmpty() ? $apartmentNames->join(', ') : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @include('admin.orders.partials.badges', ['order' => $order])
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Sipariş yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="customer-payments" class="mt-6">
        <h2 class="text-lg font-semibold text-slate-900">Ödemeler</h2>
        <div class="mt-3 space-y-2 md:hidden">
            @forelse ($payments as $row)
                <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <p class="font-mono font-semibold text-slate-900">{{ $row['order']->order_number ?: '—' }}</p>
                        <p class="text-xs text-slate-500">{{ $row['payment']->payment_date?->format('d.m.Y') ?? '—' }}</p>
                        <p class="text-sm text-slate-700">{{ number_format((float) $row['payment']->amount, 2, ',', '.') }} ₺</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $paymentMethodLabel($row['payment']->payment_method) }}
                        <span class="text-slate-300"> · </span>
                        {{ $row['payment']->reference_code ?: '—' }}
                        <span class="text-slate-300"> · </span>
                        <a href="{{ route('admin.orders.show', $row['order']) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                    </p>
                    <div class="mt-1">
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Tahsil edildi</span>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Bu müşteriye bağlı tahsilat yok.</div>
            @endforelse
        </div>
        <div class="mt-3 hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarih</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Yöntem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Referans</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($payments as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-medium text-slate-900">{{ $row['order']->order_number ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $row['payment']->payment_date?->format('d.m.Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $row['payment']->amount, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3 text-slate-700">{{ $paymentMethodLabel($row['payment']->payment_method) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $row['payment']->reference_code ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Tahsil edildi</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.orders.show', $row['order']) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Bu müşteriye bağlı tahsilat yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div id="customer-password-modal" data-open="{{ $errors->has('password') ? '1' : '0' }}" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! $errors->has('password'),
    ])>
        <form method="POST" action="{{ route('admin.customers.password.update', $customer) }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="customer-password-title">
            @csrf
            @method('PATCH')
            <h2 id="customer-password-title" class="text-lg font-bold text-slate-900">Parolayı güncelle</h2>
            <p class="mt-2 text-sm text-slate-600">Yeni parolayı müşteriye ayrıca bildirin.</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Yeni parola
                <input type="password" name="password" autocomplete="new-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
            </label>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Yeni parola tekrar
                <input type="password" name="password_confirmation" autocomplete="new-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('customer-password-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('customer-password-modal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
@endsection
