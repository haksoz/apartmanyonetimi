<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPaymentAttempt;
use App\Support\Paytr\HandleNotification;
use App\Support\Paytr\VerifiedNotification;
use Illuminate\Console\Command;

class ResolvePaytrFailedNotifications extends Command
{
    protected $signature = 'paytr:resolve-failed-notifications {--attempt= : Yalnızca bu girişim} {--apply : Uygun kayıtları sonuçlandır}';

    protected $description = 'İmzası doğrulanmış, eksik başarısız bildirim alanları nedeniyle incelemeye düşmüş kayıtları yeniden işler';

    public function handle(HandleNotification $handler): int
    {
        $query = SubscriptionPaymentAttempt::where('provider', 'paytr')->where('review_required', true);
        if ($id = $this->option('attempt')) {
            if (! ctype_digit((string) $id) || (int) $id < 1) { $this->error('Geçerli bir girişim numarası girin.'); return self::FAILURE; }
            $query->whereKey((int) $id);
        }
        $failed = false;
        foreach ($query->orderBy('id')->get() as $attempt) {
            if (! $handler->canRecoverMissingFailureFields($attempt)) {
                $this->line("Girişim {$attempt->id}: kapsam dışında; değiştirilmedi.");
                continue;
            }
            if (! $this->option('apply')) { $this->line("Girişim {$attempt->id}: yeniden işlenebilir (--apply ile uygula)."); continue; }
            $audit = $attempt->notifications()->latest('id')->firstOrFail();
            $accepted = $handler->handle(VerifiedNotification::recordedFailure($audit), false);
            $this->line("Girişim {$attempt->id}: ".($accepted ? 'başarısız ödeme bildirimi sonuçlandırıldı.' : 'diğer kontroller nedeniyle incelemede bırakıldı.'));
            $failed = $failed || ! $accepted;
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
