<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPaymentAttempt;
use Illuminate\Http\Request;

class AdminCardTransactionController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $mode = (string) $request->query('mode', '');
        $statuses = ['pending' => 'Sonuç bekleniyor', 'unknown' => 'Sonuç belirsiz', 'succeeded' => 'Başarılı', 'failed' => 'Başarısız', 'cancelled' => 'Kapatıldı'];
        $attempts = SubscriptionPaymentAttempt::query()->where('provider', 'paytr')
            ->with(['subscription.user'])->withCount('notifications')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('merchant_oid', 'like', "%{$search}%")
                        ->orWhereHas('subscription', function ($query) use ($search) {
                            $query->where('order_number', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                        });
                });
            })
            ->when(isset($statuses[$status]), fn ($query) => $query->where('status', $status))
            ->when($status === 'review', fn ($query) => $query->where('review_required', true))
            ->when($status === 'abandoned', fn ($query) => $query->whereHas('subscription', fn ($query) => $query->whereNotNull('checkout_abandoned_at')))
            ->when(in_array($mode, ['test', 'live'], true), fn ($query) => $query->where('is_test', $mode === 'test'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.card-transactions.index', compact('attempts', 'search', 'status', 'mode', 'statuses'));
    }

    public function show(SubscriptionPaymentAttempt $attempt)
    {
        abort_unless($attempt->provider === 'paytr', 404);
        $attempt->load(['subscription.user', 'notifications' => fn ($query) => $query->latest('id')]);

        return view('admin.card-transactions.show', compact('attempt'));
    }
}
