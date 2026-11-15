<?php

namespace App\Console\Commands;

use App\Support\Paytr\Settings;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PaytrCheck extends Command
{
    protected $signature = 'paytr:check';

    protected $description = 'PayTR yerel ayarlarını gizli değerleri göstermeden kontrol eder. Dış istek göndermez.';

    public function handle(Settings $settings): int
    {
        $this->table(['Ayar', 'Durum'], [
            ['Kartla yeni ödeme', config('payments.card_enabled') ? 'Açık' : 'Kapalı'],
            ['Mağaza ID', filled(config('paytr.merchant_id')) ? 'Tanımlı' : 'Eksik'],
            ['Mağaza anahtarı', filled(config('paytr.merchant_key')) ? 'Tanımlı' : 'Eksik'],
            ['Mağaza salt', filled(config('paytr.merchant_salt')) ? 'Tanımlı' : 'Eksik'],
            ['Yeni denemelerin ortamı', config('paytr.test_mode') ? 'Test' : 'Canlı'],
            ['Test aktivasyonu izni', config('paytr.allow_test_settlement') ? 'Açık (hesap/ortam kontrolü uygulanır)' : 'Kapalı'],
            ['Yalnızca test hesapları', config('paytr.test_accounts_only') ? 'Evet' : 'Hayır'],
            ['Yeni üyelik', config('registration.enabled') ? 'Açık' : 'Kapalı'],
        ]);
        if (config('paytr.test_accounts_only')) {
            $emails = config('paytr.test_account_emails', []);
            $missing = false;
            foreach ($emails as $email) {
                $user = \App\Models\User::whereRaw('LOWER(email) = ?', [$email])->first();
                $missing = $missing || ! app(\App\Support\Paytr\Access::class)->isTestAccount($user);
                $this->line($email.': '.(app(\App\Support\Paytr\Access::class)->isTestAccount($user) ? 'Hesap hazır' : 'Hesap bulunamadı veya aktif değil'));
            }
            if ($missing || count($emails) === 0 || (app()->environment('production') && config('registration.enabled'))) {
                $this->error('Test hesapları tanımlanmalı ve production testinde yeni üyelik kapalı olmalıdır.');

                return self::FAILURE;
            }
        }
        try {
            $settings->assertReady();
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->errors()['card_payment']));

            return self::FAILURE;
        }
        $this->line('PayTR paneli Bildirim URL: '.$settings->publicOrigin().'/payments/paytr/callback');
        $this->line('Bu kontrol erişilebilirliği, panel ayarlarını veya gerçek ödemeyi doğrulamaz.');

        return self::SUCCESS;
    }
}
