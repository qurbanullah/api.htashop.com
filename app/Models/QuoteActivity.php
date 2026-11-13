<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteActivity extends Model
{
    use HasFactory;

    public const CREATED = 'created';

    public const ASSIGNED = 'assigned';

    public const STATUS_CHANGED = 'status_changed';

    public const RESPONSE_DRAFT = 'response_draft';

    public const RESPONSE_SENT = 'response_sent';

    public const VIEWED = 'viewed';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const NOTE = 'note';

    protected $fillable = [
        'quote_request_id',
        'user_id',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
