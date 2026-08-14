License {{ $isActivated ? 'Activated' : 'Deactivated' }} - {{ $software?->name ?? 'Unknown Software' }}

Hello {{ $user?->name ?? 'User' }},

@if($isActivated)
Your license has been activated and is now available for use.

Your {{ $software?->name ?? 'software' }} license has been activated. You can now access the features included in your license package.
@else
Your license has been deactivated.

Your {{ $software?->name ?? 'software' }} license has been deactivated. If you believe this change is incorrect or need assistance, contact support.
@endif

═══════════════════════════════════════════════════
📋 License Information
═══════════════════════════════════════════════════

Software:       {{ $software?->name ?? 'N/A' }}
Version:        {{ $version?->version_number ?? 'N/A' }}
License Type:   {{ $licenseType?->name ?? 'N/A' }}
Package:        {{ $package?->name ?? 'N/A' }}
Status:         {{ $isActivated ? 'ACTIVE' : 'INACTIVE' }}
@if($license->expires_at)
Expires:        {{ \Carbon\Carbon::parse($license->expires_at)->format('F j, Y') }}
@endif
License ID:     {{ $license->uuid }}

═══════════════════════════════════════════════════

@if($isActivated)
You can view your license details and download your license files from your account dashboard.

View License Details: {{ $licenseDetailUrl }}
@else
If you need assistance or have questions about this deactivation, contact support.

View License Details: {{ $licenseDetailUrl }}
Contact Support: {{ $supportUrl }}
@endif

---

Volvicon
{{ config('mail.from.address', 'team@volvicon.com') }}

Website: {{ config('app.url') }}
Support: {{ $supportUrl }}

This is an automated notification. Please do not reply to this email.
