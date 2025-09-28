<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSetting extends Model
{
    protected $fillable = [
        'task_name',
        'frequency',
        'execution_days',
        'execution_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'execution_days' => 'array',
            'execution_time' => 'datetime:H:i',
            'is_active' => 'boolean',
        ];
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ScheduleExecution::class, 'task_name', 'task_name');
    }

    public function isTimeToExecute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();
        $currentDay = $now->day;
        $currentTime = $now->format('H:i');

        return in_array($currentDay, $this->execution_days) &&
               $currentTime >= $this->execution_time->format('H:i');
    }

    public static function checkTimeToExecute(string $taskName): bool
    {
        $setting = static::where('task_name', $taskName)->first();

        return $setting ? $setting->isTimeToExecute() : false;
    }

    public function getNextExecutionDate(): ?Carbon
    {
        if (!$this->is_active) {
            return null;
        }

        $now = Carbon::now();
        $executionTime = Carbon::createFromTimeString($this->execution_time->format('H:i'));

        foreach ($this->execution_days as $day) {
            $nextExecution = $now->copy()->day($day)->setTimeFrom($executionTime);

            if ($nextExecution->isFuture()) {
                return $nextExecution;
            }
        }

        return $now->copy()->addMonth()->day($this->execution_days[0])->setTimeFrom($executionTime);
    }
}