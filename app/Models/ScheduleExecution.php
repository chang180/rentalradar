<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleExecution extends Model
{
    protected $fillable = [
        'task_name',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'result',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'result' => 'array',
        ];
    }

    public function scheduleSetting(): BelongsTo
    {
        return $this->belongsTo(ScheduleSetting::class, 'task_name', 'task_name');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => Carbon::now(),
        ]);
    }

    public function markAsCompleted(array $result = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
            'result' => $result,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => Carbon::now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }

        return null;
    }
}
