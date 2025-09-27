<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemAlertNotification;
use Carbon\Carbon;

class ErrorDetectionSystem
{
    private array $errorThresholds;
    private array $alertChannels;

    public function __construct()
    {
        $this->errorThresholds = [
            'error_rate' => 5, // 5% 錯誤率
            'response_time' => 5000, // 5秒響應時間
            'memory_usage' => 90, // 90% 記憶體使用
            'disk_usage' => 85, // 85% 磁碟使用
            'cpu_usage' => 80, // 80% CPU 使用
            'queue_size' => 1000, // 1000 個佇列任務
            'database_connections' => 50 // 50 個資料庫連線
        ];

        $this->alertChannels = [
            'email' => config('mail.admin_email', 'admin@rentalradar.com'),
            'slack' => config('services.slack.webhook_url'),
            'webhook' => config('services.webhook.url')
        ];
    }

    /**
     * 檢測系統錯誤
     */
    public function detectErrors(): array
    {
        $errors = [];
        $systemHealth = app(SystemHealthMonitor::class)->getSystemHealth();
        
        // 檢測 CPU 使用率
        if ($systemHealth['core_metrics']['cpu_usage'] > $this->errorThresholds['cpu_usage']) {
            $errors[] = $this->createError(
                'cpu_usage',
                'CPU 使用率過高',
                $systemHealth['core_metrics']['cpu_usage'],
                $this->errorThresholds['cpu_usage'],
                'critical'
            );
        }

        // 檢測記憶體使用率
        if ($systemHealth['core_metrics']['memory_usage'] > $this->errorThresholds['memory_usage']) {
            $errors[] = $this->createError(
                'memory_usage',
                '記憶體使用率過高',
                $systemHealth['core_metrics']['memory_usage'],
                $this->errorThresholds['memory_usage'],
                'critical'
            );
        }

        // 檢測磁碟使用率
        if ($systemHealth['core_metrics']['disk_usage'] > $this->errorThresholds['disk_usage']) {
            $errors[] = $this->createError(
                'disk_usage',
                '磁碟使用率過高',
                $systemHealth['core_metrics']['disk_usage'],
                $this->errorThresholds['disk_usage'],
                'critical'
            );
        }

        // 檢測響應時間
        if ($systemHealth['core_metrics']['response_time'] > $this->errorThresholds['response_time']) {
            $errors[] = $this->createError(
                'response_time',
                '響應時間過長',
                $systemHealth['core_metrics']['response_time'],
                $this->errorThresholds['response_time'],
                'warning'
            );
        }

        // 檢測錯誤率
        if ($systemHealth['app_metrics']['error_rate'] > $this->errorThresholds['error_rate']) {
            $errors[] = $this->createError(
                'error_rate',
                '錯誤率過高',
                $systemHealth['app_metrics']['error_rate'],
                $this->errorThresholds['error_rate'],
                'critical'
            );
        }

        // 檢測佇列大小
        if ($systemHealth['core_metrics']['queue_size'] > $this->errorThresholds['queue_size']) {
            $errors[] = $this->createError(
                'queue_size',
                '佇列任務過多',
                $systemHealth['core_metrics']['queue_size'],
                $this->errorThresholds['queue_size'],
                'warning'
            );
        }

        // 檢測資料庫連線數
        if ($systemHealth['core_metrics']['database_connections'] > $this->errorThresholds['database_connections']) {
            $errors[] = $this->createError(
                'database_connections',
                '資料庫連線數過多',
                $systemHealth['core_metrics']['database_connections'],
                $this->errorThresholds['database_connections'],
                'warning'
            );
        }

        return $errors;
    }

    /**
     * 創建錯誤記錄
     */
    private function createError(string $metric, string $message, float $value, float $threshold, string $severity): array
    {
        return [
            'metric' => $metric,
            'message' => $message,
            'value' => $value,
            'threshold' => $threshold,
            'severity' => $severity,
            'timestamp' => now()->toISOString(),
            'id' => uniqid('error_', true)
        ];
    }

    /**
     * 分類錯誤
     */
    public function categorizeErrors(array $errors): array
    {
        $categorized = [
            'critical' => [],
            'warning' => [],
            'info' => []
        ];

        foreach ($errors as $error) {
            $categorized[$error['severity']][] = $error;
        }

        return $categorized;
    }

    /**
     * 發送警報
     */
    public function sendAlerts(array $errors): void
    {
        $categorizedErrors = $this->categorizeErrors($errors);
        
        // 發送嚴重警報
        if (!empty($categorizedErrors['critical'])) {
            $this->sendCriticalAlerts($categorizedErrors['critical']);
        }

        // 發送警告警報
        if (!empty($categorizedErrors['warning'])) {
            $this->sendWarningAlerts($categorizedErrors['warning']);
        }

        // 發送資訊警報
        if (!empty($categorizedErrors['info'])) {
            $this->sendInfoAlerts($categorizedErrors['info']);
        }
    }

    /**
     * 發送嚴重警報
     */
    private function sendCriticalAlerts(array $errors): void
    {
        $message = "🚨 嚴重系統警報\n\n";
        $message .= "時間: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        foreach ($errors as $error) {
            $message .= "• {$error['message']}: {$error['value']} (閾值: {$error['threshold']})\n";
        }

