<?php

namespace App\Models;

use App\Enums\ProductImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One uploaded product file and the progress of applying it.
 *
 * The original file is kept on disk/object storage; `product_import_rows`
 * holds the parsed, validated rows so the apply step never has to re-read the
 * file and can resume after a failure.
 */
class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'organization_id',
        'user_id',
        'original_filename',
        'disk',
        'path',
        'format',
        'kind',
        'size_bytes',
        'status',
        'options',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'failed_count',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'options' => 'array',
        'size_bytes' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            if (empty($import->uuid)) {
                $import->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class, 'import_id');
    }

    public function statusLabel(): string
    {
        return ProductImportStatus::label((string) $this->status);
    }

    public function progressPercentage(): int
    {
        $total = (int) $this->total_rows;

        if ($total === 0) {
            return 0;
        }

        return (int) min(100, round(((int) $this->processed_rows / $total) * 100));
    }
}
