<?php

namespace App\Services;

use App\Models\FileUpload;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            $errors[] = '只允許上傳 ZIP、CSV 檔案';
        }

        if (! $file->isValid()) {
            $errors[] = '檔案上傳失敗或檔案已損壞';
        }

        return $errors;
    }

    public function uploadFile(UploadedFile $file, User $user): ?FileUpload
    {
        if (! $this->permissionService->checkUploadPermission($user)) {
            Log::warning('User attempted to upload without permission', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return null;
        }

        $validationErrors = $this->validateFile($file);
        if (! empty($validationErrors)) {
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
            // 設定執行時間和記憶體限制
            ini_set('max_execution_time', 0); // 無限制執行時間
            ini_set('memory_limit', '2G'); // 增加記憶體限制

            $fileUpload->update(['upload_status' => 'processing']);

            if ($fileUpload->file_type === 'application/zip') {
                $result = $this->processZipFile($fileUpload);
            } else {
                $filePath = Storage::disk('local')->path($fileUpload->upload_path);
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

                // 處理完成後清理上傳檔案
                $this->cleanupUploadFile($fileUpload);

                // 生成統計資料
                try {
                    \Illuminate\Support\Facades\Artisan::call('statistics:populate', [
                        '--chunk' => 1000,
                    ]);
                    Log::info('Statistics generated after file upload', [
                        'file_upload_id' => $fileUpload->id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to generate statistics after file upload', [
                        'file_upload_id' => $fileUpload->id,
                        'error' => $e->getMessage(),
                    ]);
                }

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

    private function processZipFile(FileUpload $fileUpload): array
    {
        try {
            // 設定執行時間和記憶體限制
            ini_set('max_execution_time', 0); // 無限制執行時間
            ini_set('memory_limit', '2G'); // 增加記憶體限制

            // 直接使用 FileUpload 物件中的相對路徑，這樣更可靠
            $storagePath = $fileUpload->upload_path;
            $filePath = $storagePath; // 為了在 catch 區塊中使用

            // 使用 DataParserService 來處理政府資料 ZIP 檔案
            $result = $this->dataParserService->parseZipData($storagePath);

            if ($result['success']) {
                $data = $result['data'];

                // 提取所有 serial_number
                $serialNumbers = array_filter(array_column($data, 'serial_number'));

                // 分批查詢已存在的 serial_number（避免 SQLite 變數限制）
                $existingSerialNumbers = [];
                $chunkSize = 500; // SQLite 安全限制
                $serialNumberChunks = array_chunk($serialNumbers, $chunkSize);

                foreach ($serialNumberChunks as $chunk) {
                    $existing = Property::whereIn('serial_number', $chunk)
                        ->pluck('serial_number')
                        ->toArray();
                    $existingSerialNumbers = array_merge($existingSerialNumbers, $existing);
                }

                // 計算重複記錄數
                $duplicateCount = count($existingSerialNumbers);

                // 篩選出需要儲存的記錄（過濾掉重複的 serial_number）
                $recordsToSave = [];
                $seenSerialNumbers = [];

                foreach ($data as $record) {
                    $serialNumber = $record['serial_number'] ?? null;

                    // 跳過沒有 serial_number 的記錄或已存在於資料庫的記錄
                    if (! $serialNumber || in_array($serialNumber, $existingSerialNumbers)) {
                        continue;
                    }

                    // 跳過在本批次中重複的 serial_number
                    if (in_array($serialNumber, $seenSerialNumbers)) {
                        continue;
                    }

                    $recordsToSave[] = $record;
                    $seenSerialNumbers[] = $serialNumber;
                }

                // 批量儲存記錄
                $savedCount = 0;
                if (! empty($recordsToSave)) {
                    // 分批儲存以避免記憶體問題
                    $chunks = array_chunk($recordsToSave, 100);
                    foreach ($chunks as $chunk) {
                        // 為每筆記錄添加時間戳
                        $now = now();
                        foreach ($chunk as &$record) {
                            $record['created_at'] = $now;
                            $record['updated_at'] = $now;
                        }

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
            $skippedRecords = 0;
            $duplicateSerialNumbers = [];
            $errors = [];

            $handle = fopen($filePath, 'r');
            if (! $handle) {
                return ['success' => false, 'error' => 'Cannot read CSV file'];
            }

            // 讀取並清理標題行（移除 BOM）
            $header = fgetcsv($handle);
            if (! $header) {
                fclose($handle);

                return ['success' => false, 'error' => 'Invalid CSV format: Cannot read header'];
            }

            // 清理標題行的 BOM 和空白字符
            $header = array_map(function ($col) {
                return trim($col, "\xEF\xBB\xBF \t\n\r\0\x0B");
            }, $header);

            Log::info('Processing CSV file', [
                'file_path' => $filePath,
                'header_count' => count($header),
                'header' => $header,
            ]);

            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // 跳過空行
                if (empty($row) || (count($row) === 1 && trim($row[0]) === '')) {
                    continue;
                }

                // 如果欄位數量不匹配，嘗試調整
                if (count($row) !== count($header)) {
                    Log::warning('CSV row column count mismatch', [
                        'line' => $lineNumber,
                        'expected' => count($header),
                        'actual' => count($row),
                    ]);

                    // 如果欄位太少，跳過
                    if (count($row) < count($header)) {
                        $skippedRecords++;
                        continue;
                    }

                    // 如果欄位太多，只取前 N 個
                    $row = array_slice($row, 0, count($header));
                }

                try {
                    $data = array_combine($header, $row);
                    if ($data === false) {
                        Log::warning('Failed to combine CSV row with header', [
                            'line' => $lineNumber,
                            'header_count' => count($header),
                            'row_count' => count($row),
                        ]);
                        $skippedRecords++;
                        continue;
                    }

                    $result = $this->processPropertyRecord($data);

                    if ($result['processed']) {
                        $processedRecords++;
                    } elseif ($result['duplicate']) {
                        $duplicateRecords++;
                        if ($result['serial_number']) {
                            $duplicateSerialNumbers[] = $result['serial_number'];
                        }
                    } else {
                        $skippedRecords++;
                        if (isset($result['error'])) {
                            $errors[] = "Line {$lineNumber}: {$result['error']}";
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing CSV row', [
                        'line' => $lineNumber,
                        'error' => $e->getMessage(),
                    ]);
                    $skippedRecords++;
                    $errors[] = "Line {$lineNumber}: {$e->getMessage()}";
                }
            }

            fclose($handle);

            Log::info('CSV processing completed', [
                'file_path' => $filePath,
                'processed' => $processedRecords,
                'duplicates' => $duplicateRecords,
                'skipped' => $skippedRecords,
            ]);

            return [
                'success' => true,
                'processed_records' => $processedRecords,
                'duplicate_records' => $duplicateRecords,
                'skipped_records' => $skippedRecords,
                'duplicate_serial_numbers' => $duplicateSerialNumbers,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            Log::error('CSV file processing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processPropertyRecord(array $data): array
    {
        // 先獲取 serial_number 並清理
        $serialNumber = $data['編號'] ?? $data['序號'] ?? $data['serial_number'] ?? null;
        $serialNumber = $serialNumber ? trim((string) $serialNumber) : null;
        
        // 如果 serial_number 為空或 null，直接跳過
        if (empty($serialNumber)) {
            Log::warning('Skipping property record due to missing serial_number', [
                'data_keys' => array_keys($data),
            ]);

            return [
                'processed' => false,
                'duplicate' => false,
                'serial_number' => null,
                'error' => 'Missing required field: serial_number',
            ];
        }

        // 檢查是否已存在
        if (Property::serialNumberExists($serialNumber)) {
            return [
                'processed' => false,
                'duplicate' => true,
                'serial_number' => $serialNumber,
            ];
        }

        $normalizedData = $this->normalizePropertyData($data);

        // 再次確認 serial_number 不為空（防止 normalizePropertyData 返回空值）
        if (empty($normalizedData['serial_number'])) {
            Log::warning('Skipping property record due to empty serial_number after normalization', [
                'original_serial_number' => $serialNumber,
                'data_keys' => array_keys($data),
            ]);

            return [
                'processed' => false,
                'duplicate' => false,
                'serial_number' => null,
                'error' => 'Serial number is empty after normalization',
            ];
        }

        // 驗證必填欄位
        $requiredFields = ['city', 'district', 'serial_number', 'rental_type', 'total_rent', 'rent_per_ping', 'rent_date', 'building_type', 'area_ping'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            $value = $normalizedData[$field] ?? null;
            // 檢查是否為 null、空字串或只包含空白字符
            if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            Log::warning('Skipping property record due to missing required fields', [
                'missing_fields' => $missingFields,
                'serial_number' => $normalizedData['serial_number'] ?? null,
                'data_keys' => array_keys($data),
            ]);

            return [
                'processed' => false,
                'duplicate' => false,
                'serial_number' => $normalizedData['serial_number'] ?? null,
                'error' => 'Missing required fields: '.implode(', ', $missingFields),
            ];
        }

        try {
            Property::create($normalizedData);

            return [
                'processed' => true,
                'duplicate' => false,
                'serial_number' => $normalizedData['serial_number'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create property record', [
                'error' => $e->getMessage(),
                'serial_number' => $normalizedData['serial_number'] ?? null,
                'data' => $normalizedData,
            ]);

            return [
                'processed' => false,
                'duplicate' => false,
                'serial_number' => $normalizedData['serial_number'] ?? null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function normalizePropertyData(array $data): array
    {
        // 清理數據鍵名（移除 BOM 和空白字符）
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedKey = trim($key, "\xEF\xBB\xBF \t\n\r\0\x0B");
            $cleanedData[$cleanedKey] = $value;
        }
        $data = $cleanedData;

        // 嘗試多種可能的欄位名稱
        $serialNumber = $data['編號'] ?? $data['序號'] ?? $data['serial_number'] ?? $data['編號 '] ?? null;
        // 清理 serial_number：去除空白，如果為空則設為 null
        $serialNumber = $serialNumber ? trim((string) $serialNumber) : null;
        $serialNumber = $serialNumber === '' ? null : $serialNumber;
        
        $city = $data['縣市'] ?? $data['city'] ?? $data['縣市 '] ?? null;
        $city = $city ? trim((string) $city) : null;
        $city = $city === '' ? null : $city;
        
        $district = $data['鄉鎮市區'] ?? $data['district'] ?? $data['鄉鎮市區 '] ?? null;
        $district = $district ? trim((string) $district) : null;
        $district = $district === '' ? null : $district;
        
        $rentalType = $data['出租型態'] ?? $data['租賃類型'] ?? $data['rental_type'] ?? $data['出租型態 '] ?? '住宅';
        $totalRent = $this->parseNumber($data['總額元'] ?? $data['總租金'] ?? $data['total_rent'] ?? $data['總額元 '] ?? 0);
        $rentPerPing = $this->parseNumber($data['每坪租金'] ?? $data['rent_per_ping'] ?? $data['每坪租金 '] ?? 0);
        $rentDate = $this->parseDate($data['租賃年月日'] ?? $data['租賃日期'] ?? $data['rent_date'] ?? $data['租賃年月日 '] ?? null);
        $buildingType = $data['建物型態'] ?? $data['建物類型'] ?? $data['building_type'] ?? $data['建物型態 '] ?? null;
        $buildingType = $buildingType ? trim((string) $buildingType) : null;
        $buildingType = $buildingType === '' ? null : $buildingType;
        
        $areaPing = $this->parseNumber($data['面積坪'] ?? $data['area_ping'] ?? $data['面積坪 '] ?? 0);

        return [
            'serial_number' => $serialNumber,
            'city' => $city,
            'district' => $district,
            'rental_type' => $rentalType ? (string) $rentalType : '住宅',
            'total_rent' => $totalRent,
            'rent_per_ping' => $rentPerPing,
            'rent_date' => $rentDate,
            'building_type' => $buildingType,
            'area_ping' => $areaPing,
            'building_age' => $this->parseNumber($data['建物年齡'] ?? $data['building_age'] ?? $data['建物年齡 '] ?? 0),
            'bedrooms' => (int) $this->parseNumber($data['臥室數'] ?? $data['bedrooms'] ?? $data['臥室數 '] ?? 0),
            'living_rooms' => (int) $this->parseNumber($data['客廳數'] ?? $data['living_rooms'] ?? $data['客廳數 '] ?? 0),
            'bathrooms' => (int) $this->parseNumber($data['衛浴數'] ?? $data['bathrooms'] ?? $data['衛浴數 '] ?? 0),
            'has_elevator' => $this->parseBoolean($data['有無電梯'] ?? $data['has_elevator'] ?? $data['有無電梯 '] ?? false),
            'has_management_organization' => $this->parseBoolean($data['有無管理組織'] ?? $data['has_management_organization'] ?? $data['有無管理組織 '] ?? false),
            'has_furniture' => $this->parseBoolean($data['有無傢俱'] ?? $data['has_furniture'] ?? $data['有無傢俱 '] ?? false),
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
        if (! $value) {
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

        if (! $this->permissionService->checkAdminPermission($user)) {
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
        return $this->processUploadedFile($upload);
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

        if (! $this->permissionService->checkAdminPermission($user)) {
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

    /**
     * 清理上傳檔案
     */
    private function cleanupUploadFile(FileUpload $fileUpload): void
    {
        try {
            if (Storage::exists($fileUpload->upload_path)) {
                Storage::delete($fileUpload->upload_path);
                Log::info('Upload file cleaned up', [
                    'upload_id' => $fileUpload->id,
                    'filename' => $fileUpload->filename,
                    'upload_path' => $fileUpload->upload_path,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup upload file', [
                'upload_id' => $fileUpload->id,
                'upload_path' => $fileUpload->upload_path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
