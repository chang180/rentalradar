<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 自定義電子郵件驗證通知內容
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('驗證您的電子郵件地址 - RentalRadar')
                ->greeting('您好！')
                ->line('感謝您註冊 RentalRadar！請點擊下方按鈕來驗證您的電子郵件地址。')
                ->action('驗證電子郵件地址', $url)
                ->line('如果您沒有創建帳戶，請忽略此郵件。')
                ->salutation('祝好，' . PHP_EOL . 'RentalRadar 團隊');
        });
    }
}
