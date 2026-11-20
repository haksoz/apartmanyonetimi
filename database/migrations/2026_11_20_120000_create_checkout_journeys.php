<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_journeys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('apartment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->string('stage', 32)->default('plan');
            $table->string('period', 16)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->foreignId('billing_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('checkout_journey_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_journey_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('source', 16);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        // Existing records only establish known payment history, not unrecorded form actions.
        DB::table('user_subscriptions')->whereExists(function ($query) {
            $query->selectRaw('1')->from('subscription_payment_attempts')
                ->whereColumn('subscription_id', 'user_subscriptions.id');
        })->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $id = DB::table('checkout_journeys')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'user_id' => $order->user_id,
                    'apartment_id' => DB::table('subscription_items')->where('subscription_id', $order->id)->value('apartment_id'),
                    'order_id' => $order->id, 'stage' => 'payment', 'period' => $order->period,
                    'payment_method' => $order->payment_method, 'billing_profile_id' => $order->billing_profile_id,
                    'quoted_amount' => $order->price, 'last_seen_at' => $order->updated_at,
                    'closed_at' => $order->checkout_abandoned_at,
                    'created_at' => $order->created_at, 'updated_at' => $order->updated_at,
                ]);
                DB::table('checkout_journey_events')->insert([
                    'checkout_journey_id' => $id, 'type' => 'historical', 'source' => 'server',
                    'details' => null, 'created_at' => $order->created_at, 'updated_at' => $order->created_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_journey_events');
        Schema::dropIfExists('checkout_journeys');
    }
};
