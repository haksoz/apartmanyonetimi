<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\DueBatch;
use App\Services\ConsumptionBatchTracking;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsumptionRepairController extends Controller
{
    public function preview(Request $request, CurrentApartment $current, string $batch, ConsumptionBatchTracking $tracking)
    {
        $apartment = $current->getFor($request->user()) ?? abort(403);
        $selection = $request->validate(['unit_id' => ['nullable', 'integer', 'min:1']]);
        $unitId = isset($selection['unit_id']) ? (int) $selection['unit_id'] : null;

        return DB::transaction(function () use ($request, $apartment, $batch, $tracking, $unitId) {
            Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            $batch = DueBatch::where('apartment_id', $apartment->id)->whereNotNull('consumption_period_id')->lockForUpdate()->findOrFail($batch);

            return $this->confirmationView($request, $batch, $tracking->summary($batch, $unitId), false, $unitId);
        });
    }

    private function confirmationView(Request $request, DueBatch $batch, array $summary, bool $changed = false, ?int $unitId = null)
    {
        $token = (string) Str::uuid();
        $request->session()->put('consumption_repair', ['token' => $token, 'user_id' => auth()->id(), 'apartment_id' => $batch->apartment_id,
            'batch_id' => $batch->id, 'unit_id' => $unitId, 'fingerprint' => hash('sha256', json_encode($summary)), 'expires_at' => now()->addMinutes(30)->timestamp]);

        return view('dues.consumption-repair', compact('batch', 'summary', 'token', 'changed'));
    }

    public function confirm(Request $request, CurrentApartment $current, ConsumptionBatchTracking $tracking)
    {
        $apartment = $current->getFor($request->user()) ?? abort(403);
        $pending = $request->session()->get('consumption_repair');
        abort_unless($pending && is_string($request->input('confirmation_token')) && hash_equals($pending['token'], $request->input('confirmation_token'))
            && $pending['user_id'] === auth()->id() && $pending['apartment_id'] === $apartment->id && $pending['expires_at'] >= now()->timestamp, 419);

        return DB::transaction(function () use ($request, $apartment, $pending, $tracking) {
            Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            $batch = DueBatch::where('apartment_id', $apartment->id)->whereNotNull('consumption_period_id')->lockForUpdate()->findOrFail($pending['batch_id']);
            $unitId = $pending['unit_id'] ?? null;
            $summary = $tracking->summary($batch, $unitId);
            if (! $summary['can_repair'] || ! hash_equals($pending['fingerprint'], hash('sha256', json_encode($summary)))) {
                return $this->confirmationView($request, $batch, $summary, true, $unitId);
            }
            $count = $tracking->repair($batch, $summary);
            $request->session()->forget('consumption_repair');

            return redirect()->route('meters.history.detail', $batch->consumption_period_id)->with('status', "$count eksik borç ilk dağıtımdaki tutarlarla tamamlandı.");
        });
    }
}
