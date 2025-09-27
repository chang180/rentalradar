<?php

namespace Tests\Feature;

use App\Services\AIMapOptimizationService;
use App\Support\AdvancedPricePredictor;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ModelConsistencyTest extends TestCase
{
    public function test_php_and_js_price_predictions_are_consistent(): void
    {
        $properties = $this->sampleProperties();

        $predictor = new AdvancedPricePredictor();
        $phpPredictions = array_map(static fn (array $payload) => $predictor->predict($payload), $properties);

        $jsOutput = $this->runNodeModelScript('predict', ['points' => $properties]);
        $jsPredictions = $jsOutput['predictions'];

        $this->assertCount(count($phpPredictions), $jsPredictions);

        foreach ($phpPredictions as $index => $phpPrediction) {
            $jsPrediction = $jsPredictions[$index];

            $this->assertEqualsWithDelta($phpPrediction['price'], $jsPrediction['price'], 5.0);
            $this->assertEqualsWithDelta($phpPrediction['confidence'], $jsPrediction['confidence'], 0.05);
            $this->assertEquals($phpPrediction['model_version'], $jsPrediction['modelVersion']);

            $phpRange = $phpPrediction['range'];
            $jsRange = $jsPrediction['range'];
            $this->assertEqualsWithDelta($phpRange['min'], $jsRange['min'], 10.0);
            $this->assertEqualsWithDelta($phpRange['max'], $jsRange['max'], 10.0);
        }
    }

    public function test_php_and_js_clustering_are_consistent(): void
    {
        $points = array_map(static fn (array $payload) => [
            'lat' => $payload['lat'],
            'lng' => $payload['lng'],
        ], $this->sampleProperties(15));

        /** @var AIMapOptimizationService $service */
        $service = app(AIMapOptimizationService::class);
        $phpResult = $service->clusteringAlgorithm($points, 'kmeans', 3);
        $phpClusters = $phpResult['clusters'];

        $jsOutput = $this->runNodeModelScript('cluster', [
            'points' => $points,
            'k' => 3,
        ]);
        $jsClusters = $jsOutput['clusters'];

        $this->assertCount(count($phpClusters), $jsClusters);

        $phpSorted = $this->sortClusters($phpClusters);
        $jsSorted = $this->sortClusters($jsClusters);

        foreach ($phpSorted as $index => $phpCluster) {
            $jsCluster = $jsSorted[$index];

            $this->assertEquals($phpCluster['count'], $jsCluster['count']);
            $this->assertEqualsWithDelta($phpCluster['center']['lat'], $jsCluster['center']['lat'], 0.0005);
            $this->assertEqualsWithDelta($phpCluster['center']['lng'], $jsCluster['center']['lng'], 0.0005);
        }
    }

    private function sampleProperties(int $count = 5): array
    {
        $base = [
            [
                'id' => 1,
                'lat' => 25.0330,
                'lng' => 121.5654,
                'area' => 28,
                'floor' => 8,
                'age' => 4,
                'rent_per_month' => 42000,
                'building_type' => '住宅大樓',
                'pattern' => '2房1廳1衛',
                'district' => '大安區',
            ],
            [
                'id' => 2,
                'lat' => 25.0412,
                'lng' => 121.5501,
                'area' => 18,
                'floor' => 3,
                'age' => 12,
                'rent_per_month' => 28500,
                'building_type' => '公寓',
                'pattern' => '1房1廳1衛',
                'district' => '信義區',
            ],
            [
                'id' => 3,
                'lat' => 25.0555,
                'lng' => 121.5203,
                'area' => 35,
                'floor' => 15,
                'age' => 6,
                'rent_per_month' => 48000,
                'building_type' => '華廈',
                'pattern' => '3房2廳2衛',
                'district' => '中山區',
            ],
            [
                'id' => 4,
                'lat' => 25.0214,
                'lng' => 121.5659,
                'area' => 22,
                'floor' => 5,
                'age' => 9,
                'rent_per_month' => 31000,
                'building_type' => '住宅大樓',
                'pattern' => '2房2廳1衛',
                'district' => '大安區',
            ],
            [
                'id' => 5,
                'lat' => 25.0689,
                'lng' => 121.5899,
                'area' => 42,
                'floor' => 20,
                'age' => 3,
                'rent_per_month' => 62000,
                'building_type' => '住宅大樓',
                'pattern' => '4房2廳2衛',
                'district' => '內湖區',
            ],
        ];

        if ($count <= count($base)) {
            return array_slice($base, 0, $count);
        }

        $extended = $base;
        for ($i = count($base); $i < $count; $i++) {
            $seed = $base[$i % count($base)];
            $extended[] = [
                'id' => $i + 1,
                'lat' => $seed['lat'] + ($i * 0.001),
                'lng' => $seed['lng'] + ($i * 0.001),
                'area' => $seed['area'],
                'floor' => $seed['floor'],
                'age' => $seed['age'],
                'rent_per_month' => $seed['rent_per_month'],
                'building_type' => $seed['building_type'],
                'pattern' => $seed['pattern'],
                'district' => $seed['district'],
            ];
        }

        return $extended;
    }

    private function runNodeModelScript(string $mode, array $payload): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const { pathToFileURL } = require('url');

const mode = process.argv[2];
const dataPath = process.argv[3];
const algorithmPath = process.argv[4];

const data = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

(async () => {
    const moduleUrl = pathToFileURL(algorithmPath);
    const { ClusteringAlgorithm } = await import(moduleUrl.href);
    const algorithm = new ClusteringAlgorithm();

    let output;

    if (mode === 'predict') {
        output = {
            predictions: data.points.map(point => ClusteringAlgorithm.predictPrice(point)),
        };
    } else if (mode === 'cluster') {
        output = {
            clusters: algorithm.kmeansClustering(data.points, data.k || 3, data.options || {}),
        };
    } else {
        throw new Error(`Unknown mode: ${mode}`);
    }

    process.stdout.write(JSON.stringify(output));
})().catch(error => {
    console.error(error);
    process.exit(1);
});
NODE;

        $scriptPath = tempnam(sys_get_temp_dir(), 'model-consistency-script');
        $dataPath = tempnam(sys_get_temp_dir(), 'model-consistency-data');
        file_put_contents($scriptPath, $script);
        file_put_contents($dataPath, json_encode($payload));

        $algorithmPath = realpath(base_path('resources/js/algorithms/ClusteringAlgorithm.js'));

        $command = sprintf('node "%s" %s "%s" "%s"', $scriptPath, escapeshellarg($mode), $dataPath, $algorithmPath);
        $result = Process::run($command);

        @unlink($scriptPath);
        @unlink($dataPath);

        if ($result->failed()) {
            $this->fail('Node script failed: ' . $result->errorOutput());
        }

        return json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function sortClusters(array $clusters): array
    {
        usort($clusters, static function ($a, $b) {
            return [$a['center']['lat'], $a['center']['lng']] <=> [$b['center']['lat'], $b['center']['lng']];
        });

        return $clusters;
    }
}
