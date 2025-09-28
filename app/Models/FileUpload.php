<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileUpload extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'original_filename',
        'file_size',
        'file_type',
        'upload_path',
        'upload_status',
        'processing_result',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'processing_result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->upload_status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->upload_status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->upload_status === 'processing';
    }

    public function isPending(): bool
    {
        return $this->upload_status === 'pending';
    }
}
