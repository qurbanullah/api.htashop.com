param(
    [string]$PhpPath,
    [switch]$StartPreviewServer,
    [int]$PreviewPort = 18082
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-PhpExecutable {
    param(
        [string]$PreferredPath
    )

    if ($PreferredPath) {
        if (-not (Test-Path -LiteralPath $PreferredPath)) {
            throw "PHP executable not found at: $PreferredPath"
        }

        return (Resolve-Path -LiteralPath $PreferredPath).Path
    }

    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) {
        return $phpCommand.Source
    }

    $knownPhpPaths = @(
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        'C:\Program Files\PHP\php.exe',
        'C:\php\php.exe'
    )

    foreach ($candidate in $knownPhpPaths) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    throw 'PHP was not found. Install PHP 8.4 and add php.exe to PATH, or pass -PhpPath explicitly.'
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptRoot '..')).Path
$artisanPath = Join-Path $repoRoot 'artisan'
$envPath = Join-Path $repoRoot '.env'
$vendorAutoloadPath = Join-Path $repoRoot 'vendor\autoload.php'
$previewRoot = Join-Path $repoRoot 'public\previews'

if (-not (Test-Path -LiteralPath $artisanPath)) {
    throw "artisan was not found. Run this script from the api.volvicon.com repository. Expected: $artisanPath"
}

if (-not (Test-Path -LiteralPath $envPath)) {
    throw ".env is missing. Create it from .env.example before generating previews."
}

if (-not (Test-Path -LiteralPath $vendorAutoloadPath)) {
    throw "vendor\autoload.php is missing. Install Composer dependencies before generating previews."
}

$phpExe = Resolve-PhpExecutable -PreferredPath $PhpPath

New-Item -ItemType Directory -Force -Path $previewRoot | Out-Null

$generatorPhp = @'
<?php

use App\Enums\LicenseStatusEnum;
use App\Enums\PriorityEnum;
use App\Enums\ReproducibilityEnum;
use App\Enums\SeverityEnum;
use App\Enums\StatusEnum;
use App\Enums\StypeEnum;
use App\Mail\FeedbackCommentMail;
use App\Mail\FeedbackReplyMail;
use App\Mail\FeedbackSubmittedMail;
use App\Mail\LicenseActivatedMail;
use App\Mail\LicenseApprovedMail;
use App\Mail\LicenseRequestCreatedMail;
use App\Mail\Tickets\TicketCreated;
use App\Mail\Tickets\TicketResolved;
use App\Mail\Tickets\TicketUnassigned;
use App\Models\Assignment;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\License;
use App\Models\Ltype;
use App\Models\Package;
use App\Models\Software;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Version;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

$repoRoot = getenv('VOLVICON_API_ROOT');
if (!is_string($repoRoot) || $repoRoot === '') {
    fwrite(STDERR, "VOLVICON_API_ROOT is not set.\n");
    exit(1);
}

require $repoRoot . '/vendor/autoload.php';

$app = require $repoRoot . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$previewDir = $repoRoot . '/public/previews';
if (!is_dir($previewDir) && !mkdir($previewDir, 0777, true) && !is_dir($previewDir)) {
    fwrite(STDERR, "Unable to create preview directory: {$previewDir}\n");
    exit(1);
}

$now = Carbon::now();

$writePreview = static function (string $filename, string $html) use ($previewDir): void {
    file_put_contents($previewDir . '/' . $filename, $html);
    fwrite(STDOUT, "Generated {$filename}\n");
};

$saveModel = static function ($model, array $attributes, ?Carbon $createdAt = null, ?Carbon $updatedAt = null) {
    foreach ($attributes as $key => $value) {
        $model->{$key} = $value;
    }

    $model->save();

    if ($createdAt || $updatedAt) {
        $model->timestamps = false;
        if ($createdAt) {
            $model->created_at = $createdAt;
        }
        if ($updatedAt) {
            $model->updated_at = $updatedAt;
        }
        $model->save();
        $model->timestamps = true;
    }

    return $model->fresh();
};

$reporter = User::firstOrNew(['email' => 'preview.user@volvicon.local']);
$reporter = $saveModel(
    $reporter,
    [
        'name' => 'Preview User',
        'first_name' => 'Preview',
        'last_name' => 'User',
        'password' => 'password',
        'email_verified_at' => $now,
        'organization' => 'Volvicon Preview Lab',
        'job_title' => 'Application Engineer',
    ],
    $now->copy()->subDays(90),
    $now->copy()->subDays(1)
);