        $this->sendToAllChannels($message, 'critical');
    }

    /**
     * 發送警告警報
     */
    private function sendWarningAlerts(array $errors): void
    {
        $message = "⚠️ 系統警告\n\n";
        $message .= "時間: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        foreach ($errors as $error) {
            $message .= "• {$error['message']}: {$error['value']} (閾值: {$error['threshold']})\n";
        }

        $this->sendToAllChannels($message, 'warning');
    }

    /**
     * 發送資訊警報
     */
    private function sendInfoAlerts(array $errors): void
    {
        $message = "ℹ️ 系統資訊\n\n";
        $message .= "時間: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        foreach ($errors as $error) {
            $message .= "• {$error['message']}: {$error['value']} (閾值: {$error['threshold']})\n";
        }

        $this->sendToAllChannels($message, 'info');
    }

    /**
     * 發送到所有管道
     */
    private function sendToAllChannels(string $message, string $severity): void
    {
        // 發送 Email
        if (!empty($this->alertChannels['email'])) {
            $this->sendEmailAlert($message, $severity);
        }

        // 發送 Slack
        if (!empty($this->alertChannels['slack'])) {
            $this->sendSlackAlert($message, $severity);
        }

        // 發送 Webhook
        if (!empty($this->alertChannels['webhook'])) {
            $this->sendWebhookAlert($message, $severity);
        }

        // 記錄到日誌
        Log::channel('system')->{$severity}($message);
    }

    /**
     * 發送 Email 警報
     */
    private function sendEmailAlert(string $message, string $severity): void
    {
        try {
            Mail::raw($message, function ($mail) use ($severity) {
                $mail->to($this->alertChannels['email'])
                    ->subject("RentalRadar 系統警報 - {$severity}");
            });
        } catch (\Exception $e) {
            Log::error('Failed to send email alert: ' . $e->getMessage());
        }
    }

    /**
     * 發送 Slack 警報
     */
    private function sendSlackAlert(string $message, string $severity): void
    {
        try {
            $payload = [
                'text' => $message,
                'channel' => '#system-alerts',
                'username' => 'RentalRadar Monitor',
                'icon_emoji' => ':warning:'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->alertChannels['slack']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert: ' . $e->getMessage());
        }
    }

    /**
     * 發送 Webhook 警報
     */
    private function sendWebhookAlert(string $message, string $severity): void
    {
        try {
            $payload = [
                'message' => $message,
                'severity' => $severity,
                'timestamp' => now()->toISOString(),
                'source' => 'RentalRadar System Monitor'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->alertChannels['webhook']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Failed to send webhook alert: ' . $e->getMessage());
        }
    }

    /**
     * 警報升級
     */
    public function escalateAlert(array $error): void
    {
        $cacheKey = "alert_escalation_{$error['metric']}";
        $escalationCount = Cache::get($cacheKey, 0);
        
        // 如果警報持續超過 5 分鐘，升級為緊急警報
        if ($escalationCount >= 5) {
            $message = "🚨 緊急系統警報升級\n\n";
            $message .= "警報已持續 " . ($escalationCount * 60) . " 秒\n";
            $message .= "指標: {$error['metric']}\n";
            $message .= "當前值: {$error['value']}\n";
            $message .= "閾值: {$error['threshold']}\n";
            $message .= "時間: " . now()->format('Y-m-d H:i:s');

            $this->sendToAllChannels($message, 'critical');
            
            // 重置升級計數
            Cache::forget($cacheKey);
        } else {
            // 增加升級計數
            Cache::put($cacheKey, $escalationCount + 1, 3600);
        }
    }

    /**
     * 獲取錯誤統計
     */
    public function getErrorStatistics(): array
    {
        $cacheKey = 'error_statistics';
        $statistics = Cache::get($cacheKey, []);

        if (empty($statistics)) {
            $statistics = [
                'total_errors' => 0,
                'critical_errors' => 0,
                'warning_errors' => 0,
                'info_errors' => 0,
                'last_24_hours' => 0,
                'last_7_days' => 0,
                'average_resolution_time' => 0
            ];

            Cache::put($cacheKey, $statistics, 300);
        }

        return $statistics;
    }

    /**
     * 更新錯誤統計
     */
    public function updateErrorStatistics(array $errors): void
    {
        $statistics = $this->getErrorStatistics();
        
        $statistics['total_errors'] += count($errors);
        
        foreach ($errors as $error) {
            $statistics[$error['severity'] . '_errors']++;
        }
        
        $statistics['last_24_hours'] = $this->getErrorsInLast24Hours();
        $statistics['last_7_days'] = $this->getErrorsInLast7Days();
        
        Cache::put('error_statistics', $statistics, 300);
    }

    /**
     * 獲取過去 24 小時的錯誤數
     */
    private function getErrorsInLast24Hours(): int
    {
        $cacheKey = 'errors_24h_' . now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * 獲取過去 7 天的錯誤數
     */
    private function getErrorsInLast7Days(): int
    {
        $cacheKey = 'errors_7d_' . now()->format('Y-m-d');
        return Cache::get($cacheKey, 0);
    }
}
