<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Definition extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'parent_id',
        'measurement_id',
        'unit_id',
        'name',
        'code',
        'kind',
        'value_type',
        'group_name',
        'section_name',
        'is_required',
        'is_filterable',
        'is_searchable',
        'is_multi',
        'display_type',
        'swatch_type',
        'is_active',
        'validation',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_multi' => 'boolean',
        'is_active' => 'boolean',
        'validation' => 'array',
        'config' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Definition $definition): void {
            if (empty($definition->uuid)) {
                $definition->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'parent_id');
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class, 'measurement_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable')->withTimestamps()->orderBy('labels.sorting')->orderBy('labels.name');
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(Value::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DefinitionTarget::class);
    }

    public function supportsTargetType(string $targetType): bool
    {
        if ($this->relationLoaded('targets')) {
            return $this->targets->contains('target_type', $targetType);
        }

        return $this->targets()->where('target_type', $targetType)->exists();
    }

    public function displayLabel(): string
    {
        $label = $this->resolvePrimaryLabel();

        if ($label) {
            return $label->name;
        }

        return (string) (
            Arr::get($this->config ?? [], 'frontend.label')
            ?? Arr::get($this->config ?? [], 'label')
            ?? $this->name
        );
    }

    public function measurementType(): ?string
    {
        $label = $this->resolvePrimaryLabel();

        if ($label) {
            return $label->slug;
        }

        return $this->resolveMeasurement()?->code;
    }

    public function labelPayload(): ?array
    {
        $label = $this->resolvePrimaryLabel();

        if (!$label) {
            return null;
        }

        return [
            'id' => $label->id,
            'uuid' => $label->uuid,
            'tenant_id' => $label->tenant_id,
            'slug' => $label->slug,
            'display_name' => $label->name,
            'name' => $label->name,
            'summary' => $label->summary,
            'description' => $label->description,
            'sorting' => $label->sorting,
            'is_active' => $label->is_active,
            'metadata' => $label->metadata,
        ];
    }

    public function labelsPayload(): array
    {
        $labels = $this->relationLoaded('labels') ? $this->labels : $this->labels()->get();

        return $labels->map(fn (Label $label) => [
            'id' => $label->id,
            'uuid' => $label->uuid,
            'tenant_id' => $label->tenant_id,
            'slug' => $label->slug,
            'display_name' => $label->name,
            'name' => $label->name,
            'summary' => $label->summary,
            'description' => $label->description,
            'sorting' => $label->sorting,
            'is_active' => $label->is_active,
            'metadata' => $label->metadata,
        ])->values()->all();
    }

    private function resolvePrimaryLabel(): ?Label
    {
        if ($this->relationLoaded('labels') && $this->labels->isNotEmpty()) {
            return $this->labels->first();
        }

        return $this->labels()->first();
    }

    public function measurementPayload(): ?array
    {
        $measurement = $this->resolveMeasurement();

        if (!$measurement) {
            return null;
        }

        return [
            'id' => $measurement->id,
            'uuid' => $measurement->uuid,
            'tenant_id' => $measurement->tenant_id,
            'name' => $measurement->name,
            'code' => $measurement->code,
            'description' => $measurement->description,
            'is_active' => $measurement->is_active,
            'metadata' => $measurement->metadata,
        ];
    }

    private function resolveMeasurement(): ?Measurement
    {
        if ($this->relationLoaded('measurement') && $this->measurement) {
            return $this->measurement;
        }

        if ($this->relationLoaded('unit') && $this->unit) {
            if ($this->unit->relationLoaded('measurement') && $this->unit->measurement) {
                return $this->unit->measurement;
            }

            return $this->unit->measurement()->first();
        }

        if ($this->measurement_id) {
            return $this->measurement()->first();
        }

        if ($this->unit_id) {
            return $this->unit()->with('measurement')->first()?->measurement;
        }

        return null;
    }
}