$supportUser = User::firstOrNew(['email' => 'preview.support@volvicon.local']);
$supportUser = $saveModel(
    $supportUser,
    [
        'name' => 'Volvicon Team',
        'first_name' => 'Volvicon',
        'last_name' => 'Team',
        'password' => 'password',
        'email_verified_at' => $now,
        'job_title' => 'Customer Success Lead',
    ],
    $now->copy()->subDays(120),
    $now->copy()->subHours(8)
);

$approver = User::firstOrNew(['email' => 'preview.approver@volvicon.local']);
$approver = $saveModel(
    $approver,
    [
        'name' => 'License Approver',
        'first_name' => 'License',
        'last_name' => 'Approver',
        'password' => 'password',
        'email_verified_at' => $now,
        'job_title' => 'Licensing Manager',
    ],
    $now->copy()->subDays(150),
    $now->copy()->subHours(4)
);

$software = Software::firstOrNew(['slug' => 'volvicon-preview-suite']);
$software = $saveModel(
    $software,
    [
        'name' => 'Volvicon Preview Suite',
        'slug' => 'volvicon-preview-suite',
        'summary' => 'Stable preview fixture software used for email rendering.',
        'description' => 'This software record exists only to render local HTML previews for transactional emails.',
        'image' => 'software/volvicon-preview-suite.png',
        'is_active' => true,
        'sorting' => 9990,
    ]
);

$version = Version::firstOrNew([
    'software_id' => $software->id,
    'slug' => 'volvicon-preview-suite-3-2-1',
]);
$version = $saveModel(
    $version,
    [
        'name' => 'Version 3.2.1',
        'slug' => 'volvicon-preview-suite-3-2-1',
        'summary' => 'Preview version for email rendering.',
        'description' => 'Used by the preview generation script.',
        'image' => 'versions/volvicon-preview-suite-3-2-1.png',
        'is_active' => true,
        'sorting' => 9990,
        'software_id' => $software->id,
        'version_number' => '3.2.1',
        'release_date' => $now->copy()->subMonths(2)->toDateString(),
        'download_count' => 0,
        'metadata' => ['preview' => true],
    ]
);

$package = Package::firstOrNew(['slug' => 'preview-professional']);
$package = $saveModel(
    $package,
    [
        'name' => 'Professional',
        'type' => 'commercial',
        'slug' => 'preview-professional',
        'summary' => 'Preview package for transactional mail.',
        'description' => 'Used by the preview generation script.',
        'image' => 'packages/preview-professional.png',
        'is_active' => true,
        'sorting' => 9990,
    ]
);

$licenseType = Ltype::firstOrNew(['slug' => 'preview-floating']);
$licenseType = $saveModel(
    $licenseType,
    [
        'name' => 'Floating',
        'slug' => 'preview-floating',
        'summary' => 'Preview license type for transactional mail.',
        'description' => 'Used by the preview generation script.',
        'image' => 'ltypes/preview-floating.png',
        'is_active' => true,
        'sorting' => 9990,
    ]
);

$feedback = Feedback::firstOrNew(['uuid' => 'f5d1026c-4c51-4415-8d59-915f6a501001']);
$feedback = $saveModel(
    $feedback,
    [
        'uuid' => 'f5d1026c-4c51-4415-8d59-915f6a501001',
        'type' => 'feature_request',
        'name' => 'Preview User',
        'email' => $reporter->email,
        'subject' => 'Preview feedback for the onboarding workflow',
        'message' => "We would like clearer activation guidance in the portal and a more direct way to access license details after approval.",
        'status' => 'replied',
        'priority' => 'high',
        'software_name' => $software->name,
        'software_version' => $version->version_number,
        'operating_system' => 'Windows 11',
        'additional_info' => ['preview' => true],
        'source' => 'portal',
        'user_agent' => 'Preview Generator',
        'ip_address' => '127.0.0.1',
        'admin_response' => 'Thank you. We are refining the email copy and preview workflow.',
        'replied_at' => $now->copy()->subHours(12),
        'replied_by' => $supportUser->id,
    ],
    $now->copy()->subDays(2),
    $now->copy()->subHours(12)
);

