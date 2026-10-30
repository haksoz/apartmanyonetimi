<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'allow_aidat',
        'allow_malik_payi',
        'allow_masraf_yansitma',
        'allow_ceza',
        'allow_faiz',
        'allow_expense',
    ];

    public function up(): void
    {
        $this->validateAkbeyMapping();

        Schema::table('categories', function (Blueprint $table) {
            foreach (self::PERMISSIONS as $permission) {
                $table->boolean($permission)->default(false)->after('is_system');
            }
        });

        Schema::table('apartments', function (Blueprint $table) {
            $table->foreignId('default_aidat_category_id')->nullable()->after('manager_unit_id')
                ->constrained('categories')->nullOnDelete();
        });

        // Preserve every category's old selectable uses before the application starts
        // consulting the new permission columns.
        DB::table('categories')->whereIn('type', ['income', 'all'])->update([
            'allow_malik_payi' => true,
            'allow_masraf_yansitma' => true,
        ]);
        DB::table('categories')->whereIn('type', ['expense', 'all'])->update(['allow_expense' => true]);

        DB::table('dues')->whereNotNull('category_id')->select('category_id', 'due_type')->distinct()
            ->orderBy('category_id')->get()->each(function ($usage) {
                $column = match ($usage->due_type) {
                    'aidat' => 'allow_aidat',
                    'malik_payi' => 'allow_malik_payi',
                    'masraf_yansitma' => 'allow_masraf_yansitma',
                    'ceza' => 'allow_ceza',
                    'faiz' => 'allow_faiz',
                    default => null,
                };
                if ($column) {
                    DB::table('categories')->where('id', $usage->category_id)->update([$column => true]);
                }
            });
        DB::table('expenses')->whereNotNull('category_id')->distinct()->pluck('category_id')
            ->each(fn ($id) => DB::table('categories')->where('id', $id)->update(['allow_expense' => true]));
        DB::table('expense_plans')->whereNotNull('category_id')->distinct()->pluck('category_id')
            ->each(fn ($id) => DB::table('categories')->where('id', $id)->update(['allow_expense' => true]));
        foreach (['due_plans', 'assessment_plans'] as $table) {
            DB::table($table)->whereNotNull('category_id')->select('category_id', 'due_type')->distinct()->get()
                ->each(function ($usage) {
                    $column = in_array($usage->due_type, ['aidat', 'malik_payi', 'masraf_yansitma', 'ceza', 'faiz'], true)
                        ? 'allow_'.$usage->due_type
                        : null;
                    if ($column) {
                        DB::table('categories')->where('id', $usage->category_id)->update([$column => true]);
                    }
                });
        }

        DB::table('categories')->where('name', 'Ceza')->update(['allow_ceza' => true]);
        DB::table('categories')->where('name', 'Faiz')->update(['allow_faiz' => true]);

        $this->configureAkbey();
        $this->assignDefaultAidatCategories();
    }

    private function configureAkbey(): void
    {
        $mapping = [
            70 => ['Aidat', 'Genel Aidat', ['allow_aidat']],
            71 => ['Demirbaş', 'Demirbaş', ['allow_malik_payi', 'allow_masraf_yansitma', 'allow_expense']],
            72 => ['Elektrik', 'Ortak Elektrik', ['allow_aidat', 'allow_expense']],
            73 => ['Su', 'Ortak Su', ['allow_aidat', 'allow_expense']],
            74 => ['Asansör', 'Asansör', ['allow_aidat', 'allow_malik_payi', 'allow_expense']],
            75 => ['Temizlik', 'Ortak Temizlik', ['allow_aidat', 'allow_expense']],
            76 => ['Yönetim Giderleri', 'Yönetim Giderleri', ['allow_aidat', 'allow_expense']],
            77 => ['Bakım', 'Bakım', ['allow_malik_payi', 'allow_masraf_yansitma', 'allow_expense']],
            78 => ['Diğer', 'Diğer', ['allow_aidat', 'allow_malik_payi', 'allow_masraf_yansitma', 'allow_expense']],
            79 => ['Asansör Ruhsatlama', 'Asansör Ruhsatlama', ['allow_aidat', 'allow_malik_payi', 'allow_expense']],
            80 => ['Elektrik Sayaç Ayırma', 'Elektrik Sayaç Ayırma', ['allow_malik_payi', 'allow_expense']],
            81 => ['Avukat Susayaç Logar', 'Avukat Susayaç Logar', ['allow_malik_payi', 'allow_expense']],
            82 => ['Kepenk Tente Drenaj', 'Kepenk Tente Drenaj', ['allow_malik_payi', 'allow_expense']],
            83 => ['Su iski', 'Su iski', ['allow_aidat', 'allow_expense']],
            84 => ['Bakım Onarım', 'Bakım Onarım', ['allow_malik_payi', 'allow_masraf_yansitma', 'allow_expense']],
            85 => ['Asansör Bakımı', 'Asansör Bakımı', ['allow_aidat', 'allow_expense']],
        ];

        foreach ($mapping as $id => [$expectedName, $newName, $permissions]) {
            $category = DB::table('categories')->where('id', $id)->where('apartment_id', 8)->first();
            $values = array_fill_keys(self::PERMISSIONS, false);
            foreach ($permissions as $permission) {
                $values[$permission] = true;
            }
            DB::table('categories')->where('id', $id)->update(['name' => $newName, ...$values]);
        }

        foreach ([['Ceza', 'allow_ceza'], ['Faiz', 'allow_faiz']] as [$name, $permission]) {
            $category = DB::table('categories')->where('apartment_id', 8)->where('name', $name)->first();
            if ($category) {
                $values = array_fill_keys(self::PERMISSIONS, false);
                $values[$permission] = true;
                DB::table('categories')->where('id', $category->id)->update($values);
            }
        }
    }

    private function validateAkbeyMapping(): void
    {
        if (! DB::table('apartments')->where('id', 8)->exists()) {
            return;
        }

        $expected = [
            70 => 'Aidat', 71 => 'Demirbaş', 72 => 'Elektrik', 73 => 'Su', 74 => 'Asansör',
            75 => 'Temizlik', 76 => 'Yönetim Giderleri', 77 => 'Bakım', 78 => 'Diğer',
            79 => 'Asansör Ruhsatlama', 80 => 'Elektrik Sayaç Ayırma', 81 => 'Avukat Susayaç Logar',
            82 => 'Kepenk Tente Drenaj', 83 => 'Su iski', 84 => 'Bakım Onarım', 85 => 'Asansör Bakımı',
        ];
        $actual = DB::table('categories')->where('apartment_id', 8)
            ->whereIn('id', array_keys($expected))->pluck('name', 'id');
        $mismatches = [];

        foreach ($expected as $id => $name) {
            $actualName = $actual->get($id);
            if ($actualName !== $name) {
                $mismatches[] = "#{$id}: beklenen '{$name}', bulunan ".($actualName === null ? 'kategori yok' : "'{$actualName}'");
            }
        }

        if ($mismatches !== []) {
            throw new RuntimeException('Akbey kategori eşleştirmesi doğrulanamadı; migration durduruldu. '.implode('; ', $mismatches));
        }
    }

    private function assignDefaultAidatCategories(): void
    {
        foreach (DB::table('apartments')->pluck('id') as $apartmentId) {
            $categoryId = DB::table('due_plans')->where('apartment_id', $apartmentId)
                ->where('due_type', 'aidat')->whereNotNull('category_id')->value('category_id');
            $categoryId ??= DB::table('categories')->where('apartment_id', $apartmentId)
                ->whereIn('name', ['Genel Aidat', 'Aidat'])->orderByRaw("name = 'Genel Aidat' desc")->value('id');
            if ($categoryId) {
                DB::table('apartments')->where('id', $apartmentId)->update(['default_aidat_category_id' => $categoryId]);
                DB::table('categories')->where('id', $categoryId)->update([
                    ...array_fill_keys(self::PERMISSIONS, false),
                    'allow_aidat' => true,
                    'is_active' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('apartments', fn (Blueprint $table) => $table->dropConstrainedForeignId('default_aidat_category_id'));
        DB::table('categories')->where('apartment_id', 8)->where('id', 70)->where('name', 'Genel Aidat')->update(['name' => 'Aidat']);
        DB::table('categories')->where('apartment_id', 8)->where('id', 72)->where('name', 'Ortak Elektrik')->update(['name' => 'Elektrik']);
        DB::table('categories')->where('apartment_id', 8)->where('id', 73)->where('name', 'Ortak Su')->update(['name' => 'Su']);
        DB::table('categories')->where('apartment_id', 8)->where('id', 75)->where('name', 'Ortak Temizlik')->update(['name' => 'Temizlik']);
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(self::PERMISSIONS));
    }
};
