<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('party_type', 32);
            $table->string('legal_name')->nullable();
            $table->string('identity_number', 64)->nullable();
            $table->string('tax_office')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country', 64)->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('billing_profile_id')
                ->nullable()
                ->after('apartment_id')
                ->constrained('billing_profiles')
                ->nullOnDelete();
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->foreignId('billing_profile_id')->nullable()->constrained('billing_profiles')->nullOnDelete();
            $table->string('billing_party_type', 32)->nullable();
            $table->string('billing_label')->nullable();
            $table->string('billing_legal_name')->nullable();
            $table->string('billing_identity_number', 64)->nullable();
            $table->string('billing_tax_office')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_country', 64)->nullable();
            $table->string('billing_province')->nullable();
            $table->string('billing_district')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_postal_code', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_profile_id');
            $table->dropColumn([
                'billing_party_type',
                'billing_label',
                'billing_legal_name',
                'billing_identity_number',
                'billing_tax_office',
                'billing_email',
                'billing_phone',
                'billing_country',
                'billing_province',
                'billing_district',
                'billing_address',
                'billing_postal_code',
            ]);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_profile_id');
        });

        Schema::dropIfExists('billing_profiles');
    }
};
