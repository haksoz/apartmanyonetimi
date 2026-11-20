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
        $transactionMatch = [];
        preg_match('/^ODM-(\d{2})-(\d{6,})$/iD', $search, $transactionMatch);
        $attempts = SubscriptionPaymentAttempt::query()->where('provider', 'paytr')
            ->with(['subscription.user'])->withCount('notifications')
            ->when($search !== '', function ($query) use ($search, $transactionMatch) {
                $query->where(function ($query) use ($search, $transactionMatch) {
                    $query->where('merchant_oid', 'like', "%{$search}%")
                        ->orWhereHas('subscription', function ($query) use ($search) {
                            $query->where('order_number', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                        });
                    if ($transactionMatch) {
                        $query->orWhere(fn ($query) => $query->whereKey((int) $transactionMatch[2])
                            ->whereYear('created_at', 2000 + (int) $transactionMatch[1]));
                    }
                });
            })
            ->when(isset($statuses[$status]), fn ($query) => $query->where('status', $status))
            ->when(in_array($status, ['pending', 'unknown'], true), fn ($query) => $query
                ->where('review_required', false)->whereHas('subscription', fn ($query) => $query->whereNull('checkout_abandoned_at')))
            ->when($status === 'review', fn ($query) => $query->where('review_required', true))
            ->when($status === 'abandoned', fn ($query) => $query->where('review_required', false)
                ->whereIn('status', ['pending', 'unknown', 'cancelled'])
                ->whereHas('subscription', fn ($query) => $query->whereNotNull('checkout_abandoned_at')))
            ->when(in_array($mode, ['test', 'live'], true), fn ($query) => $query->where('is_test', $mode === 'test'))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.card-transactions.index', compact('attempts', 'search', 'status', 'mode', 'statuses'));
    }

    public function show(SubscriptionPaymentAttempt $attempt)
    {
        abort_unless($attempt->provider === 'paytr', 404);
        $attempt->load(['subscription.user', 'notifications' => fn ($query) => $query->latest('id')]);

        $journey = \App\Models\CheckoutJourney::where('order_id', $attempt->subscription_id)->latest('id')->first();

        return view('admin.card-transactions.show', compact('attempt', 'journey'));
    }
}
