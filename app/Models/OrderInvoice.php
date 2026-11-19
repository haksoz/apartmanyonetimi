<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInvoice extends Model
{
    protected $fillable = ['user_subscription_id', 'invoice_number', 'invoice_date', 'created_by', 'snapshot', 'invoice_uuid', 'xml_sha256', 'xml_content', 'xml_details', 'xml_difference_reason', 'xml_imported_by', 'xml_imported_at'];

    protected $casts = ['invoice_date' => 'date', 'snapshot' => 'array', 'xml_details' => 'array', 'xml_imported_at' => 'datetime'];

    protected $hidden = ['xml_content'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trackingNumber(): string
    {
        return 'FTN'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function atmacaText(): string
    {
        return collect($this->snapshot['lines'])->map(function (array $line) {
            $description = preg_replace('/[\t\r\n]+/u', ' ', $line['description']);

            return implode("\t", [$description, '1', number_format($line['net_amount'], 2, '.', ''),
                rtrim(rtrim(number_format($line['tax_rate'], 2, '.', ''), '0'), '.'), '0']);
        })->implode("\n");
    }
}
