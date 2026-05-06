<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartment_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();

            $table->unique(['apartment_id', 'user_id']);
        });

        DB::table('apartments')
            ->select(['id', 'user_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->each(function ($apartment) {
                DB::table('apartment_user')->insert([
                    'apartment_id' => $apartment->id,
                    'user_id' => $apartment->user_id,
                    'role' => 'owner',
                    'created_at' => $apartment->created_at,
                    'updated_at' => $apartment->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_user');
    }
};
