<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPlan;
use App\Models\DuePlan;
use App\Models\PlanReminderDismissal;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanReminderController extends Controller
{
    public function dismiss(Request $request, CurrentApartment $current)
    {
        $apartment = $current->getFor($request->user()) ?? abort(403);
        $input = $request->validate(['plan_type' => ['required', Rule::in(['aidat', 'assessment'])],
            'plan_id' => ['required', 'integer', 'min:1'], 'period' => ['required', 'date_format:Y-m']]);
        $model = $input['plan_type'] === 'aidat' ? DuePlan::class : AssessmentPlan::class;
        $model::where('apartment_id', $apartment->id)->findOrFail($input['plan_id']);
        PlanReminderDismissal::firstOrCreate(['user_id' => auth()->id(), 'apartment_id' => $apartment->id, ...$input]);

        return redirect()->route('dashboard')->with('status', 'Bu plan için seçilen dönemin eksik kayıt hatırlatması kapatıldı. Plan ayrıntılarındaki kayıtlar korunur.');
    }
}
