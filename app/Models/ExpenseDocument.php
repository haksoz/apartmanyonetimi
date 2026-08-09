<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseDocument extends Model
{
    public const TYPE_INVOICE_IMAGE = 'invoice_image';
    public const TYPE_INVOICE_PDF = 'invoice_pdf';
    public const TYPE_RECEIPT = 'receipt';

    protected $fillable = [
        'expense_id',
        'document_type',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function typeLabel(): string
    {
        return match ($this->document_type) {
            self::TYPE_INVOICE_IMAGE => 'Fatura Görseli',
            self::TYPE_INVOICE_PDF => 'Fatura PDF',
            self::TYPE_RECEIPT => 'Fiş',
            default => 'Doküman',
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return ($this->mime_type ?? '') === 'application/pdf';
    }
}
