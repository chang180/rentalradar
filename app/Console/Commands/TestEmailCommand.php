<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email functionality by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Sending test email to: {$email}");
        
        try {
            // 建立測試使用者
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Test User',
                    'password' => bcrypt('password'),
                ]
            );
            
            // 發送 Email 驗證通知
            $user->sendEmailVerificationNotification();
            
            $this->info('✅ Test email sent successfully!');
            $this->info('📧 Check storage/logs/laravel.log for email content');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send email: ' . $e->getMessage());
        }
    }
}
