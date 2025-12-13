<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Services\FileUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFileUploadJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任務嘗試次數
     */
    public int $tries = 3;

    /**
     * 任務超時時間（秒）
     */
    public int $timeout = 3600; // 1 小時

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $fileUploadId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FileUploadService $fileUploadService): void
    {
        try {
            $fileUpload = FileUpload::find($this->fileUploadId);

            if (! $fileUpload) {
                Log::error('File upload not found for processing', [
                    'file_upload_id' => $this->fileUploadId,
                ]);

                return;
            }

            // 如果已經處理完成或正在處理中，跳過
            if ($fileUpload->upload_status === 'completed') {
                Log::info('File upload already completed', [
                    'file_upload_id' => $this->fileUploadId,
                ]);

                return;
            }

            if ($fileUpload->upload_status === 'processing') {
                Log::info('File upload already processing', [
                    'file_upload_id' => $this->fileUploadId,
                ]);

                return;
            }

            Log::info('Starting file upload processing job', [
                'file_upload_id' => $this->fileUploadId,
                'filename' => $fileUpload->original_filename,
                'file_size' => $fileUpload->file_size,
            ]);

            $result = $fileUploadService->processUploadedFile($fileUpload);

            if ($result) {
                Log::info('File upload processing job completed successfully', [
                    'file_upload_id' => $this->fileUploadId,
                ]);
            } else {
                Log::error('File upload processing job failed', [
                    'file_upload_id' => $this->fileUploadId,
                    'error_message' => $fileUpload->fresh()->error_message,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('File upload processing job exception', [
                'file_upload_id' => $this->fileUploadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 更新檔案狀態為失敗
            $fileUpload = FileUpload::find($this->fileUploadId);
            if ($fileUpload) {
                $fileUpload->update([
                    'upload_status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * 處理失敗的任務
     */
    public function failed(?\Throwable $exception): void
    {
        $fileUpload = FileUpload::find($this->fileUploadId);

        if ($fileUpload) {
            $fileUpload->update([
                'upload_status' => 'failed',
                'error_message' => $exception ? $exception->getMessage() : 'Job failed after maximum retries',
            ]);

            Log::error('File upload processing job failed after retries', [
                'file_upload_id' => $this->fileUploadId,
                'error' => $exception ? $exception->getMessage() : 'Unknown error',
            ]);
        }
    }
}
