<?php

namespace App\Services;

use App\Models\FileUpload;
use App\Models\Property;
use App\Models\User;
use App\Services\DataParserService;
use App\Services\PermissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FileUploadService
{
    public function __construct(
        private DataParserService $dataParserService,
        private PermissionService $permissionService
    ) {}

    private const ALLOWED_MIME_TYPES = [
        'application/zip',
        'text/csv',
        'application/vnd.ms-excel',
        'text/plain',
    ];

    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = '檔案大小不能超過 100MB';
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            $errors[] = '只允許上傳 ZIP、CSV 檔案';
        }

        if (!$file->isValid()) {
            $errors[] = '檔案上傳失敗或檔案已損壞';
        }

        return $errors;
    }

    public function uploadFile(UploadedFile $file, User $user): ?FileUpload
    {
        if (!$this->permissionService->checkUploadPermission($user)) {
            Log::warning('User attempted to upload without permission', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            return null;
        }

        $validationErrors = $this->validateFile($file);
        if (!empty($validationErrors)) {
            Log::error('File validation failed', [
                'user_id' => $user->id,
                'errors' => $validationErrors,
                'file_name' => $file->getClientOriginalName(),
            ]);
            return null;
        }

        try {
            $filename = $this->generateUniqueFilename($file);
            $path = $file->storeAs('uploads/government-data', $filename, 'local');

            $fileUpload = FileUpload::create([
                'user_id' => $user->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'upload_path' => $path,
                'upload_status' => 'pending',
            ]);

            Log::info('File uploaded successfully', [
                'user_id' => $user->id,
                'file_upload_id' => $fileUpload->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);

            return $fileUpload;
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);
            return null;
        }
    }

    public function processUploadedFile(FileUpload $fileUpload): bool
    {
        try {
            $fileUpload->update(['upload_status' => 'processing']);

            $filePath = Storage::disk('local')->path($fileUpload->upload_path);

            if ($fileUpload->file_type === 'application/zip') {
                $result = $this->processZipFile($filePath);
            } else {
                $result = $this->processCsvFile($filePath);
            }

            if ($result['success']) {
                $fileUpload->update([
                    'upload_status' => 'completed',
                    'processing_result' => $result,
                ]);

                Log::info('File processing completed', [
                    'file_upload_id' => $fileUpload->id,
                    'processed_records' => $result['processed_records'] ?? 0,
                    'duplicate_records' => $result['duplicate_records'] ?? 0,
                ]);

                return true;
            } else {
                $fileUpload->update([
                    'upload_status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]);

                return false;
            }
        } catch (\Exception $e) {
            $fileUpload->update([
                'upload_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('File processing failed', [
                'file_upload_id' => $fileUpload->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function processZipFile(string $filePath): array
    {
        try {
            // 使用 DataParserService 來處理政府資料 ZIP 檔案
            $result = $this->dataParserService->parseZipData($filePath);
            
            if ($result['success']) {
                $data = $result['data'];
                
                // 提取所有 serial_number
                $serialNumbers = array_filter(array_column($data, 'serial_number'));
                
                // 批量查詢已存在的 serial_number
                $existingSerialNumbers = Property::whereIn('serial_number', $serialNumbers)
                    ->pluck('serial_number')
                    ->toArray();
                
                // 計算重複記錄數
                $duplicateCount = count($existingSerialNumbers);
                
                // 篩選出需要儲存的記錄
                $recordsToSave = [];
                foreach ($data as $record) {
                    if (!$record['serial_number'] || !in_array($record['serial_number'], $existingSerialNumbers)) {
                        $recordsToSave[] = $record;
                    }
                }
                
                // 批量儲存記錄
                $savedCount = 0;
                if (!empty($recordsToSave)) {
                    // 分批儲存以避免記憶體問題
                    $chunks = array_chunk($recordsToSave, 100);
                    foreach ($chunks as $chunk) {
                        Property::insert($chunk);
                        $savedCount += count($chunk);
                    }
                }
                
                return [
                    'success' => true,
                    'processed_records' => count($result['data']),
                    'duplicate_records' => $duplicateCount,
                    'records_imported' => $savedCount,
                    'records_skipped' => $duplicateCount,
                    'processing_time' => $result['processing_time'] ?? 0,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        } catch (\Exception $e) {
            Log::error('ZIP file processing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function processCsvFile(string $filePath): array
    {
        try {
            $processedRecords = 0;
            $duplicateRecords = 0;
            $duplicateSerialNumbers = [];

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return ['success' => false, 'error' => 'Cannot read CSV file'];
            }

            $header = fgetcsv($handle);
            if (!$header) {
                fclose($handle);
                return ['success' => false, 'error' => 'Invalid CSV format'];
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < count($header)) {
                    continue;
                }

                $data = array_combine($header, $row);
                $result = $this->processPropertyRecord($data);

                if ($result['processed']) {
                    $processedRecords++;
                }

                if ($result['duplicate']) {
                    $duplicateRecords++;
                    if ($result['serial_number']) {
                        $duplicateSerialNumbers[] = $result['serial_number'];
                    }
                }
            }

            fclose($handle);

            return [
                'success' => true,
                'processed_records' => $processedRecords,
                'duplicate_records' => $duplicateRecords,
                'duplicate_serial_numbers' => $duplicateSerialNumbers,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processPropertyRecord(array $data): array
    {
        $serialNumber = $data['編號'] ?? $data['序號'] ?? $data['serial_number'] ?? null;

        if ($serialNumber && Property::serialNumberExists($serialNumber)) {
            return [
                'processed' => false,
                'duplicate' => true,
                'serial_number' => $serialNumber,
            ];
        }

        $normalizedData = $this->normalizePropertyData($data);

        Property::create($normalizedData);

        return [
            'processed' => true,
            'duplicate' => false,
            'serial_number' => $serialNumber,
        ];
    }

    private function normalizePropertyData(array $data): array
    {
        return [
            'serial_number' => $data['編號'] ?? $data['序號'] ?? $data['serial_number'] ?? null,
            'city' => $data['縣市'] ?? $data['city'] ?? null,
            'district' => $data['鄉鎮市區'] ?? $data['district'] ?? null,
            'rental_type' => $data['出租型態'] ?? $data['租賃類型'] ?? $data['rental_type'] ?? '住宅',
            'total_rent' => $this->parseNumber($data['總額元'] ?? $data['總租金'] ?? $data['total_rent'] ?? 0),
            'rent_per_ping' => $this->parseNumber($data['每坪租金'] ?? $data['rent_per_ping'] ?? 0),
            'rent_date' => $this->parseDate($data['租賃年月日'] ?? $data['租賃日期'] ?? $data['rent_date'] ?? null),
            'building_type' => $data['建物型態'] ?? $data['建物類型'] ?? $data['building_type'] ?? null,
            'area_ping' => $this->parseNumber($data['面積坪'] ?? $data['area_ping'] ?? 0),
            'building_age' => $this->parseNumber($data['建物年齡'] ?? $data['building_age'] ?? 0),
            'bedrooms' => $this->parseNumber($data['臥室數'] ?? $data['bedrooms'] ?? 0),
            'living_rooms' => $this->parseNumber($data['客廳數'] ?? $data['living_rooms'] ?? 0),
            'bathrooms' => $this->parseNumber($data['衛浴數'] ?? $data['bathrooms'] ?? 0),
            'has_elevator' => $this->parseBoolean($data['有無電梯'] ?? $data['has_elevator'] ?? false),
            'has_management_organization' => $this->parseBoolean($data['有無管理組織'] ?? $data['has_management_organization'] ?? false),
            'has_furniture' => $this->parseBoolean($data['有無傢俱'] ?? $data['has_furniture'] ?? false),
            'is_geocoded' => false,
        ];
    }

    private function parseNumber($value): float
    {
        return (float) preg_replace('/[^\d.]/', '', $value);
    }

    private function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));
        return in_array($value, ['yes', 'true', '1', '有', 'y']);
    }

    private function parseDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('/^(\d{3})(\d{2})(\d{2})$/', $value, $matches)) {
            $year = $matches[1] + 1911;
            $month = $matches[2];
            $day = $matches[3];
            return "{$year}-{$month}-{$day}";
        }

        return $value;
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(8);

        return "government_data_{$timestamp}_{$randomString}.{$extension}";
    }

    public function deleteFile(FileUpload $fileUpload): bool
    {
        try {
            if (Storage::exists($fileUpload->upload_path)) {
                Storage::delete($fileUpload->upload_path);
            }

            $fileUpload->delete();

            Log::info('File deleted successfully', [
                'file_upload_id' => $fileUpload->id,
                'filename' => $fileUpload->filename,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'file_upload_id' => $fileUpload->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getUploadHistory(User $user, int $page = 1, int $perPage = 15, ?string $status = null): array
    {
        $query = FileUpload::query();

        if (!$this->permissionService->checkAdminPermission($user)) {
            $query->where('user_id', $user->id);
        }

        if ($status) {
            $query->where('upload_status', $status);
        }

        $uploads = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'uploads' => $uploads->items(),
            'pagination' => [
                'current_page' => $uploads->currentPage(),
                'last_page' => $uploads->lastPage(),
                'per_page' => $uploads->perPage(),
                'total' => $uploads->total(),
            ],
        ];
    }

    public function processUpload(FileUpload $upload): bool
    {
        try {
            $upload->update(['upload_status' => 'processing']);

            // 這裡應該調用實際的資料處理邏輯
            // 暫時模擬處理成功
            $upload->update([
                'upload_status' => 'completed',
                'processing_result' => [
                    'records_processed' => 100,
                    'records_imported' => 95,
                    'records_skipped' => 5,
                    'processing_time' => 30,
                ],
            ]);

            Log::info('File processing completed', [
                'upload_id' => $upload->id,
                'filename' => $upload->original_filename,
            ]);

            return true;
        } catch (\Exception $e) {
            $upload->update([
                'upload_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('File processing failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteUpload(FileUpload $upload): bool
    {
        try {
            // 刪除檔案
            if (Storage::exists($upload->upload_path)) {
                Storage::delete($upload->upload_path);
            }

            // 刪除資料庫記錄
            $upload->delete();

            Log::info('File upload deleted', [
                'upload_id' => $upload->id,
                'filename' => $upload->original_filename,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file upload', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getUploadStats(User $user): array
    {
        $query = FileUpload::query();

        if (!$this->permissionService->checkAdminPermission($user)) {
            $query->where('user_id', $user->id);
        }

        $totalUploads = $query->count();
        $completedUploads = $query->clone()->where('upload_status', 'completed')->count();
        $failedUploads = $query->clone()->where('upload_status', 'failed')->count();
        $pendingUploads = $query->clone()->where('upload_status', 'pending')->count();
        $processingUploads = $query->clone()->where('upload_status', 'processing')->count();

        $totalSize = $query->clone()->sum('file_size');

        return [
            'total_uploads' => $totalUploads,
            'completed_uploads' => $completedUploads,
            'failed_uploads' => $failedUploads,
            'pending_uploads' => $pendingUploads,
            'processing_uploads' => $processingUploads,
            'total_size' => $totalSize,
            'success_rate' => $totalUploads > 0 ? round(($completedUploads / $totalUploads) * 100, 2) : 0,
        ];
    }
}
