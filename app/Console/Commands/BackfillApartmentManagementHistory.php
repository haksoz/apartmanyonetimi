<?php

namespace App\Console\Commands;

use App\Models\Apartment;
use App\Support\ApartmentManagementHistory;
use Illuminate\Console\Command;

class BackfillApartmentManagementHistory extends Command
{
    protected $signature = 'management-history:backfill';

    protected $description = 'Reconstruct management periods from existing memberships and accepted transfers';

    public function handle(ApartmentManagementHistory $history): int
    {
        Apartment::withTrashed()->orderBy('id')->chunkById(100, function ($apartments) use ($history) {
            foreach ($apartments as $apartment) {
                $history->backfill($apartment);
            }
        });
        $this->info('Yönetim geçmişi mevcut kayıtlardan tamamlandı.');

        return self::SUCCESS;
    }
}
