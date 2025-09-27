<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $severity;
    private string $message;
    private array $metrics;
    private array $alerts;

    public function __construct(string $severity, string $message, array $metrics = [], array $alerts = [])
    {
        $this->severity = $severity;
        $this->message = $message;
        $this->metrics = $metrics;
        $this->alerts = $alerts;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'slack'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject("RentalRadar 系統警報 - {$this->getSeverityText()}")
            ->greeting('系統警報通知')
            ->line($this->message)
            ->line('時間: ' . now()->format('Y-m-d H:i:s'));

        // 添加指標資訊
        if (!empty($this->metrics)) {
            $mailMessage->line('系統指標:');
            foreach ($this->metrics as $metric => $value) {
                $mailMessage->line("  - {$metric}: {$value}");
            }
        }

        // 添加警報詳情
        if (!empty($this->alerts)) {
            $mailMessage->line('警報詳情:');
            foreach ($this->alerts as $alert) {
                $mailMessage->line("  - {$alert['message']} ({$alert['value']} / {$alert['threshold']})");
            }
        }

        $mailMessage->line('請及時檢查系統狀態並採取相應措施。');

        return $mailMessage;
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $icon = $this->getSeverityIcon();
        $color = $this->getSeverityColor();
        
        $slackMessage = SlackMessage::create()
            ->to('#system-alerts')
            ->content("{$icon} RentalRadar 系統警報")
            ->attachment(function ($attachment) use ($color) {
                $attachment->title('系統警報詳情')
                    ->content($this->message)
                    ->color($color)
                    ->fields([
                        '嚴重程度' => $this->getSeverityText(),
                        '時間' => now()->format('Y-m-d H:i:s'),
                        '系統' => 'RentalRadar',
                    ]);
            });

        // 添加指標資訊
        if (!empty($this->metrics)) {
            $slackMessage->attachment(function ($attachment) {
                $attachment->title('系統指標')
                    ->color('warning')
                    ->fields(array_map(function ($value, $metric) {
                        return [
                            'title' => $metric,
                            'value' => $value,
                            'short' => true
                        ];
                    }, $this->metrics, array_keys($this->metrics)));
            });
        }

        // 添加警報詳情
        if (!empty($this->alerts)) {
            $slackMessage->attachment(function ($attachment) {
                $attachment->title('警報詳情')
                    ->color('danger')
                    ->fields(array_map(function ($alert) {
                        return [
                            'title' => $alert['message'],
                            'value' => "當前值: {$alert['value']} | 閾值: {$alert['threshold']}",
                            'short' => false
                        ];
                    }, $this->alerts));
            });
        }

        return $slackMessage;
    }

    /**
     * Get the severity text.
     */
    private function getSeverityText(): string
    {
        return match ($this->severity) {
            'critical' => '嚴重',
            'warning' => '警告',
            'info' => '資訊',
            default => '未知'
        };
    }

    /**
     * Get the severity icon.
     */
    private function getSeverityIcon(): string
    {
        return match ($this->severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📢'
        };
    }

    /**
     * Get the severity color.
     */
    private function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            default => '#808080'
        };
    }
}
