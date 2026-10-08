<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class LegalDocumentVersion extends Model
{
    protected $fillable = [
        'document_key',
        'version',
        'title',
        'body',
        'content_sha256',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if (static::query()
                ->where('document_key', $version->document_key)
                ->where('version', $version->version)
                ->exists()) {
                throw new RuntimeException('Bu belge anahtarı ve sürüm numarası zaten yayınlanmış.');
            }

            $version->content_sha256 = hash('sha256', (string) $version->body);
            $version->published_at ??= now();
        });

        static::updating(function (): void {
            throw new RuntimeException('Yayınlanmış yasal belge sürümü değiştirilemez.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Yayınlanmış yasal belge sürümü silinemez.');
        });
    }

    public static function publish(string $documentKey, string $version, string $title, string $body): self
    {
        return static::query()->create([
            'document_key' => $documentKey,
            'version' => $version,
            'title' => $title,
            'body' => $body,
        ]);
    }

    public static function current(string $documentKey): ?self
    {
        return static::query()
            ->where('document_key', $documentKey)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }
}
