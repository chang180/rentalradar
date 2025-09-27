<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecommendationEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecommendations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-recommendations 
                            {--user= : Generate for specific user ID}
                            {--limit=10 : Number of recommendations to generate}
                            {--type=all : Type of recommendations (personalized, trending, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate personalized and trending recommendations for users';

    protected RecommendationEngine $recommendationEngine;

    public function __construct(RecommendationEngine $recommendationEngine)
    {
        parent::__construct();
        $this->recommendationEngine = $recommendationEngine;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🎯 Starting recommendation generation...');

            $userId = $this->option('user');
            $limit = (int) $this->option('limit');
            $type = $this->option('type');

            if ($userId) {
                // 為特定用戶生成推薦
                return $this->generateForUser($userId, $limit, $type);
            } else {
                // 為所有用戶生成推薦
                return $this->generateForAllUsers($limit, $type);
            }

        } catch (\Exception $e) {
            $this->error('❌ Recommendation generation failed: '.$e->getMessage());

            Log::error('Recommendation generation failed', [
                'error' => $e->getMessage(),
                'options' => $this->options(),
            ]);

            return Command::FAILURE;
        }
    }

    private function generateForUser(int $userId, int $limit, string $type): int
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                $this->error("❌ User with ID {$userId} not found.");

                return Command::FAILURE;
            }

            $this->info("👤 Generating recommendations for user: {$user->name} (ID: {$userId})");

            $recommendations = [];

            if ($type === 'personalized' || $type === 'all') {
                $this->info('📊 Generating personalized recommendations...');
                $personalized = $this->recommendationEngine->generatePersonalizedRecommendations($user, $limit);
                $recommendations['personalized'] = $personalized;

                $this->info('✅ Generated '.count($personalized).' personalized recommendations');
            }

            if ($type === 'trending' || $type === 'all') {
                $this->info('🔥 Generating trending recommendations...');
                $trending = $this->recommendationEngine->getTrendingRecommendations($limit);
                $recommendations['trending'] = $trending;

                $this->info('✅ Generated '.count($trending).' trending recommendations');
            }

            // 顯示結果摘要
            $this->newLine();
            $this->info('📋 Recommendation Summary:');

            foreach ($recommendations as $typeName => $recs) {
                $this->line("  {$typeName}: ".count($recs).' recommendations');
            }

            Log::info('Recommendations generated for user', [
                'user_id' => $userId,
                'recommendations_count' => array_sum(array_map('count', $recommendations)),
                'types' => array_keys($recommendations),
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to generate recommendations for user {$userId}: ".$e->getMessage());

            Log::error('User recommendation generation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    private function generateForAllUsers(int $limit, string $type): int
    {
        try {
            $this->info('👥 Generating recommendations for all users...');

            $users = User::all();
            $totalUsers = $users->count();

            if ($totalUsers === 0) {
                $this->warn('⚠️  No users found in database.');

                return Command::SUCCESS;
            }

            $progressBar = $this->output->createProgressBar($totalUsers);
            $progressBar->start();

            $successCount = 0;
            $errorCount = 0;
            $totalRecommendations = 0;

            foreach ($users as $user) {
                try {
                    $recommendations = [];

                    if ($type === 'personalized' || $type === 'all') {
                        $personalized = $this->recommendationEngine->generatePersonalizedRecommendations($user, $limit);
                        $recommendations = array_merge($recommendations, $personalized);
                    }

                    if ($type === 'trending' || $type === 'all') {
                        $trending = $this->recommendationEngine->getTrendingRecommendations($limit);
                        $recommendations = array_merge($recommendations, $trending);
                    }

                    $totalRecommendations += count($recommendations);
                    $successCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::warning('Failed to generate recommendations for user', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // 顯示統計結果
            $this->info('📊 Generation Complete!');
            $this->table(['Metric', 'Count'], [
                ['Total Users', $totalUsers],
                ['Successful', $successCount],
                ['Failed', $errorCount],
                ['Total Recommendations', $totalRecommendations],
                ['Average per User', $successCount > 0 ? round($totalRecommendations / $successCount, 2) : 0],
            ]);

            Log::info('Bulk recommendation generation completed', [
                'total_users' => $totalUsers,
                'successful' => $successCount,
                'failed' => $errorCount,
                'total_recommendations' => $totalRecommendations,
            ]);

            return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ Bulk recommendation generation failed: '.$e->getMessage());

            Log::error('Bulk recommendation generation failed', [
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
