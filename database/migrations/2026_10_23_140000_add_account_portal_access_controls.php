<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('portal_suspended_until')->nullable();
            $table->text('portal_suspension_reason')->nullable();
        });
        Schema::create('account_portal_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason');
            $table->timestamp('suspended_until')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_portal_access_events');
        Schema::table('accounts', fn (Blueprint $table) => $table->dropColumn(['portal_suspended_until', 'portal_suspension_reason']));
    }
};
