<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApartmentDataOperation extends Model
{
    public const ACTION_WIPE = 'operational_wipe';

    public const ACTION_RENEW = 'setup_renew';

    public const ACTION_NEW_APARTMENT = 'new_free_apartment';

    public const RESULT_PENDING = 'pending';

    public const RESULT_COMPLETED = 'completed';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_QUOTE = 'quote';

    public const RESULT_AWAITING = 'awaiting_confirmation';

    public const RESULT_ARCHIVE_FAILED = 'archive_failed';

    public const ARCHIVE_UNAVAILABLE = 'unavailable';

    public const ARCHIVE_SKIPPED = 'not_required';

    public const ARCHIVE_ACCEPTED = 'accepted';

    /** Geçici: kullanıcı yedek kontrolünü atladı. Yedek alındığı anlamına gelmez. */
    public const ARCHIVE_BYPASSED = 'bypassed';

    protected $fillable = [
        'user_id',
        'apartment_id',
        'outcome_apartment_id',
        'action',
        'result',
        'archive_status',
        'requested_unit_count',
        'scope',
        'note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }
}
