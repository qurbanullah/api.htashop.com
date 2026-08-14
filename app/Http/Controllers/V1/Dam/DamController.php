<?php

namespace App\Http\Controllers\V1\Dam;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Dam\DamAssetResource;
use App\Http\Responses\V1\ApiResponse;
use App\Services\Dam\DamService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DamController extends Controller
{
    public function __construct(protected DamService $service)
    {
    }

    protected function validationError($validator): JsonResponse
    {
        return ApiResponse::error('Validation failed', $validator->errors(), 422);
    }

    public function owners(\App\Http\Requests\V1\Dam\ReadDamOwnersRequest $request): JsonResponse
    {


        return ApiResponse::success(
            $this->service->readOwners($request->only(['type', 'query', 'limit'])),
            'DAM owners retrieved successfully'
        );
    }

    public function index(\App\Http\Requests\V1\Dam\ReadDamAssetsRequest $request): JsonResponse
    {


        return ApiResponse::success(
            DamAssetResource::collection($this->service->readAssets($request->only([
                'damable_type',
                'damable_id',
                'collection_name',
                'collection_key',
            ]))),
            'DAM assets retrieved successfully'
        );
    }

    public function generatePresignedUrl(\App\Http\Requests\V1\Dam\GeneratePresignedUrlRequest $request): JsonResponse
    {


        try {
            return ApiResponse::success(
                $this->service->generatePresignedUploadUrl(
                    $request->string('directory')->toString(),
                    $request->string('filename')->toString(),
                    $request->string('content_type')->toString(),
                    (int) $request->input('expires_in', 3600),
                    $request->integer('max_file_size') ?: null,
                ),
                'Upload URL generated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate DAM upload URL', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to generate upload URL', ['error' => $e->getMessage()], 500);
        }
    }

    public function presign(\App\Http\Requests\V1\Dam\PresignRequest $request): JsonResponse
    {


        try {
            $upload = $this->service->generatePresignedUploadUrl(
                $request->input('prefix', 'uploads'),
                $request->string('file_name')->toString(),
                $request->string('content_type')->toString(),
                (int) $request->input('expires', (int) config('dam.presign_expires', 15) * 60),
            );

            return ApiResponse::success([
                'object_key' => $upload['key'],
                'bucket' => $upload['bucket'],
                'upload' => $upload,
            ], 'Upload URL generated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to generate DAM presign payload', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to generate upload URL', ['error' => $e->getMessage()], 500);
        }
    }

    public function initiateMultipartUpload(\App\Http\Requests\V1\Dam\InitiateMultipartUploadRequest $request): JsonResponse
    {


        try {
            return ApiResponse::success(
                $this->service->initiateMultipartUpload(
                    $request->string('directory')->toString(),
                    $request->string('filename')->toString(),
                    $request->string('content_type')->toString(),
                    (int) $request->input('file_size'),
                    (int) $request->input('chunk_size'),
                ),
                'Multipart upload initiated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to initiate DAM multipart upload', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to initiate multipart upload', ['error' => $e->getMessage()], 500);
        }
    }

    public function getMultipartUploadUrls(\App\Http\Requests\V1\Dam\GenerateMultipartUploadUrlsRequest $request): JsonResponse
    {


        try {
            return ApiResponse::success(
                $this->service->getMultipartUploadUrls(
                    $request->string('key')->toString(),
                    $request->string('upload_id')->toString(),
                    $request->input('part_numbers', []),
                    (int) $request->input('expires_in', 3600),
                ),
                'Part upload URLs generated successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to generate DAM multipart part URLs', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to generate part upload URLs', ['error' => $e->getMessage()], 500);
        }
    }

    public function completeMultipartUpload(\App\Http\Requests\V1\Dam\CompleteMultipartUploadRequest $request): JsonResponse
    {


        try {
            return ApiResponse::success(
                $this->service->completeMultipartUpload(
                    $request->string('key')->toString(),
                    $request->string('upload_id')->toString(),
                    $request->input('parts', []),
                ),
                'Multipart upload completed successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to complete DAM multipart upload', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to complete multipart upload', ['error' => $e->getMessage()], 500);
        }
    }

    public function abortMultipartUpload(\App\Http\Requests\V1\Dam\AbortMultipartUploadRequest $request): JsonResponse
    {


        try {
            $this->service->abortMultipartUpload(
                $request->string('key')->toString(),
                $request->string('upload_id')->toString(),
            );

            return ApiResponse::success(null, 'Multipart upload aborted successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to abort DAM multipart upload', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to abort multipart upload', ['error' => $e->getMessage()], 500);
        }
    }

    public function verifyUpload(\App\Http\Requests\V1\Dam\VerifyUploadRequest $request): JsonResponse
    {


        try {
            $result = $this->service->verifyUpload($request->string('key')->toString());

            if (! $result['exists']) {
                return ApiResponse::error(
                    'File not found in storage. Upload may be in progress or failed.',
                    ['key' => $result['key']],
                    404
                );
            }

            return ApiResponse::success($result, 'File verified successfully');
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Forbidden', ['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to verify DAM upload', [
                'error' => $e->getMessage(),
                'key' => $request->input('key'),
            ]);

            return ApiResponse::error('Failed to verify file', ['error' => $e->getMessage()], 500);
        }
    }

    public function generateAccessUrl(\App\Http\Requests\V1\Dam\GenerateAccessUrlRequest $request): JsonResponse
    {


        try {
            $result = $this->service->generateAuthenticatedAccessUrl(
                $request->string('key')->toString(),
                (int) $request->input('expires_in', 900),
                $request->input('filename'),
            );

            if ($result === null) {
                return ApiResponse::error('File not found', null, 404);
            }

            return ApiResponse::success($result, 'Access URL generated successfully');
        } catch (AuthorizationException $e) {
            return ApiResponse::error('Forbidden', ['error' => $e->getMessage()], 403);
        } catch (\Throwable $e) {
            Log::error('Failed to generate DAM access URL', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to generate access URL', ['error' => $e->getMessage()], 500);
        }
    }

    public function ingest(\App\Http\Requests\V1\Dam\IngestAssetRequest $request): JsonResponse
    {


        try {
            $data = $request->only([
                'object_key',
                'file_name',
                'mime_type',
                'size',
                'checksum_sha256',
                'collection_name',
                'collection_keys',
                'sort_order',
                'custom_properties',
                'metadata',
                'damable_type',
                'damable_id',
                'bucket',
            ]);

            $data['bucket'] = $request->input('bucket', config('dam.default_bucket'));
            $data['disk'] = config('dam.default_disk');
            $data['uploaded_by'] = $request->user()->id;

            return ApiResponse::success($this->service->ingest($data), 'Asset ingested successfully', 201);
        } catch (AuthorizationException $e) {
            return ApiResponse::error($e->getMessage(), null, 403);
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('Failed to ingest DAM asset', [
                'error' => $e->getMessage(),
                'object_key' => $request->input('object_key'),
            ]);

            return ApiResponse::error('Failed to ingest asset', ['error' => $e->getMessage()], 500);
        }
    }
}
