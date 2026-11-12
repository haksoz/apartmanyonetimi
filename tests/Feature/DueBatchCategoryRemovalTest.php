<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Category, Due, DueBatch, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class DueBatchCategoryRemovalTest extends TestCase
{
    use DatabaseMigrations;

    public function test_category_changes_preserve_group_and_only_actual_dues_block_category_deletion(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        $old = Category::create(['apartment_id' => $apartment->id, 'name' => 'Eski iş', 'allow_malik_payi' => true]);
        $new = Category::create(['apartment_id' => $apartment->id, 'name' => 'Yeni iş', 'allow_malik_payi' => true]);
        $batch = DueBatch::create(['apartment_id' => $apartment->id, 'due_type' => 'malik_payi',
            'source_type' => 'manual', 'distribution_type' => 'equal', 'source_amount' => 200,
            'assessment_date' => '2026-11-01', 'description' => 'İşlem grubu']);
        $first = Due::create(['apartment_id' => $apartment->id, 'due_batch_id' => $batch->id,
            'account_id' => $owner->id, 'due_type' => 'malik_payi', 'category_id' => $old->id,
            'amount' => 100, 'remaining_amount' => 100, 'created_at_manual' => '2026-11-01']);
        $second = $first->replicate(['reference_number']);
        $second->save();
        $this->assertFalse(Schema::hasColumn('due_batches', 'category_id'));
        $beforeGroup = (array) DB::table('due_batches')->find($batch->id);
        $beforeDues = DB::table('dues')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_11_12_000002_drop_category_from_due_batches.php');
        $migration->down();
        DB::table('due_batches')->where('id', $batch->id)->update(['category_id' => $old->id]);
        $migration->up();
        $migration->up();
        $this->assertSame($beforeGroup, (array) DB::table('due_batches')->find($batch->id));
        $this->assertSame($beforeDues, DB::table('dues')->orderBy('id')->get()->toJson());

        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $first->update(['category_id' => $new->id]);
        $this->assertSame($old->id, $second->fresh()->category_id);
        $this->delete(route('categories.destroy', $old))->assertSessionHas('error');
        $this->assertNotSoftDeleted($old);
        $second->update(['category_id' => $new->id]);
        $this->delete(route('categories.destroy', $old))->assertSessionHas('status');
        $this->assertSoftDeleted($old);
        $this->assertSame($beforeGroup, (array) DB::table('due_batches')->find($batch->id));
        $this->assertSame(2, $batch->dues()->count());
        $this->assertEquals(200, $batch->dues()->sum('remaining_amount'));
    }
}