$comment = Comment::firstOrNew([
    'commentable_type' => Feedback::class,
    'commentable_id' => $feedback->id,
    'user_id' => $supportUser->id,
    'parent_id' => null,
]);
$comment = $saveModel(
    $comment,
    [
        'commentable_type' => Feedback::class,
        'commentable_id' => $feedback->id,
        'user_id' => $supportUser->id,
        'parent_id' => null,
        'content' => 'We reviewed the request and will align the final wording with the license guide in the documentation portal.',
        'is_internal' => false,
        'is_read' => true,
        'attachments' => [],
        'metadata' => ['preview' => true],
    ],
    $now->copy()->subHours(10),
    $now->copy()->subHours(10)
);

$ticket = Ticket::firstOrNew(['uuid' => '6cb4ab16-40d5-4f47-b8a0-915f6a501002']);
$ticket = $saveModel(
    $ticket,
    [
        'uuid' => '6cb4ab16-40d5-4f47-b8a0-915f6a501002',
        'user_id' => $reporter->id,
        'title' => 'Portal license details page needs clearer download guidance',
        'slug' => 'preview-license-details-guidance',
        'stype' => StypeEnum::FEATURE->value,
        'severity' => SeverityEnum::FEATURE->value,
        'reproducibility' => ReproducibilityEnum::ALWAYS->value,
        'priority' => PriorityEnum::HIGH->value,
        'status' => StatusEnum::RESOLVED->value,
        'is_visible' => true,
        'is_resolved' => true,
        'is_locked' => false,
        'is_archived' => false,
        'description' => 'The license detail page should make the .lic download option and the View License Detail action more explicit after approval.',
        'steps_to_reproduce' => "1. Submit a license request\n2. Approve the request\n3. Open the email\n4. Try to find the download option quickly",
        'additional_information' => 'Used only for local preview rendering.',
        'resolved_on' => $now->copy()->subHours(6),
        'archived_on' => null,
    ],
    $now->copy()->subDays(5),
    $now->copy()->subHours(6)
);

$ticket->setRelation('reporter', $reporter);
$ticket->setRelation('user', $reporter);
$ticket->setRelation('assignee', $supportUser);

$activeAssignment = Assignment::firstOrNew([
    'assignable_type' => Ticket::class,
    'assignable_id' => $ticket->id,
    'assigned_to' => $supportUser->id,
    'is_active' => true,
]);
$saveModel(
    $activeAssignment,
    [
        'assigned_by' => $approver->id,
        'assigned_to' => $supportUser->id,
        'assignable_type' => Ticket::class,
        'assignable_id' => $ticket->id,
        'notes' => 'Preview assignment.',
        'is_active' => true,
        'assigned_at' => $now->copy()->subDays(4),
        'unassigned_at' => null,
    ],
    $now->copy()->subDays(4),
    $now->copy()->subDays(4)
);

$previousAssignee = User::firstOrNew(['email' => 'preview.previous.assignee@volvicon.local']);
$previousAssignee = $saveModel(
    $previousAssignee,
    [
        'name' => 'Previous Assignee',
        'first_name' => 'Previous',
        'last_name' => 'Assignee',
        'password' => 'password',
        'email_verified_at' => $now,
        'job_title' => 'Support Engineer',
    ],
    $now->copy()->subDays(130),
    $now->copy()->subHours(3)
);

$inactiveAssignment = Assignment::firstOrNew([
    'assignable_type' => Ticket::class,
    'assignable_id' => $ticket->id,
    'assigned_to' => $previousAssignee->id,
    'is_active' => false,
]);
$saveModel(
    $inactiveAssignment,
    [
        'assigned_by' => $approver->id,
        'assigned_to' => $previousAssignee->id,
        'assignable_type' => Ticket::class,
        'assignable_id' => $ticket->id,
        'notes' => 'Preview unassignment.',
        'is_active' => false,
        'assigned_at' => $now->copy()->subDays(7),
        'unassigned_at' => $now->copy()->subDays(6),
    ],
    $now->copy()->subDays(7),
    $now->copy()->subDays(6)
);

