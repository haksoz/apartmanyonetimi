<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('plan_year')->nullable()->after('name');
            $table->string('generation_mode', 32)->default('on_schedule')->after('auto_generate');
        });

        $plans = DB::table('due_plans')->orderBy('id')->get();
        foreach ($plans as $plan) {
            $year = null;
            if (! empty($plan->start_date)) {
                $year = (int) substr((string) $plan->start_date, 0, 4);
            }
            if (! $year && ! empty($plan->created_at)) {
                $year = (int) substr((string) $plan->created_at, 0, 4);
            }
            $year = $year ?: (int) date('Y');

            DB::table('due_plans')->where('id', $plan->id)->update([
                'plan_year' => $year,
                'start_date' => sprintf('%04d-01-01', $year),
                'end_date' => sprintf('%04d-12-31', $year),
                'generation_mode' => $plan->generation_mode ?: 'on_schedule',
            ]);
        }

        // Aynı apartman+yıl çakışmalarını ayır (nadir; eski tek-plan sonrası)
        $dupes = DB::table('due_plans')
            ->select('apartment_id', 'plan_year', DB::raw('COUNT(*) as c'))
            ->groupBy('apartment_id', 'plan_year')
            ->having('c', '>', 1)
            ->get();

        foreach ($dupes as $dupe) {
            $ids = DB::table('due_plans')
                ->where('apartment_id', $dupe->apartment_id)
                ->where('plan_year', $dupe->plan_year)
                ->orderByDesc('id')
                ->pluck('id');
            foreach ($ids->slice(1) as $extraId) {
                DB::table('due_plans')->where('id', $extraId)->update([
                    'plan_year' => (int) $dupe->plan_year + 1000 + ((int) $extraId % 100),
                    'is_active' => false,
                ]);
            }
        }

        Schema::table('due_plans', function (Blueprint $table) {
            $table->unique(['apartment_id', 'plan_year'], 'due_plans_apartment_year_unique');
        });
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropUnique('due_plans_apartment_year_unique');
            $table->dropColumn(['plan_year', 'generation_mode']);
        });
    }
};
