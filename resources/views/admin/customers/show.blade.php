@extends('layouts.app')

@section('title', 'Müşteri Detayı')

@section('content')
    @php
        $profileEditing = $errors->hasAny(['name', 'email', 'phone', 'address']) || session()->hasOldInput('email');
        $quotaEditing = $errors->has('max_apartments') || session()->hasOldInput('max_apartments');
        $paymentEditing = $errors->has('bank_transfer_allowed') || session()->hasOldInput('bank_transfer_allowed');
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
            @if ($customer->deactivated_at)
                <p class="mt-1 text-sm font-semibold text-slate-500">Pasif</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.checkout-journeys.index', ['customer' => $customer->id]) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ücretli kullanım geçmişi</a>
            <a href="{{ route('admin.customers.billing-profiles.index', $customer) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fatura profilleri</a>
            @if ($customer->deactivated_at)
                <form method="POST" action="{{ route('admin.customers.activate', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tekrar aktifleştir</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.customers.deactivate', $customer) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Pasife al</button>
                </form>
            @endif
            <button type="button" onclick="document.getElementById('purge-customer-modal').classList.remove('hidden')" class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Müşteriyi kalıcı sil</button>
            <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Müşterilere dön</a>
        </div>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Müşteri bilgileri</h2>
                <button type="button" data-edit-target="customer-info-form" aria-controls="customer-info-form-fields" aria-expanded="{{ $profileEditing ? 'true' : 'false' }}" onclick="setCustomerEditing('customer-info-form', true)" @class(['rounded-xl border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50', 'hidden' => $profileEditing])>Düzelt</button>
            </div>
        <form id="customer-info-form" data-customer-editor method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-4">
            <fieldset id="customer-info-form-fields" data-edit-fields @disabled(! $profileEditing) class="min-w-0 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                @method('PATCH')
                <label class="block text-sm text-slate-700">
                    Ad soyad
                    <input type="text" name="name" maxlength="60" data-saved-value="{{ $customer->name }}" value="{{ old('name', $customer->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                    @error('name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm text-slate-700">
                    E-posta
                    <input type="email" name="email" maxlength="100" data-saved-value="{{ $customer->email }}" value="{{ old('email', $customer->email) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                    @error('email')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm text-slate-700">
                    Telefon
                    <input type="tel" name="phone" required inputmode="numeric" pattern="[1-9][0-9]{9}" maxlength="10" data-saved-value="{{ $customer->phone }}" value="{{ old('phone', $customer->phone) }}" placeholder="5551234567" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                    @error('phone')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm text-slate-700">
                    İletişim adresi
                    <textarea name="address" required maxlength="400" rows="3" data-saved-value="{{ $customer->address }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">{{ old('address', $customer->address) }}</textarea>
                    @error('address')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <div class="text-sm">
                    <div class="text-slate-500">Kayıt tarihi</div>
                    <div class="mt-1 font-medium text-slate-900">{{ $customer->created_at?->format('d.m.Y') ?? '—' }}</div>
                </div>
                <div data-edit-actions @class(['flex flex-wrap items-end gap-2', 'hidden' => ! $profileEditing])>
                    <button type="button" onclick="document.getElementById('customer-password-modal').classList.remove('hidden')" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Parolayı güncelle</button>
                    <button type="button" onclick="setCustomerEditing('customer-info-form', false, true)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Vazgeç</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bilgileri kaydet</button>
                </div>
            </fieldset>
        </form>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Aktif apartman limiti</h2>
                <button type="button" data-edit-target="customer-quota-form" aria-controls="customer-quota-form-fields" aria-expanded="{{ $quotaEditing ? 'true' : 'false' }}" onclick="setCustomerEditing('customer-quota-form', true)" @class(['rounded-xl border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50', 'hidden' => $quotaEditing])>Düzelt</button>
            </div>
            <p class="mt-1 text-sm text-slate-500">Yalnızca kabul edilmiş yönetici üyelikleri sayılır. Daire üyeliği ve pasif apartmanlar bu sayıya girmez. Boş bırakılırsa sınır yoktur.</p>
            <form id="customer-quota-form" data-customer-editor method="POST" action="{{ route('admin.customers.quota.update', $customer) }}" class="mt-4">
                <fieldset id="customer-quota-form-fields" data-edit-fields @disabled(! $quotaEditing) class="min-w-0 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="text-sm">
                        <div class="text-slate-500">Aktif apartman</div>
                        <div class="mt-1 text-2xl font-bold text-slate-900">{{ $activeApartmentCount }}</div>
                    </div>
                    <label class="block text-sm text-slate-700">
                        Limit
                        <input type="number" name="max_apartments" data-saved-value="{{ $apartmentLimit ?? '' }}" min="1" max="1000" value="{{ old('max_apartments', $apartmentLimit) }}" class="mt-1 w-32 rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                        @error('max_apartments')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <div data-edit-actions @class(['flex flex-wrap gap-2', 'hidden' => ! $quotaEditing])>
                        <button type="button" onclick="setCustomerEditing('customer-quota-form', false, true)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Vazgeç</button>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Limiti kaydet</button>
                    </div>
                </fieldset>
            </form>
        </section>

        <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Ödeme yöntemleri</h2>
                <button type="button" data-edit-target="customer-payment-form" aria-controls="customer-payment-form-fields" aria-expanded="{{ $paymentEditing ? 'true' : 'false' }}" onclick="setCustomerEditing('customer-payment-form', true)" @class(['rounded-xl border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50', 'hidden' => $paymentEditing])>Düzelt</button>
            </div>
            <p class="mt-2 text-sm text-slate-500">Müşteriler varsayılan olarak kredi kartıyla ödeme yapar. Gerektiğinde bu müşteriye Havale / EFT seçeneğini ayrıca açabilirsiniz.</p>
            <form id="customer-payment-form" data-customer-editor method="POST" action="{{ route('admin.customers.payment-settings.update', $customer) }}" class="mt-4">
                <fieldset id="customer-payment-form-fields" data-edit-fields @disabled(! $paymentEditing) class="min-w-0 space-y-3">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="bank_transfer_allowed" value="0">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="bank_transfer_allowed" data-saved-checked="{{ $customer->bank_transfer_allowed ? '1' : '0' }}" value="1" class="mt-1" @checked(old('bank_transfer_allowed', $customer->bank_transfer_allowed))>
                        <span>Havale / EFT ile ödeme izni</span>
                    </label>
                    @error('bank_transfer_allowed')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div data-edit-actions @class(['flex flex-wrap gap-2', 'hidden' => ! $paymentEditing])>
                        <button type="button" onclick="setCustomerEditing('customer-payment-form', false, true)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Vazgeç</button>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
                    </div>
                </fieldset>
            </form>
        </section>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-sm text-slate-500">Apartmanlar</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $summary['apartments'] }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $customer->apartments->where('is_active', true)->count() }} aktif</div>
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

    @include('admin.customers.partials.apartment-subscriptions')

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
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş tarihi</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
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
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-mono font-medium text-slate-900">{{ $order->order_number ?: '—' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $apartmentNames->isNotEmpty() ? $apartmentNames->join(', ') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @include('admin.orders.partials.badges', ['order' => $order])
                                </div>
                            </td>
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
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Ödeme tarihi</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Yöntem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
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
                            <td class="px-4 py-3 text-slate-700">{{ $paymentMethodLabel($row['payment']->payment_method) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $row['payment']->amount, 2, ',', '.') }} ₺</td>
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
    <div id="purge-customer-modal" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! $errors->has('customer_deletion') && ! $errors->has('confirm_member_deletion') && (! $errors->has('current_password') || old('purge_apartment_id')),
    ])>
        @if ($deletionBlockReason || $errors->has('customer_deletion'))
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="customer-deletion-blocked-title">
                <h2 id="customer-deletion-blocked-title" class="text-lg font-bold text-slate-900">Bu kullanıcı silinemez</h2>
                <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $deletionBlockReason ?: $errors->first('customer_deletion') }}</p>
                <div class="mt-5 flex justify-end">
                    <button type="button" onclick="document.getElementById('purge-customer-modal').classList.add('hidden')" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Tamam</button>
                </div>
            </div>
        @else
        <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="purge-customer-title">
            @csrf
            @method('DELETE')
            <h2 id="purge-customer-title" class="text-lg font-bold text-slate-900">Müşteriyi kalıcı sil</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $customer->name }} kullanıcısı kalıcı olarak silinir. Bu işlem geri alınamaz.</p>
            @if ($memberDeletionNeedsConfirmation)
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Bu kişinin yalnızca bu apartmandaki üyeliği değil, AidatCep kullanıcı hesabı silinecek.</p>
                    <p class="mt-2">Tüm apartman üyelikleri, yöneticilik yetkileri ve daire hesap bağlantıları kaldırılır. Kişi artık giriş yapamaz. Apartmanlar, daire hesapları ve mali hareketler korunur.</p>
                    <p class="mt-2">Kullanıcıya bağlı davetler, kişisel fatura profilleri ve onay kayıtları silinir. Yalnızca erişimi durdurmak istiyorsanız Pasife al işlemini kullanın.</p>
                </div>
                <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="confirm_member_deletion" value="1" required class="mt-1" @checked(old('confirm_member_deletion'))>
                    <span>Bu kullanıcının tüm apartman erişimlerinin ve hesap bağlantılarının kaldırılacağını anladım, kalıcı silmeyi onaylıyorum.</span>
                </label>
                @error('confirm_member_deletion')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @endif
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Kendi parolanız
                <input type="password" name="current_password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('purge-customer-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Kalıcı sil</button>
            </div>
        </form>
        @endif
    </div>
    <div id="purge-apartment-modal" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! (( $errors->has('current_password') && old('purge_apartment_id')) || $errors->has('apartment')),
    ])>
        <form method="POST" action="{{ old('purge_apartment_id') ? route('admin.customers.apartments.destroy', [$customer, old('purge_apartment_id')]) : '#' }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="purge-apartment-title">
            @csrf
            @method('DELETE')
            <input type="hidden" name="purge_apartment_id" value="{{ old('purge_apartment_id') }}">
            <h2 id="purge-apartment-title" class="text-lg font-bold text-slate-900">Apartmanı kalıcı sil</h2>
            <p id="purge-apartment-copy" class="mt-2 text-sm text-slate-600">Pasif apartmanın daire, aidat ve kasa kayıtları silinir. Sipariş ve ödemeler kalır.</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Kendi parolanız
                <input type="password" name="current_password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                @error('apartment')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('purge-apartment-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Kalıcı sil</button>
            </div>
        </form>
    </div>
    <script>
        function setCustomerEditing(formId, editing, discard = false) {
            const form = document.getElementById(formId);
            const fields = form.querySelector('[data-edit-fields]');
            if (discard) {
                fields.querySelectorAll('[data-saved-value]').forEach(input => {
                    input.value = input.dataset.savedValue;
                });
                fields.querySelectorAll('[data-saved-checked]').forEach(input => {
                    input.checked = input.dataset.savedChecked === '1';
                });
            }
            fields.disabled = !editing;
            form.querySelector('[data-edit-actions]').classList.toggle('hidden', !editing);
            const editButton = document.querySelector('[data-edit-target="' + formId + '"]');
            editButton.classList.toggle('hidden', editing);
            editButton.setAttribute('aria-expanded', String(editing));
            if (editing) fields.querySelector('input:not([type="hidden"])')?.focus();
            else editButton.focus();
        }
        document.querySelectorAll('[data-customer-editor]').forEach(form => {
            form.addEventListener('submit', event => {
                if (form.querySelector('[data-edit-fields]').disabled) event.preventDefault();
            });
        });
    </script>
    <script>
        function openPurgeApartment(action, name) {
            const modal = document.getElementById('purge-apartment-modal');
            const form = modal.querySelector('form');
            form.action = action;
            form.querySelector('input[name="purge_apartment_id"]').value = action.split('/').filter(Boolean).pop();
            document.getElementById('purge-apartment-copy').textContent = name + ' apartmanının daire, aidat ve kasa kayıtları silinir. Sipariş ve ödemeler kalır.';
            modal.classList.remove('hidden');
        }
    </script>
    <script>
        document.getElementById('customer-password-modal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
@endsection
