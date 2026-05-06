<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'owner_account_id')) {
                $table->foreignId('owner_account_id')->nullable()->after('apartment_id')->constrained('accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('units', 'occupant_account_id')) {
                $table->foreignId('occupant_account_id')->nullable()->after('owner_account_id')->constrained('accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'occupant_account_id')) {
                $table->dropConstrainedForeignId('occupant_account_id');
            }
            if (Schema::hasColumn('units', 'owner_account_id')) {
                $table->dropConstrainedForeignId('owner_account_id');
            }
        });
    }
};
