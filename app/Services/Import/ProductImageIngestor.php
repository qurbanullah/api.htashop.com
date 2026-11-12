<?php

namespace App\Services\Import;

use App\Models\Dam;
use App\Models\Product;
use App\Services\Storage\S3UploadService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Downloads remote product images and stores them, with resized variants, in
 * the DAM's object storage — the same object layout the management portals
 * produce, so both paths yield identical assets.
 *
 * An image problem is never fatal to a product row: the caller records it as a
 * warning, because a dead CDN link must not block a catalogue import.
 */
class ProductImageIngestor
{
    public const MAX_IMAGES_PER_PRODUCT = 10;

    /** 8 MB keeps a whole import well inside the worker's memory budget. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MIN_WIDTH = 200;

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Widths mirror `manage/src/utils/image-resize.ts`, so an imported image and
     * a portal-uploaded image expose the same `metadata.variants` keys.
     */
    private const VARIANTS = ['large' => 1500, 'medium' => 800, 'small' => 300, 'thumb' => 100];

    public function __construct(
        private readonly S3UploadService $s3,
    ) {}

    /**
     * Attach every URL to the product, stopping at the per-product cap.
     *
     * @param  list<string>  $urls
     * @return array{attached: int, skipped: int, warnings: list<string>}
     */
    public function ingest(Product $product, array $urls, ?int $userId = null): array
    {
        $attached = 0;
        $skipped = 0;
        $warnings = [];

        $urls = array_slice(
            array_values(array_unique(array_filter(array_map('trim', $urls)))),
            0,
            self::MAX_IMAGES_PER_PRODUCT,
        );

        foreach ($urls as $url) {
            try {
                if ($this->ingestOne($product, $url, $userId) === null) {
                    $skipped++;
                } else {
                    $attached++;
                }
            } catch (Throwable $exception) {
                $warnings[] = "Image {$url} was skipped: {$exception->getMessage()}";
            }
        }

        return ['attached' => $attached, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    /**
     * Attach one remote image.
     *
     * Returns `null` when the same file is already attached to the product
     * (content hash), which keeps re-imports idempotent.
     */
    public function ingestOne(
        Product $product,
        string $url,
        ?int $userId = null,
        ?string $collection = null,
        ?int $sortOrder = null,
        ?string $alt = null,
    ): ?Dam {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('it is not an http(s) URL.');
        }

        $response = Http::timeout(20)
            ->connectTimeout(10)
            ->withHeaders(['Accept' => 'image/*'])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("the download failed with HTTP {$response->status()}.");
        }

        $bytes = $response->body();

        if ($bytes === '') {
            throw new RuntimeException('the file is empty.');
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('it is larger than 8 MB.');
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! in_array($mime, self::MIME_TYPES, true)) {
            throw new RuntimeException("its type '{$mime}' is not JPEG, PNG or WebP.");
        }

        $dimensions = @getimagesizefromstring($bytes);

        if ($dimensions === false) {
            throw new RuntimeException('it is not a readable image.');
        }

        [$width, $height] = $dimensions;

        if ($width < self::MIN_WIDTH) {
            throw new RuntimeException('it is only '.$width.'px wide; at least '.self::MIN_WIDTH.'px is required.');
        }

        $checksum = hash('sha256', $bytes);

        if ($product->dams()->where('checksum_sha256', $checksum)->exists()) {
            return null;
        }

        $base = 'products/'.$product->uuid.'/images/'.substr($checksum, 0, 12);
        $variants = $this->storeVariants($bytes, $base, $mime, $width);

        $collection ??= $product->dams()->where('collection_name', Dam::COLLECTION_FEATURED)->exists()
            ? Dam::COLLECTION_GALLERY
            : Dam::COLLECTION_FEATURED;

        return $product->attachDam([
            'uuid' => (string) Str::uuid(),
            'file_name' => basename((string) (parse_url($url, PHP_URL_PATH) ?: 'image')),
            'disk' => (string) config('dam.default_disk', 'idrivee2'),
            'bucket' => (string) config('dam.default_bucket', ''),
            'object_key' => $variants['original'],
            'mime_type' => $mime,
            'size' => strlen($bytes),
            'checksum_sha256' => $checksum,
            'origin_url' => $url,
            'uploaded_by' => $userId,
            'sort_order' => $sortOrder ?? $this->nextSortOrder($product),
            'is_current' => true,
            'metadata' => [
                'variants' => $variants,
                'width' => $width,
                'height' => $height,
                'alt' => $alt ?? $product->name,
            ],
        ], $collection);
    }

    private function nextSortOrder(Product $product): int
    {
        return (int) $product->dams()->max('sort_order') + 1;
    }

    /**
     * Store the original plus the responsive JPEGs under one object prefix.
     *
     * @return array<string, string> suffix → object key (`original` included)
     */
    private function storeVariants(string $bytes, string $base, string $mime, int $width): array
    {
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $originalKey = "{$base}.{$extension}";
        $this->put($originalKey, $bytes, $mime);

        $keys = ['original' => $originalKey];

        foreach (self::VARIANTS as $suffix => $targetWidth) {
            // Never upscale: a small original is its own "large" variant.
            $resized = $width > $targetWidth ? $this->resizeToJpeg($bytes, $targetWidth) : null;

            if ($resized === null) {
                $keys[$suffix] = $originalKey;

                continue;
            }

            $key = "{$base}-{$suffix}.jpg";
            $this->put($key, $resized, 'image/jpeg');
            $keys[$suffix] = $key;
        }

        return $keys;
    }

    private function put(string $key, string $bytes, string $contentType): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'htashop-img');

        if ($temporary === false) {
            throw new RuntimeException('a temporary file could not be created.');
        }

        try {
            file_put_contents($temporary, $bytes);

            $this->s3->uploadFile($temporary, $key, [
                'ContentType' => $contentType,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
        } finally {
            @unlink($temporary);
        }
    }

    private function resizeToJpeg(string $bytes, int $targetWidth): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $newWidth = min($targetWidth, $width);
        $newHeight = max(1, (int) round($height * ($newWidth / $width)));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Flatten transparency onto white: every variant is delivered as JPEG.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $resized = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        return is_string($resized) && $resized !== '' ? $resized : null;
    }
}