$license = License::firstOrNew(['uuid' => '0ea8e744-0d65-4c26-a6ec-915f6a501003']);
$license = $saveModel(
    $license,
    [
        'uuid' => '0ea8e744-0d65-4c26-a6ec-915f6a501003',
        'user_id' => $reporter->id,
        'software_id' => $software->id,
        'version_id' => $version->id,
        'package_id' => $package->id,
        'ltype_id' => $licenseType->id,
        'hardware_id' => 'CPU-AB12CD34EF56',
        'license_key' => 'VOL-PRVW-3X9Q-7K2M-A8D1',
        'user_message' => 'Please activate this license for preview rendering.',
        'admin_message' => 'Preview fixture for local HTML email generation.',
        'data_file' => 'licenses/preview/license-preview.dat',
        'license_file' => 'licenses/preview/license-preview.lic',
        'status' => LicenseStatusEnum::APPROVED,
        'is_active' => true,
        'features' => ['activation', 'support', 'portal-access'],
        'modules' => ['core', 'portal'],
        'expires_at' => $now->copy()->addYear(),
    ],
    $now->copy()->subDays(3),
    $now->copy()->subHours(2)
);

$feedback->setRelation('repliedBy', $supportUser);
$comment->setRelation('user', $supportUser);
$license->setRelation('user', $reporter);
$license->setRelation('software', $software);
$license->setRelation('version', $version);
$license->setRelation('package', $package);
$license->setRelation('ltype', $licenseType);

$previewDefinitions = [
    ['feedback-submitted.html', new FeedbackSubmittedMail($feedback)],
    ['feedback-reply.html', new FeedbackReplyMail($feedback, 'We reviewed your feedback and updated the email copy to make the next action clearer for end users.', 'Re: Preview feedback for the onboarding workflow', 'Volvicon Team', 'team@volvicon.com')],
    ['feedback-comment.html', new FeedbackCommentMail($feedback, $comment)],
    ['license-request-user.html', new LicenseRequestCreatedMail($license, 'user')],
    ['license-request-admin.html', new LicenseRequestCreatedMail($license, 'admin')],
    ['license-approved-user.html', new LicenseApprovedMail($license, $approver, $reporter, false)],
    ['license-approved-admin.html', new LicenseApprovedMail($license, $approver, $reporter, true)],
    ['license-activated-user.html', new LicenseActivatedMail($license, true, $approver, $reporter, false)],
    ['license-activated-admin.html', new LicenseActivatedMail($license, true, $approver, $reporter, true)],
    ['license-deactivated-user.html', new LicenseActivatedMail($license, false, $approver, $reporter, false)],
    ['ticket-created.html', new TicketCreated($ticket)],
    ['ticket-resolved-creator.html', new TicketResolved($ticket, 'creator')],
    ['ticket-resolved-support.html', new TicketResolved($ticket, 'support')],
    ['ticket-unassigned-creator.html', new TicketUnassigned($ticket, $previousAssignee, 'creator')],
    ['ticket-unassigned-previous-assignee.html', new TicketUnassigned($ticket, $previousAssignee, 'previous_assignee')],
];

foreach ($previewDefinitions as [$filename, $mailable]) {
    $writePreview($filename, $mailable->render());
}

fwrite(STDOUT, "Preview generation complete. Output directory: {$previewDir}\n");
'@

$tempPhpFile = Join-Path ([System.IO.Path]::GetTempPath()) ('generate-email-previews-' + [System.Guid]::NewGuid().ToString('N') + '.php')

try {
    Set-Content -LiteralPath $tempPhpFile -Value $generatorPhp -Encoding UTF8

    Push-Location $repoRoot
    try {
        $env:VOLVICON_API_ROOT = $repoRoot
        & $phpExe $tempPhpFile
        if ($LASTEXITCODE -ne 0) {
            throw "Preview generation failed with exit code $LASTEXITCODE."
        }
    }
    finally {
        Pop-Location
        Remove-Item Env:VOLVICON_API_ROOT -ErrorAction SilentlyContinue
    }
}
finally {
    Remove-Item -LiteralPath $tempPhpFile -Force -ErrorAction SilentlyContinue
}

if ($StartPreviewServer) {
    Push-Location $repoRoot
    try {
        Write-Host "Starting static preview server on http://localhost:$PreviewPort/previews/" -ForegroundColor Cyan
        & $phpExe -S "localhost:$PreviewPort" -t public
    }
    finally {
        Pop-Location
    }
}
