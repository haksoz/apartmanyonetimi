<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminCustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $customers = User::query()
            ->whereNotIn('role', User::adminRoles())
            ->withCount([
                'apartments as apartment_count' => function ($query) {
                    $query->where('apartment_user.is_active', true);
                },
                'subscriptions as pending_order_count' => function ($query) {
                    $query->commercial()->where('status', UserSubscription::STATUS_PENDING);
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search'));
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

    public function show(User $customer)
    {
        $this->ensureCustomer($customer);

        $customer->load([
            'apartments' => function ($query) {
                $query->where('apartment_user.is_active', true)
                    ->orderBy('apartments.name');
            },
        ]);

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
            'summary'
        ));
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
     *
     * @param  int|string  $user
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
        });
    }
}
