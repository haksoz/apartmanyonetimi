<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentCoverage;
use App\Support\SubscriptionCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminManagerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $subscriptions = Subscription::query()
            ->with([
                'apartment.members' => function ($query) {
                    $query->where('apartment_user.role', 'owner')
                        ->where('apartment_user.is_active', true);
                },
                'items.subscription.user',
                'items.apartment',
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('subscription_no', 'like', "%{$search}%")
                        ->orWhereHas('apartment', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('apartment.members', function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('users.name', 'like', "%{$search}%")
                                    ->orWhere('users.email', 'like', "%{$search}%");
                            })->where('apartment_user.role', 'owner')
                                ->where('apartment_user.is_active', true);
                        })
                        ->orWhereHas('items', function ($query) use ($search) {
                            $query->where('status', '!=', SubscriptionItem::STATUS_CANCELLED)
                                ->where(function ($query) use ($search) {
                                    $query->where('apartment_name', 'like', "%{$search}%")
                                        ->orWhereHas('apartment', function ($query) use ($search) {
                                            $query->where('name', 'like', "%{$search}%");
                                        })
                                        ->orWhereHas('subscription.user', function ($query) use ($search) {
                                            $query->where('name', 'like', "%{$search}%")
                                                ->orWhere('email', 'like', "%{$search}%");
                                        });
                                });
                        });
                });
            })
            ->orderByRaw(
                'CASE WHEN (select max(started_at) from subscription_items where apartment_subscription_id = subscriptions.id and status != ?) IS NULL THEN 1 ELSE 0 END',
                [SubscriptionItem::STATUS_CANCELLED]
            )
            ->orderByRaw(
                '(select max(started_at) from subscription_items where apartment_subscription_id = subscriptions.id and status != ?) desc',
                [SubscriptionItem::STATUS_CANCELLED]
            )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $legacyItems = SubscriptionItem::query()
            ->whereNull('apartment_subscription_id')
            ->with([
                'subscription.user',
                'apartment.members' => function ($query) {
                    $query->where('apartment_user.role', 'owner')
                        ->where('apartment_user.is_active', true);
                },
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('apartment_name', 'like', "%{$search}%")
                        ->orWhereHas('apartment', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('apartment.members', function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('users.name', 'like', "%{$search}%")
                                    ->orWhere('users.email', 'like', "%{$search}%");
                            })->where('apartment_user.role', 'owner')
                                ->where('apartment_user.is_active', true);
                        })
                        ->orWhereHas('subscription.user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('subscription', function ($query) use ($search) {
                            $query->where('order_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw('CASE WHEN started_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.managers.index', compact('subscriptions', 'legacyItems', 'search'));
    }

    public function show(User $manager)
    {
        $manager->load([
            'subscriptions.package',
            'subscriptions.items',
            'subscriptions.payments',
        ]);

        $orders = $manager->subscriptions->filter(fn (UserSubscription $subscription) => $subscription->items->isNotEmpty());
        $legacyOrders = $manager->subscriptions->filter(fn (UserSubscription $subscription) => $subscription->items->isEmpty());

        return view('admin.managers.show', compact('manager', 'orders', 'legacyOrders'));
    }

    public function approveSubscriptionOrder(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if (! $subscription->isPending()) {
            return back()->withErrors(['subscription' => 'Bu abonelik zaten onaylı veya iptal edilmiş.']);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50', Rule::in(['havale', 'nakit'])],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $hasReceipt = ! empty($subscription->receipt_reference) || ! empty($subscription->receipt_path);

        if ($hasReceipt) {
            if ($subscription->payment_method === 'kredi_kartı') {
                return back()->withErrors(['payment_method' => 'Kredi kartı ödemesi henüz onaylanamaz.']);
            }

            $paymentMethod = $subscription->payment_method ?? 'havale';
            $referenceCode = $subscription->receipt_reference ?? $validated['reference_code'] ?? null;
        } else {
            $paymentMethod = $validated['payment_method'];
            $referenceCode = $validated['reference_code'] ?? null;
        }

        if ($paymentMethod === 'nakit' && empty($referenceCode)) {
            $referenceCode = 'NKT-' . now()->format('Ymd-His') . '-' . strtoupper(Str::random(4));
        }

        DB::transaction(function () use ($subscription, $manager, $paymentMethod, $referenceCode, $validated) {
            if ($subscription->items()->doesntExist()) {
                $this->closeLegacySubscriptions($manager, $subscription);
            }

            if ($subscription->items()->exists()) {
                app(SubscriptionCheckout::class)->activate($subscription);
            } else {
                $expiresAt = $subscription->period === 'yearly' ? now()->addYear() : now()->addMonth();

                $subscription->update([
                    'status' => UserSubscription::STATUS_ACTIVE,
                    'is_active' => true,
                    'started_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            }

            $subscription->payments()->create([
                'amount' => $subscription->price,
                'payment_date' => now(),
                'payment_method' => $paymentMethod,
                'reference_code' => $referenceCode,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return back()->with('status', 'Ödeme onaylandı ve abonelik aktif edildi.');
    }

    public function rejectSubscriptionOrder(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if (! $subscription->isPending()) {
            return back()->withErrors(['subscription' => 'Bu abonelik zaten onaylı veya iptal edilmiş.']);
        }

        $validated = $request->validate([
            'rejection_notes' => ['nullable', 'string'],
        ]);

        $apartmentIds = $subscription->items()->pluck('apartment_id');

        $subscription->items()->update([
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);

        $subscription->update([
            'status' => UserSubscription::STATUS_CANCELLED,
            'cancelled_by' => UserSubscription::CANCELLED_BY_ADMIN,
            'is_active' => false,
            'ended_at' => now(),
            'notes' => $validated['rejection_notes'] ?? $subscription->notes,
        ]);

        ApartmentCoverage::sync($apartmentIds);

        return back()->with('status', 'Sipariş reddedildi.');
    }

    public function reactivateSubscription(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        if ($subscription->is_active) {
            return back()->withErrors(['subscription' => 'Abonelik zaten aktif.']);
        }

        if ($subscription->isCancelled()) {
            return back()->withErrors(['subscription' => 'İptal edilen abonelik geri yüklenemez.']);
        }

        $subscription->update([
            'is_active' => true,
            'status' => UserSubscription::STATUS_ACTIVE,
            'ended_at' => null,
        ]);

        ApartmentCoverage::sync($subscription->items()->pluck('apartment_id'));

        return back()->with('status', 'Abonelik geri yüklendi.');
    }

    public function cancelSubscription(Request $request, User $manager, UserSubscription $subscription)
    {
        if ($subscription->user_id !== $manager->id) {
            return back()->withErrors(['subscription' => 'Abonelik bu kullanıcıya ait değil.']);
        }

        $validated = $request->validate([
            'cancellation_notes' => ['nullable', 'string'],
        ]);

        $apartmentIds = $subscription->items()->pluck('apartment_id');

        $subscription->update([
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
            'cancelled_by' => UserSubscription::CANCELLED_BY_ADMIN,
            'ended_at' => now(),
            'notes' => $validated['cancellation_notes'] ?? $subscription->notes,
        ]);

        ApartmentCoverage::sync($apartmentIds);

        return back()->with('status', 'Abonelik sonlandırıldı.');
    }

    public function grantComplimentary(Request $request, Apartment $apartment, SubscriptionCheckout $checkout)
    {
        if (! $apartment->is_active) {
            return back()->withErrors(['apartment' => 'Pasif apartmana süre tanımlanamaz.']);
        }

        $validated = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $isOwner = $apartment->members()
            ->where('users.id', $validated['user_id'])
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();

        if (! $isOwner) {
            return back()->withErrors(['user_id' => 'Süre, apartmanın güncel yöneticisine tanımlanır.']);
        }

        $checkout->grantComplimentary(
            User::query()->findOrFail($validated['user_id']),
            $apartment,
            (int) $validated['months']
        );

        return back()->with('status', $apartment->name.' için '.$validated['months'].' aylık ücretli kullanım açıldı.');
    }

    public function updateApartmentPrice(Request $request, Apartment $apartment)
    {
        $validated = $request->validate([
            'custom_monthly_price' => ['nullable', 'numeric', 'min:0'],
            'custom_yearly_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $apartment->update([
            'custom_monthly_price' => $validated['custom_monthly_price'] !== null && $validated['custom_monthly_price'] !== ''
                ? $validated['custom_monthly_price']
                : null,
            'custom_yearly_price' => $validated['custom_yearly_price'] !== null && $validated['custom_yearly_price'] !== ''
                ? $validated['custom_yearly_price']
                : null,
        ]);

        return back()->with('status', 'Teklif fiyatı kaydedildi.');
    }

    public function destroy(User $manager)
    {
        if ($manager->role !== User::ROLE_MANAGER) {
            abort(404);
        }

        $name = $manager->name;
        $manager->delete();

        return redirect()
            ->route('admin.managers.index')
            ->with('status', $name.' ve abonelik kaydı silindi.');
    }

    private function closeLegacySubscriptions(User $manager, UserSubscription $except): void
    {
        UserSubscription::query()
            ->where('user_id', $manager->id)
            ->where('is_active', true)
            ->whereKeyNot($except->id)
            ->whereDoesntHave('items')
            ->update([
                'is_active' => false,
                'status' => UserSubscription::STATUS_CANCELLED,
                'ended_at' => now(),
            ]);
    }
}
