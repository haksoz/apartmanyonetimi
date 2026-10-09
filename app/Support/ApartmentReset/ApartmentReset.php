<?php

namespace App\Support\ApartmentReset;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApartmentReset
{
    /** Testler, finish() öncesi hatayı buradan verir. Canlıda boştur. */
    public static ?\Closure $beforeFinish = null;

    public function __construct(
        private ResetPolicy $policy,
        private ApartmentOperationalData $data,
        private ApartmentResetArchive $archive,
        private SubscriptionCheckout $checkout,
        private LegalConsent $consent,
    ) {}

    public function policy(): ResetPolicy
    {
        return $this->policy;
    }

    public function wipe(User $actor, Apartment $apartment, bool $skipArchiveCheck = false): ApartmentDataOperation
    {
        $operation = $this->open($actor, $apartment, ApartmentDataOperation::ACTION_WIPE, null, 'Aidat, gider, tahsilat ve kasa hareketi');

        if (! $this->deleteAllowed($operation, $skipArchiveCheck)) {
            return $operation->fresh();
        }

        try {
            DB::transaction(function () use ($apartment) {
                $this->data->wipe($apartment);
            });
            $this->beforeFinish($operation);
        } catch (\Throwable $exception) {
            $this->markFailed($operation);

            throw $exception;
        }

        return $this->finish(
            $operation,
            $skipArchiveCheck ? ApartmentDataOperation::ARCHIVE_BYPASSED : ApartmentDataOperation::ARCHIVE_ACCEPTED,
            $apartment->id,
            $skipArchiveCheck
                ? 'İşlem verisi silindi. Yedekleme kontrolü atlandı. Yedek dosyası oluşturulmadı.'
                : 'İşlem verisi silindi. Yedek dosyası oluşturulmadı.',
        );
    }

    /**
     * @param  array{decision: string, active: bool, floor: int|null, ceiling: int|null}  $decision
     */
    public function renew(User $actor, Apartment $apartment, int $unitCount, array $decision, Request $request, bool $skipArchiveCheck = false): ApartmentDataOperation|Apartment
    {
        if ($decision['decision'] === ResetPolicy::QUOTE) {
            return $this->open(
                $actor,
                $apartment,
                ApartmentDataOperation::ACTION_RENEW,
                $unitCount,
                '101 ve üzeri teklif',
                ApartmentDataOperation::RESULT_QUOTE,
                ApartmentDataOperation::ARCHIVE_SKIPPED,
            );
        }

        if ($decision['decision'] === ResetPolicy::BLOCKED_DECREASE) {
            return $this->open(
                $actor,
                $apartment,
                ApartmentDataOperation::ACTION_RENEW,
                $unitCount,
                'Aktif ücretli dönemde daire sayısı düşürülemez',
                ApartmentDataOperation::RESULT_BLOCKED,
                ApartmentDataOperation::ARCHIVE_SKIPPED,
            );
        }

        if ($decision['decision'] === ResetPolicy::NEW_FREE_APARTMENT) {
            return DB::transaction(function () use ($actor, $apartment, $unitCount, $request) {
                $created = $this->createFreeApartment($actor, $apartment, $unitCount, $request);
                $this->open(
                    $actor,
                    $apartment,
                    ApartmentDataOperation::ACTION_NEW_APARTMENT,
                    $unitCount,
                    'Eski apartman korunarak yeni ücretsiz apartman açıldı',
                    ApartmentDataOperation::RESULT_COMPLETED,
                    ApartmentDataOperation::ARCHIVE_SKIPPED,
                    $created->id,
                );

                return $created;
            });
        }

        $operation = $this->open($actor, $apartment, ApartmentDataOperation::ACTION_RENEW, $unitCount, 'Aynı apartmanda kurulum yenileme');

        if (! $this->deleteAllowed($operation, $skipArchiveCheck)) {
            return $operation->fresh();
        }

        try {
            $this->data->renewSetup($apartment, $unitCount, $actor);
            $this->beforeFinish($operation);
        } catch (\Throwable $exception) {
            $this->markFailed($operation);

            throw $exception;
        }

        return $this->finish(
            $operation,
            $skipArchiveCheck ? ApartmentDataOperation::ARCHIVE_BYPASSED : ApartmentDataOperation::ARCHIVE_ACCEPTED,
            $apartment->id,
            $skipArchiveCheck
                ? 'Kurulum yenilendi. Yedekleme kontrolü atlandı. Yedek dosyası oluşturulmadı.'
                : 'Kurulum yenilendi. Yedek dosyası oluşturulmadı.',
        );
    }

