<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataDownloadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $type;
    public array $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $type, array $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->type === 'success') {
            return $this->successMail();
        } else {
            return $this->failureMail();
        }
    }

    /**
     * 成功通知郵件
     */
    private function successMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ 政府資料下載成功 - RentalRadar')
            ->greeting('資料下載完成！')
            ->line('政府租賃實價登錄資料已成功下載並處理。')
            ->line("📁 檔案名稱: {$this->data['filename']}")
            ->line("📊 檔案大小: " . $this->formatBytes($this->data['file_size']))
            ->line("⏱️ 下載時間: {$this->data['download_time']} 秒")
            ->line("🔄 嘗試次數: {$this->data['attempts']} 次")
            ->line("📅 下載時間: {$this->data['downloaded_at']}")
            ->action('查看系統狀態', url('/admin/data-status'))
            ->line('感謝使用 RentalRadar 系統！');
    }

    /**
     * 失敗通知郵件
     */
    private function failureMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('❌ 政府資料下載失敗 - RentalRadar')
            ->greeting('資料下載失敗')
            ->line('政府租賃實價登錄資料下載過程中發生錯誤。')
            ->line("❌ 錯誤訊息: {$this->data['error']}")
            ->line("🔄 嘗試次數: {$this->data['attempts']} 次")
            ->line("📅 失敗時間: {$this->data['failed_at']}")
            ->line('請檢查系統日誌或聯繫技術支援。')
            ->action('查看系統狀態', url('/admin/data-status'))
            ->line('我們將持續嘗試下載資料。');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 格式化位元組大小
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
