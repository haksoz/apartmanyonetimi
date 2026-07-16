<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DuePlanController;
use App\Models\Apartment;
use App\Models\DuePlan;
use App\Support\AidatPeriodReconciliation;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubscriberApartmentController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartments = $currentApartment->availableFor(auth()->user())
            ->load('user')
            ->sortBy('name')
            ->values();

        if ($apartments->isEmpty()) {
            return redirect()->route('subscriber.apartments.create');
        }

        return view('subscriber.apartments.index', compact('apartments'));
    }

    public function update(Request $request, CurrentApartment $currentApartment)
    {
        $validated = $request->validate([
            'apartment_id' => ['required', 'integer'],
        ]);

        $apartment = $currentApartment->setFor($request->user(), (int) $validated['apartment_id']);

        return redirect()->route('dashboard')->with('status', $apartment->name.' seçildi.');
    }

    public function triggerAidatGeneration(Request $request, Apartment $apartment, AidatPeriodReconciliation $aidatReconciliation, DuePlanController $duePlanController)
    {
        // Kullanıcının bu apartmana erişimi olduğunu kontrol et
        if (! auth()->user()->apartments->contains($apartment->id)) {
            abort(403, 'Bu apartmana erişiminiz yok.');
        }

        $today = Carbon::today();
        $period = $today->format('Y-m');

        $plans = DuePlan::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        if ($plans->isEmpty()) {
            return redirect()->back()->with('error', 'Aktif aidat planı bulunamadı.');
        }

        $totalCreated = 0;
        $messages = [];

        foreach ($plans as $plan) {
            $reconciliation = $aidatReconciliation->reconcile($plan, $period);
            $targetCount = $reconciliation['target_accounts']->count();
            $completedCount = count($reconciliation['completed_account_ids']);

            if ($targetCount > 0 && $completedCount >= $targetCount) {
                $messages[] = "[{$plan->name}] {$period} dönemi zaten tamamlanmış.";
                continue;
            }

            if ($completedCount > 0) {
                $messages[] = "[{$plan->name}] {$period} dönemi kısmen oluşturulmuş ({$completedCount}/{$targetCount}), yönetici onayı bekleniyor.";
                continue;
            }

            $count = $duePlanController->createDuesForPeriod($plan, $period, null, false);
            $totalCreated += $count;

            if ($count === 0) {
                $messages[] = "[{$plan->name}] {$period} dönemi: Aidatlandırılacak daire bulunamadı.";
            } else {
                $messages[] = "[{$plan->name}] {$period} dönemi: {$count} daireye aidat oluşturuldu.";
            }
        }

        $statusMessage = "Aidat üretim tetiklendi. Toplam {$totalCreated} daireye aidat oluşturuldu. " . implode(' ', $messages);

        return redirect()->back()->with('status', $statusMessage);
    }
}
