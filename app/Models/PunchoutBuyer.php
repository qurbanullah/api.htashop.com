<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PunchoutBuyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'organization_id',
        'contract_id',
        'name',
        'code',
        'protocol',
        'sender_identity',
        'from_identity',
        'to_identity',
        'shared_secret_hash',
        'previous_shared_secret_hash',
        'oci_username',
        'oci_password_hash',
        'secret_rotated_at',
        'allowed_return_domains',
        'default_currency',
        'default_locale',
        'settings',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'allowed_return_domains' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'secret_rotated_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'shared_secret_hash',
        'previous_shared_secret_hash',
        'oci_password_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (PunchoutBuyer $buyer): void {
            if (empty($buyer->uuid)) {
                $buyer->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PunchoutSession::class, 'buyer_id');
    }

    /**
     * Shared secrets are machine credentials: they are high-entropy and are
     * validated on every setup request, so a fast digest with a constant-time
     * comparison is the right tradeoff. Admin input is length-checked instead
     * of relying on a slow password hash.
     */
    public static function hashSecret(?string $secret): ?string
    {
        $secret = trim((string) $secret);

        return $secret === '' ? null : hash('sha256', $secret);
    }

    public function matchesSharedSecret(?string $secret): bool
    {
        $hash = self::hashSecret($secret);

        if ($hash === null) {
            return false;
        }

        foreach ([$this->shared_secret_hash, $this->previous_shared_secret_hash] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && hash_equals($candidate, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function matchesOciPassword(?string $password): bool
    {
        $password = trim((string) $password);

        if ($password === '' || ! is_string($this->oci_password_hash) || $this->oci_password_hash === '') {
            return false;
        }

        return hash_equals($this->oci_password_hash, (string) self::hashSecret($password));
    }

    /**
     * An empty allowlist means "any host the setup request asked for", which is
     * still constrained by the return-URL comparison made at cart time.
     *
     * @return array<int, string>
     */
    public function allowedReturnDomains(): array
    {
        $domains = $this->allowed_return_domains;

        if (! is_array($domains)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($domain) => Str::lower(ltrim(trim((string) $domain), '.')),
            $domains,
        )));
    }

    public function allowsReturnHost(string $host): bool
    {
        $host = Str::lower(trim($host));
        $domains = $this->allowedReturnDomains();

        if ($domains === [] || $host === '') {
            return true;
        }

        foreach ($domains as $domain) {
            if ($host === $domain) {
                return true;
            }

            if (str_starts_with($domain, '*.')) {
                $suffix = substr($domain, 1); // ".example.com"

                if (str_ends_with($host, $suffix) && strlen($host) > strlen($suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Buyer overrides win over tenant defaults; empty values fall through.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return data_get($settings, $key, $default);
    }

    public function branding(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return [
            'logo_url' => data_get($settings, 'branding.logo_url'),
            'primary_color' => data_get($settings, 'branding.primary_color'),
        ];
    }
}
