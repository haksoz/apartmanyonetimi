<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('access', 10)->default('paid');
            $table->timestamps();
        });

        Schema::create('price_bands', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedInteger('min_units');
            $table->unsignedInteger('max_units')->nullable();
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->boolean('is_quote')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('price_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_band_id')->nullable()->constrained('price_bands')->nullOnDelete();
            $table->string('name');
            $table->decimal('percent_off', 5, 2)->nullable();
            $table->decimal('amount_off', 10, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->foreignId('apartment_id')->nullable()->constrained('apartments')->nullOnDelete();
            $table->string('apartment_name');
            $table->unsignedInteger('unit_count');
            $table->foreignId('price_band_id')->nullable()->constrained('price_bands')->nullOnDelete();
            $table->string('band_label');
            $table->unsignedInteger('band_min_units');
            $table->unsignedInteger('band_max_units')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('TRY');
            $table->timestamps();
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->string('billing_plan', 10)->default('free')->after('is_active');
            $table->decimal('custom_monthly_price', 10, 2)->nullable()->after('billing_plan');
            $table->decimal('custom_yearly_price', 10, 2)->nullable()->after('custom_monthly_price');
        });

        $now = now();

        DB::table('features')->insert([
            ['key' => 'auto_dues', 'name' => 'Otomatik aidat tahakkuku', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'late_fee', 'name' => 'Otomatik gecikme faizi', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notifications', 'name' => 'Otomatik bildirimler', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_pdf', 'name' => 'PDF rapor', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_excel', 'name' => 'Excel dışa aktarma', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_monthly_board', 'name' => 'Aylık aidat panosu', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_due_matrix', 'name' => 'Aidat tahsilat matrisi', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_overdue', 'name' => 'Gecikme raporu', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_annual', 'name' => 'Yıllık faaliyet raporu', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'report_budget', 'name' => 'Bütçe raporu', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'expense_distribution', 'name' => 'Giderin dairelere dağıtılması', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'distribution_advanced', 'name' => 'Metrekare ve pay çarpanı dağıtımı', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'expense_installments', 'name' => 'Gider taksitlendirme', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'documents', 'name' => 'Belge yükleme', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'online_collection', 'name' => 'Online aidat tahsilatı', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bank_integration', 'name' => 'Banka entegrasyonu', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'budget_planning', 'name' => 'Bütçe ve işletme projesi', 'access' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('price_bands')->insert([
            ['label' => '1–14 daire', 'min_units' => 1, 'max_units' => 14, 'monthly_price' => 150, 'yearly_price' => 1500, 'is_quote' => false, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '15–32 daire', 'min_units' => 15, 'max_units' => 32, 'monthly_price' => 300, 'yearly_price' => 3000, 'is_quote' => false, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '33–64 daire', 'min_units' => 33, 'max_units' => 64, 'monthly_price' => 550, 'yearly_price' => 5500, 'is_quote' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '65–150 daire', 'min_units' => 65, 'max_units' => 150, 'monthly_price' => 900, 'yearly_price' => 9000, 'is_quote' => false, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '151+ daire', 'min_units' => 151, 'max_units' => null, 'monthly_price' => null, 'yearly_price' => null, 'is_quote' => true, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['billing_plan', 'custom_monthly_price', 'custom_yearly_price']);
        });

        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('price_campaigns');
        Schema::dropIfExists('price_bands');
        Schema::dropIfExists('features');
    }
};
