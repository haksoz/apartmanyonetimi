<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Package;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriberSubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()
            ->subscriptions()
            ->with('package')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('subscriber.subscriptions.index', compact('subscriptions'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $currentSubscription = $user->subscription?->load('package');
        $requestedPackage = Package::where('is_active', true)->find($request->query('package_id'));
        $type = in_array($request->query('type'), ['renew', 'upgrade']) ? $request->query('type') : 'renew';

        $packages = Package::where('is_active', true)
            ->where('is_trial', false)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();

        if ($type === 'upgrade' && $currentSubscription?->package) {
            $packages = $packages->filter(fn (Package $package) => $package->monthly_price > $currentSubscription->package->monthly_price);
        }

        return view('subscriber.subscriptions.create', compact(
            'currentSubscription',
            'requestedPackage',
            'type',
            'packages'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => ['required', Rule::exists('packages', 'id')->where('is_active', true)],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['required', Rule::in(['havale', 'kredi_kartı'])],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $isHavale = $validated['payment_method'] === 'havale';

        $user = auth()->user();
        $package = Package::findOrFail($validated['package_id']);
        $price = $validated['period'] === 'yearly' ? $package->yearly_price : $package->monthly_price;

        $defaultFeatureAutoDues = $package->features->where('feature_key', 'Otomatik aidat planlama')->first()?->is_enabled ?? false;
        $defaultFeatureUserPortal = $package->features->where('feature_key', 'Kullanıcı portalı erişimi')->first()?->is_enabled ?? false;
        $defaultFeatureReports = $package->features->where('feature_key', 'Hesap ekstresi ve raporlar')->first()?->is_enabled ?? false;
        $defaultFeatureMultiApartment = $package->features->where('feature_key', 'Çoklu apartman yönetimi')->first()?->is_enabled ?? false;

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("receipts/{$user->id}", 'public');
        }

        $notes = $isHavale
            ? 'Kullanıcı tarafından abone panelinden oluşturuldu (Havale/EFT).'
            : 'Kullanıcı tarafından abone panelinden oluşturuldu (Kredi Kartı - ödeme bekleniyor).';

        $orderNumber = $this->generateOrderNumber();

        $subscription = UserSubscription::create([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'period' => $validated['period'],
            'price' => $price,
            'started_at' => now(),
            'expires_at' => null,
            'is_active' => false,
            'is_trial' => false,
            'status' => UserSubscription::STATUS_PENDING,
            'notes' => $notes,
            'feature_auto_dues' => $defaultFeatureAutoDues,
            'feature_user_portal' => $defaultFeatureUserPortal,
            'feature_reports' => $defaultFeatureReports,
            'feature_multi_apartment' => $defaultFeatureMultiApartment,
            'multi_apartment_limit_override' => $defaultFeatureMultiApartment ? $package->multi_apartment_limit : null,
            'payment_method' => $validated['payment_method'],
            'receipt_path' => $receiptPath,
            'receipt_reference' => $validated['reference_code'] ?? null,
        ]);

        $message = $isHavale
            ? 'Siparişiniz alındı. Havale/EFT ödemesi için banka bilgilerini görüntüleyebilirsiniz.'
            : 'Siparişiniz alındı. Kredi kartı ödeme altyapısı entegre edildiğinde buradan ödemenizi tamamlayabileceksiniz.';

        return redirect()->route('subscriber.subscriptions.receipt', $subscription)
            ->with('status', $message);
    }

    public function receipt(UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        $accounts = BankAccount::active()->ordered()->get();

        return view('subscriber.subscriptions.receipt', compact('subscription', 'accounts'));
    }

    public function paymentInfo(Request $request, UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        $validated = $request->validate([
            'reference_code' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        if (empty($validated['reference_code']) && ! $request->hasFile('receipt')) {
            throw ValidationException::withMessages([
                'payment_info' => 'Referans numarası veya dekont dosyası alanlarından en az biri doldurulmalıdır.',
            ]);
        }

        $data = [];

        if (! empty($validated['reference_code'])) {
            $data['receipt_reference'] = $validated['reference_code'];
        }

        if ($request->hasFile('receipt')) {
            if ($subscription->receipt_path) {
                Storage::disk('public')->delete($subscription->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('receipts/' . auth()->id(), 'public');
        }

        $subscription->update($data);

        return back()->with('status', 'Ödeme bilgileriniz başarıyla kaydedildi.');
    }

    private function authorizeSubscription(UserSubscription $subscription): void
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function generateOrderNumber(): string
    {
        $year = now()->format('y');

        do {
            $random = strtoupper(substr(uniqid('', true), -6));
            $number = "SIP-{$year}-{$random}";
        } while (UserSubscription::where('order_number', $number)->exists());

        return $number;
    }
}
