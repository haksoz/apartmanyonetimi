<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\ApartmentManagementPeriod;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class AdminApartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $apartments = Apartment::query()->tap([\App\Support\AdminTestData::class, 'apply'])
            ->with(['managers', 'subscriptions' => fn ($query) => $query->orderByDesc('id')])
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('managers', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                        ->orWhereHas('subscriptions', fn ($query) => $query->where('subscription_no', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.apartments.index', compact('apartments', 'search', 'status'));
    }

    public function show(Apartment $apartment)
    {
        $apartment->load(['user', 'managers', 'subscriptions.items.subscription']);
        $subscriptions = $apartment->subscriptions->sortByDesc('id')->values();
        $orders = UserSubscription::withTrashed()->commercial()
            ->where(fn ($query) => $query->whereHas('items', fn ($query) => $query->where('apartment_id', $apartment->id))
                ->orWhereIn('subscription_id', $subscriptions->modelKeys()))
            ->with(['user', 'invoice'])->orderByDesc('id')->paginate(15, ['*'], 'orders_page')->withQueryString();
        $managementPeriods = ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)
            ->with('user')->orderByDesc('started_at')->orderByDesc('id')
            ->paginate(15, ['*'], 'management_page')->withQueryString();
        $currentManagement = ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)
            ->where('is_current', true)->get()->keyBy('user_id');

        return view('admin.apartments.show', compact('apartment', 'subscriptions', 'orders', 'managementPeriods', 'currentManagement'));
    }
}
