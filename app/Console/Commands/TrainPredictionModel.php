<?php

namespace App\Console\Commands;

use App\Services\AIModelTrainingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrainPredictionModel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:train-model 
                            {--force : Force retrain even if model exists}
                            {--validate : Validate model performance after training}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Train AI prediction model for rent price forecasting';

    protected AIModelTrainingService $trainingService;

    public function __construct(AIModelTrainingService $trainingService)
    {
        parent::__construct();
        $this->trainingService = $trainingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🚀 Starting AI model training...');

            $force = $this->option('force');
            $validate = $this->option('validate');

            // 檢查是否已有模型
            if (! $force && $this->trainingService->loadModel()) {
                $this->warn('⚠️  Model already exists. Use --force to retrain.');

                return Command::SUCCESS;
            }

            $this->info('📊 Preparing training data...');
            $progressBar = $this->output->createProgressBar(4);
            $progressBar->start();

            // 開始訓練
            $result = $this->trainingService->trainRentPredictionModel();
            $progressBar->advance();

            if ($result['status'] === 'success') {
                $progressBar->advance();
                $this->newLine();
                $this->info('✅ Model training completed successfully!');

                // 顯示訓練結果
                $this->table(['Metric', 'Value'], [
                    ['Accuracy', number_format($result['accuracy'] * 100, 2).'%'],
                    ['Training Time', $result['training_time']],
                    ['Data Points', $result['data_points']],
                    ['Model Version', $result['model_version']],
                    ['Feature Count', $result['feature_count']],
                ]);

                // 驗證模型
                if ($validate) {
                    $progressBar->advance();
                    $this->info('🔍 Validating model performance...');

                    $modelInfo = $this->trainingService->getModelInfo();
                    $this->table(['Validation Metric', 'Value'], [
                        ['Model Status', $modelInfo['status']],
                        ['Last Trained', $modelInfo['last_trained']],
                        ['Model Size', $modelInfo['model_size'] ?? 'N/A'],
                        ['Performance Score', $modelInfo['performance_score'] ?? 'N/A'],
                    ]);
                }

                $progressBar->finish();
                $this->newLine();

                Log::info('AI model training completed successfully', $result);

                return Command::SUCCESS;

            } else {
                $progressBar->finish();
                $this->newLine();
                $this->error('❌ Model training failed: '.$result['message']);

                Log::error('AI model training failed', $result);

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Training failed with exception: '.$e->getMessage());

            Log::error('AI model training exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
