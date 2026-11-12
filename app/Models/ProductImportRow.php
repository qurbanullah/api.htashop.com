<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parsed row of an import file.
 *
 * `raw` is the spreadsheet row exactly as read; `normalized` is the payload the
 * apply step will use. `errors` carries field-level messages when the row is
 * rejected, so the operator can fix the file without guessing.
 */
class ProductImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_number',
        'external_id',
        'raw',
        'normalized',
        'status',
        'errors',
        'warnings',
        'product_id',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'raw' => 'array',
        'normalized' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'import_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
