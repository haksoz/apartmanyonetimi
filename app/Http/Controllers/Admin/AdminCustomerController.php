<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\UserApartmentQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminCustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('filtre', 'muhataplar');
        if (! in_array($filter, ['muhataplar', 'uyeler', 'tumu'], true)) {
            $filter = 'muhataplar';
        }

        $customers = User::query()
            ->whereNotIn('role', User::adminRoles())
            ->with('quotaOverride')
            ->withCount([
                'apartments as apartment_count' => function ($query) {
                    $query->where('apartment_user.is_active', true);
                },
                'apartments as managed_apartment_count' => function ($query) {
                    $query->where('apartments.is_active', true)
                        ->where('apartment_user.role', 'owner')
                        ->where('apartment_user.is_active', true);
                },
                'subscriptions as pending_order_count' => function ($query) {
                    $query->commercial()->where('status', UserSubscription::STATUS_PENDING);
                },
            ])
            ->withExists([
                'apartments as manages_building' => function ($query) {
                    $query->where('apartments.is_active', true)
                        ->where('apartment_user.role', 'owner')
                        ->where('apartment_user.is_active', true);
                },
            ])
            ->selectSub($this->activeSubscriptionCount(), 'active_subscription_count')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->tap(fn ($query) => $this->applyCustomerFilter($query, $filter))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'filter'));
    }

    public function update(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
        ]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Müşteri bilgileri güncellendi.');
    }

    public function updateQuota(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);

        $validated = $request->validate([
            'max_apartments' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        if ($validated['max_apartments'] === null || $validated['max_apartments'] === '') {
            $customer->quotaOverride()?->delete();
        } else {
            UserQuotaOverride::query()->updateOrCreate(
                ['user_id' => $customer->id],
                ['max_apartments' => (int) $validated['max_apartments']],
            );
        }

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Apartman oluşturma limiti güncellendi.');
    }

    public function updatePassword(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $customer->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Parola güncellendi.');
    }

    public function deactivate(User $customer)
    {
        $this->ensureCustomer($customer);

        $customer->update(['deactivated_at' => now()]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Müşteri pasife alındı.');
    }

    public function activate(User $customer)
    {
        $this->ensureCustomer($customer);

        $customer->update(['deactivated_at' => null]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Müşteri tekrar aktifleştirildi.');
    }

    public function destroy(Request $request, User $customer)
    {
        $this->ensureCustomer($customer);

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $name = $customer->name;

        DB::transaction(function () use ($customer, $request) {
            $customer = User::query()->lockForUpdate()->findOrFail($customer->id);
            if ($reason = $this->customerDeletionBlockReason($customer)) {
                throw ValidationException::withMessages(['customer_deletion' => $reason]);
            }

            if ($this->memberDeletionNeedsConfirmation($customer)) {
                $request->validate(['confirm_member_deletion' => ['accepted']], [
                    'confirm_member_deletion.accepted' => 'Kullanıcının tüm apartman erişimlerinin ve hesap bağlantılarının kaldırılacağını onaylayın.',
                ]);
            }
            $customer->accounts()->withTrashed()->update([
                'user_id' => null, 'portal_suspended_until' => null, 'portal_suspension_reason' => null,
            ]);
            DB::table('sessions')->where('user_id', $customer->id)->delete();
            $customer->delete();
        });

        return redirect()
            ->route('admin.customers.index')
            ->with('status', $name.' kullanıcısı silindi.');
    }

    public function destroyApartment(Request $request, User $customer, Apartment $apartment)
    {
        $this->ensureCustomer($customer);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'purge_apartment_id' => ['nullable', 'integer'],
        ]);

        $isMember = $apartment->members()
            ->whereKey($customer->id)
            ->where('apartment_user.is_active', true)
            ->exists();

        if (! $isMember || $apartment->is_active) {
            throw ValidationException::withMessages([
                'apartment' => 'Yalnızca bu müşteriye bağlı pasif apartman silinebilir.',
            ]);
        }

        DB::transaction(function () use ($customer, $apartment) {
            $this->endSubscriptions($apartment, $customer->id);
            $apartment->forceDelete();
        });

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'Pasif apartman ve işletme kayıtları silindi. Sipariş ve ödemeler duruyor.');
    }

    public function show(User $customer)
    {
        $this->ensureCustomer($customer);

        $customer->load([
            'quotaOverride',
            'apartments' => function ($query) {
                $query->where('apartment_user.is_active', true)
                    ->orderBy('apartments.name');
            },
        ]);

        $activeApartmentCount = app(UserApartmentQuota::class)->currentCount($customer);
        $apartmentLimit = app(UserApartmentQuota::class)->definedLimit($customer);
        $deletionBlockReason = $this->customerDeletionBlockReason($customer);
        $memberDeletionNeedsConfirmation = $this->memberDeletionNeedsConfirmation($customer);

        $subscriptions = $this->subscriptionsFor($customer)
            ->with([
                'apartment',
                'items.subscription',
            ])
            ->orderBy('subscription_no')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();

        $subscriptionForApartment = $subscriptions
            ->groupBy('apartment_id')
            ->map(fn ($group) => $group->sortByDesc(fn (Subscription $subscription) => sprintf(
                '%d-%010d',
                $subscription->status === Subscription::STATUS_ACTIVE ? 1 : 0,
                $subscription->id
            ))->first());

        $orders = UserSubscription::query()
            ->commercial()
            ->where('user_id', $customer->id)
            ->with([
                'subscription',
                'items.apartment',
                'items.apartmentSubscription',
                'payments',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $payments = $orders
            ->flatMap(fn (UserSubscription $order) => $order->payments->map(fn ($payment) => [
                'payment' => $payment,
                'order' => $order,
            ]))
            ->sortByDesc(fn (array $row) => sprintf(
                '%010d-%010d',
                $row['payment']->payment_date?->getTimestamp() ?? 0,
                $row['payment']->id
            ))
            ->values();

        $renewalPassives = ApartmentDataOperation::query()
            ->where('action', ApartmentDataOperation::ACTION_NEW_APARTMENT)
            ->where('result', ApartmentDataOperation::RESULT_COMPLETED)
            ->whereIn('source_apartment_id', $customer->apartments->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('source_apartment_id')
            ->keyBy('source_apartment_id');

        $summary = [
            'apartments' => $customer->apartments->count(),
            'active_subscriptions' => $subscriptions->where('status', Subscription::STATUS_ACTIVE)->count(),
            'pending_orders' => $orders->where('status', UserSubscription::STATUS_PENDING)->count(),
            'orders' => $orders->count(),
        ];

        return view('admin.customers.show', compact(
            'customer',
            'subscriptions',
            'subscriptionForApartment',
            'orders',
            'payments',
            'renewalPassives',
            'summary',
            'activeApartmentCount',
            'apartmentLimit',
            'deletionBlockReason',
            'memberDeletionNeedsConfirmation',
        ));
    }

    private function customerDeletionBlockReason(User $customer): ?string
    {
        if (Apartment::withTrashed()->where('user_id', $customer->id)->exists()) {
            return 'Bu kullanıcı bir apartmanın kurucusu olduğu için kalıcı olarak silinemez. Yöneticiliğin devredilmesi kurucu kaydını kaldırmaz. Hesabına girişini engellemek için Pasife al işlemini kullanabilirsiniz. Apartman ve geçmiş kayıtlar korunur.';
        }

        if ($customer->subscriptions()->withTrashed()->commercial()->exists()) {
            return 'Bu kullanıcının kendi adına ticari sipariş veya ödeme geçmişi bulunduğu için kalıcı olarak silinemez. Ticari kayıtları korumak için Pasife al işlemini kullanabilirsiniz.';
        }

        return null;
    }

    private function memberDeletionNeedsConfirmation(User $customer): bool
    {
        return $customer->accounts()->withTrashed()->exists()
            || DB::table('apartment_user')->where('user_id', $customer->id)->exists()
            || $customer->billingProfiles()->exists()
            || DB::table('legal_acceptances')->where('user_id', $customer->id)->exists()
            || DB::table('management_offers')->where('user_id', $customer->id)->exists()
            || DB::table('account_portal_offers')->where('user_id', $customer->id)->exists()
            || DB::table('account_portal_access_events')->where('user_id', $customer->id)->orWhere('performed_by', $customer->id)->exists();
    }

    private function endSubscriptions(Apartment $apartment, ?int $retainedUserId): void
    {
        Subscription::query()
            ->where('apartment_id', $apartment->id)
            ->get()
            ->each(function (Subscription $subscription) use ($apartment, $retainedUserId) {
                $subscription->update([
                    'apartment_name' => $apartment->name,
                    'retained_user_id' => $retainedUserId,
                    'status' => Subscription::STATUS_ENDED,
                    'ended_at' => now(),
                ]);

                $subscription->items()
                    ->whereIn('status', [SubscriptionItem::STATUS_ACTIVE, SubscriptionItem::STATUS_PENDING])
                    ->update([
                        'status' => SubscriptionItem::STATUS_CANCELLED,
                        'ended_at' => now(),
                    ]);
            });
    }

    private function applyCustomerFilter(Builder $query, string $filter): void
    {
        $ownerMembership = function ($query): void {
            $query->where('apartments.is_active', true)
                ->where('apartment_user.role', 'owner')
                ->where('apartment_user.is_active', true);
        };

        if ($filter === 'uyeler') {
            $query->whereNotNull('activated_at')
                ->where('role', '!=', User::ROLE_MANAGER)
                ->whereDoesntHave('apartments', $ownerMembership)
                ->whereDoesntHave('ownedApartments', fn ($q) => $q->withTrashed())
                ->whereDoesntHave('subscriptions', fn ($q) => $q->withTrashed()->commercial());

            return;
        }

        if ($filter === 'tumu') {
            return;
        }

        $query->whereNotNull('activated_at')
            ->where(function ($query) use ($ownerMembership) {
                $query->where('role', User::ROLE_MANAGER)
                    ->orWhereHas('apartments', $ownerMembership)
                    ->orWhereHas('ownedApartments', fn ($q) => $q->withTrashed())
                    ->orWhereHas('subscriptions', fn ($q) => $q->withTrashed()->commercial());
            });
    }

    private function ensureCustomer(User $customer): void
    {
        abort_if($customer->isAdminPanelUser(), 404);
    }

    private function activeSubscriptionCount(): Builder
    {
        return Subscription::query()
            ->selectRaw('count(distinct subscriptions.id)')
            ->where('subscriptions.status', Subscription::STATUS_ACTIVE)
            ->where(fn ($query) => $this->constrainToCustomer($query, 'users.id'));
    }

    private function subscriptionsFor(User $customer): Builder
    {
        return Subscription::query()
            ->where(fn ($query) => $this->constrainToCustomer($query, $customer->id));
    }

    /**
     * Kalıcı abonelik, müşterinin aktif apartment_user kaydından
     * veya müşterinin siparişindeki gerçek abonelik bağından gelir.
     */
    private function constrainToCustomer($query, int|string $user): void
    {
        $matchUser = function ($query, string $column) use ($user): void {
            if (is_int($user)) {
                $query->where($column, $user);

                return;
            }

            $query->whereColumn($column, $user);
        };

        $query->whereExists(function ($query) use ($matchUser) {
            $query->selectRaw('1')
                ->from('apartment_user')
                ->whereColumn('apartment_user.apartment_id', 'subscriptions.apartment_id')
                ->where('apartment_user.is_active', true);
            $matchUser($query, 'apartment_user.user_id');
        })->orWhereExists(function ($query) use ($matchUser) {
            $query->selectRaw('1')
                ->from('user_subscriptions')
                ->whereColumn('user_subscriptions.subscription_id', 'subscriptions.id');
            $matchUser($query, 'user_subscriptions.user_id');
        })->orWhereExists(function ($query) use ($matchUser) {
            $query->selectRaw('1')
                ->from('subscription_items')
                ->join('user_subscriptions as customer_orders', 'customer_orders.id', '=', 'subscription_items.subscription_id')
                ->whereColumn('subscription_items.apartment_subscription_id', 'subscriptions.id');
            $matchUser($query, 'customer_orders.user_id');
        })->orWhere(function ($query) use ($matchUser) {
            $matchUser($query, 'subscriptions.retained_user_id');
        });
    }
}
