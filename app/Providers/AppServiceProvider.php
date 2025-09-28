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
            // 解碼 URL 中的 HTML 實體，避免在郵件中出現 &amp; 符號
            $decodedUrl = htmlspecialchars_decode($url);

            return new \App\Mail\VerifyEmailMail($notifiable, $decodedUrl);
        });

        // Register event listeners
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PropertyCreated::class,
            \App\Listeners\UpdateStatisticsOnPropertyCreated::class,
        );
    }
}
