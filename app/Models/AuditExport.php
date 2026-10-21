<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'export_date',
        'disk',
        'path',
        'manifest_path',
        'rows',
        'sha256',
        'manifest_hash',
        'prev_manifest_hash',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'export_date' => 'date',
        'rows' => 'integer',
        'completed_at' => 'datetime',
    ];
}
