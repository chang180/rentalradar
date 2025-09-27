<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;

class PythonIntegrationService
{
    protected string $pythonPath;
    protected string $scriptsPath;

    public function __construct()
    {
        $this->pythonPath = config('python.path', 'python');
        $this->scriptsPath = base_path('.ai-dev/core-tools');
    }

    /**
     * 執行異常值檢測
     */
    public function detectAnomalies(array $data, array $options = []): array
    {
        $inputFile = $this->prepareInputFile($data);
        $outputFile = $this->generateOutputFile();

        $command = sprintf(
            '%s %s/anomaly_detection.py --input %s --output %s --method %s',
            $this->pythonPath,
            $this->scriptsPath,
            $inputFile,
            $outputFile,
            $options['method'] ?? 'zscore'
        );

        return $this->executePythonScript($command, $outputFile);
    }

    /**
     * 執行地圖優化演算法
     */
    public function optimizeMapData(array $data, array $options = []): array
    {
        $inputFile = $this->prepareInputFile($data);
        $outputFile = $this->generateOutputFile();

        $command = sprintf(
            '%s %s/ai-map-optimization.py --input %s --output %s --algorithm %s',
            $this->pythonPath,
            $this->scriptsPath,
            $inputFile,
            $outputFile,
            $options['algorithm'] ?? 'clustering'
        );

        return $this->executePythonScript($command, $outputFile);
    }

    /**
     * 執行價格預測
     */
    public function predictPrices(array $data, array $options = []): array
    {
        $inputFile = $this->prepareInputFile($data);
        $outputFile = $this->generateOutputFile();

        $command = sprintf(
            '%s %s/ai-map-optimization.py --input %s --output %s --task price_prediction',
            $this->pythonPath,
            $this->scriptsPath,
            $inputFile,
            $outputFile
        );

        return $this->executePythonScript($command, $outputFile);
    }

    /**
     * 執行熱力圖分析
     */
    public function generateHeatmap(array $data, array $options = []): array
    {
        $inputFile = $this->prepareInputFile($data);
        $outputFile = $this->generateOutputFile();

        $command = sprintf(
            '%s %s/ai-map-optimization.py --input %s --output %s --task heatmap',
            $this->pythonPath,
            $this->scriptsPath,
            $inputFile,
            $outputFile
        );

        return $this->executePythonScript($command, $outputFile);
    }

    /**
     * 準備輸入檔案
     */
    protected function prepareInputFile(array $data): string
    {
        $inputFile = storage_path('app/temp/input_' . uniqid() . '.json');
        
        // 確保目錄存在
        $directory = dirname($inputFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($inputFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $inputFile;
    }

    /**
     * 生成輸出檔案路徑
     */
    protected function generateOutputFile(): string
    {
        return storage_path('app/temp/output_' . uniqid() . '.json');
    }

    /**
     * 執行 Python 腳本
     */
    protected function executePythonScript(string $command, string $outputFile): array
    {
        try {
            // 記錄執行時間
            $startTime = microtime(true);
            
            // 執行命令
            $result = Process::run($command);
            
            $executionTime = microtime(true) - $startTime;
            
            if (!$result->successful()) {
                Log::error('Python script execution failed', [
                    'command' => $command,
                    'error' => $result->errorOutput(),
                    'exit_code' => $result->exitCode()
                ]);
                
                throw new \Exception('Python script execution failed: ' . $result->errorOutput());
            }

            // 讀取輸出結果
            if (!file_exists($outputFile)) {
                throw new \Exception('Python script did not generate output file');
            }

            $output = json_decode(file_get_contents($outputFile), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON output from Python script');
            }

            // 清理臨時檔案
            $this->cleanupTempFiles($outputFile);

            // 記錄效能指標
            Log::info('Python script executed successfully', [
                'execution_time' => $executionTime,
                'memory_usage' => memory_get_usage(true)
            ]);

            return [
                'success' => true,
                'data' => $output,
                'performance' => [
                    'execution_time' => $executionTime,
                    'memory_usage' => memory_get_usage(true)
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Python integration error', [
                'error' => $e->getMessage(),
                'command' => $command
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 清理臨時檔案
     */
    protected function cleanupTempFiles(string $outputFile): void
    {
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }

    /**
     * 檢查 Python 環境
     */
    public function checkPythonEnvironment(): array
    {
        try {
            $result = Process::run($this->pythonPath . ' --version');
            
            if (!$result->successful()) {
                return [
                    'available' => false,
                    'error' => 'Python not found'
                ];
            }

            // 檢查必要的套件
            $packages = ['numpy', 'pandas', 'scikit-learn'];
            $missingPackages = [];

            foreach ($packages as $package) {
                $checkResult = Process::run($this->pythonPath . ' -c "import ' . $package . '"');
                if (!$checkResult->successful()) {
                    $missingPackages[] = $package;
                }
            }

            return [
                'available' => true,
                'version' => trim($result->output()),
                'missing_packages' => $missingPackages,
                'ready' => empty($missingPackages)
            ];

        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
