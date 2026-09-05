<?php

use Aws\S3\S3Client;

function makeClient(array $creds, string $endpoint): S3Client
{
    return new S3Client([
        'version' => 'latest',
        'region' => 'eu-west-3',
        'endpoint' => $endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => $creds,
        'http' => ['connect_timeout' => 15, 'timeout' => 30],
    ]);
}

$volviconCreds = ['key' => 'JDvacBdeCub6LJo58fTX', 'secret' => 'XM3iNBiCOMXfxSS6v8ZwplzLYViUPQvaV6VWhXGC'];
$volvicon = makeClient($volviconCreds, 'https://s3.eu-west-3.idrivee2.com');

// Volvicon bucket policy?
try {
    $p = $volvicon->getBucketPolicy(['Bucket' => 'volvicon']);
    echo "VOLVICON_POLICY=" . substr($p['Policy'] ?? '', 0, 500) . "\n";
} catch (\Throwable $e) {
    echo "VOLVICON_POLICY_ERR: " . $e->getMessage() . "\n";
}

// ACL of a legacy image + an avatar in volvicon
foreach (['images/newsletters/featured/05RrFCZQrLsBU5K9q5PndhsU405ubwpnMWfxHZuD.webp', 'avatars/'] as $key) {
    try {
        $acl = $volvicon->getObjectAcl(['Bucket' => 'volvicon', 'Key' => $key]);
        $grants = array_map(fn ($g) => ($g['Grantee']['URI'] ?? 'owner') . ':' . $g['Permission'], $acl['Grants'] ?? []);
        echo "VOLVICON_ACL[$key]=" . implode(', ', $grants) . "\n";
    } catch (\Throwable $e) {
        echo "VOLVICON_ACL[$key] ERR: " . $e->getMessage() . "\n";
    }
}

// Public URL test for a legacy image on the volvicon endpoint
$url = 'https://u0v0.ldn.idrivee2-64.com/volvicon/images/newsletters/featured/05RrFCZQrLsBU5K9q5PndhsU405ubwpnMWfxHZuD.webp';
$code = shell_exec('curl -s -o /dev/null -w "%{http_code}" --max-time 15 ' . escapeshellarg($url) . ' 2>&1');
echo "VOLVICON_PUBLIC_URL_STATUS=$code\n";
