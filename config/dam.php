<?php

return [
    // Default disk to use for storing assets (must be defined in filesystems.disks)
    'default_disk' => env('DAM_DEFAULT_DISK', 'idrivee2'),

    // Default bucket name (S3 compatible)
    'default_bucket' => env('IDRIVEE2_BUCKET', ''),

    // Presigned URL expiration minutes
    'presign_expires' => env('DAM_PRESIGN_EXPIRES', 15),
];