    /**
     * Geçici kapı. Gerçek yedek hazır olunca bu atlama kalkar ve yalnızca
     * ApartmentResetArchive::capture() true döndüğünde silme başlar.
     * Atlama, yedek alınmış gibi kaydedilmez.
     */
    private function deleteAllowed(ApartmentDataOperation $operation, bool $skipArchiveCheck): bool
    {
        if ($skipArchiveCheck) {
            $operation->update([
                'archive_status' => ApartmentDataOperation::ARCHIVE_BYPASSED,
                'note' => 'Yedekleme altyapısı kontrolü atlandı. Yedek dosyası oluşturulmadı.',
            ]);

            return true;
        }

        if ($this->archive->capture($operation)) {
            return true;
        }

        $this->failArchive($operation);

        return false;
    }

    private function createFreeApartment(User $actor, Apartment $source, int $unitCount, Request $request): Apartment
    {
        $apartment = DB::transaction(function () use ($actor, $source, $unitCount) {
            $apartment = Apartment::create([
                'user_id' => $actor->id,
                'name' => $source->name,
                'address' => $source->address,
                'province' => $source->province,
                'district' => $source->district,
                'unit_count' => $unitCount,
                'billing_plan' => 'free',
            ]);

            $apartment->members()->attach($actor->id, ['role' => 'owner', 'is_active' => true]);
            $this->checkout->openFree($actor, $apartment);
            Category::createDefaultsFor($apartment->id);

            $openingDate = now()->toDateString();
            for ($i = 1; $i <= $unitCount; $i++) {
                $unitNo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $unit = Unit::create([
                    'apartment_id' => $apartment->id,
                    'unit_no' => $unitNo,
                ]);
                $ownerAccount = Account::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit->id,
                    'type' => Account::TYPE_OWNER,
                    'name' => $unitNo.'. Daire Kat Maliki',
                    'account_opening_date' => $openingDate,
                ]);
                $unit->update([
                    'owner_account_id' => $ownerAccount->id,
                    'occupant_account_id' => $ownerAccount->id,
                ]);
            }

            return $apartment;
        });

        $this->consent->recordResidentData($actor, $apartment, $request);

        return $apartment;
    }

    private function open(
        User $actor,
        Apartment $apartment,
        string $action,
        ?int $unitCount,
        string $scope,
        string $result = ApartmentDataOperation::RESULT_PENDING,
        string $archiveStatus = ApartmentDataOperation::ARCHIVE_UNAVAILABLE,
        ?int $outcomeApartmentId = null,
    ): ApartmentDataOperation {
        return ApartmentDataOperation::query()->create([
            ...ApartmentDataOperation::context($actor, $apartment),
            'outcome_apartment_id' => $outcomeApartmentId,
            'action' => $action,
            'result' => $result,
            'archive_status' => $archiveStatus,
            'requested_unit_count' => $unitCount,
            'scope' => $scope,
        ]);
    }

    private function beforeFinish(ApartmentDataOperation $operation): void
    {
        if (self::$beforeFinish) {
            (self::$beforeFinish)($operation);
        }
    }

    private function markFailed(ApartmentDataOperation $operation): void
    {
        $operation->update([
            'result' => ApartmentDataOperation::RESULT_FAILED,
            'note' => 'İşlem tamamlanamadı.',
        ]);
    }

    private function failArchive(ApartmentDataOperation $operation): ApartmentDataOperation
    {
        $operation->update([
            'result' => ApartmentDataOperation::RESULT_ARCHIVE_FAILED,
            'archive_status' => ApartmentDataOperation::ARCHIVE_UNAVAILABLE,
            'note' => 'Yedekleme altyapısı hazır olmadığı için silme başlatılmadı.',
        ]);

        return $operation->fresh();
    }

    private function finish(ApartmentDataOperation $operation, string $archiveStatus, int $outcomeId, string $note): ApartmentDataOperation
    {
        $operation->update([
            'result' => ApartmentDataOperation::RESULT_COMPLETED,
            'archive_status' => $archiveStatus,
            'outcome_apartment_id' => $outcomeId,
            'note' => $note,
        ]);

        return $operation->fresh();
    }
}
