<?php

namespace App\Models;


use App\Enums\ForumReportReasonEnum;
use App\Enums\ForumReportStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ForumReport extends Model
{
        protected $fillable = [
        'uuid',
        'user_id',
        'reportable_id',
        'reportable_type',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ForumReportReasonEnum::class,
            'status' => ForumReportStatusEnum::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $report) {
            if (empty($report->uuid)) {
                $report->uuid = Str::uuid();
            }
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', ForumReportStatusEnum::PENDING->value);
    }
}
