<?php

use App\Services\RentalDataProcessingService;

describe('RentalDataProcessingService', function () {
    beforeEach(function () {
        $this->service = new RentalDataProcessingService;
    });

    describe('processRentalData', function () {
        it('處理有效的租屋資料', function () {
            $inputData = [
                [
                    'price' => '25000',
                    'address' => '台北市中正區重慶南路一段',
                    'area' => '20.5',
                    'room_count' => '2',
                ],
                [
                    'price' => '18000',
                    'address' => '新北市板橋區文化路',
                    'area' => '15',
                    'room_count' => '1',
                ],
            ];

            $result = $this->service->processRentalData($inputData);

            expect($result)->toHaveCount(2)
                ->and($result[0])->toHaveKey('price_per_ping')
                ->and($result[0])->toHaveKey('room_type')
                ->and($result[0])->toHaveKey('normalized_address')
                ->and($result[0])->toHaveKey('processed_at')
                ->and($result[0]['price'])->toBe(25000.0)
                ->and($result[0]['room_type'])->toBe('2房')
                ->and($result[0]['price_per_ping'])->toBe(1219.51);
        });

        it('過濾無效的資料', function () {
            $inputData = [
                [
                    'price' => '25000',
                    'address' => '台北市中正區重慶南路一段',
                    'area' => '20.5',
                    'room_count' => '2',
                ],
                [
                    'price' => '500', // 價格太低
                    'address' => '新北市板橋區文化路',
                    'area' => '15',
                    'room_count' => '1',
                ],
                [
                    'price' => '18000',
                    // 缺少 address
                    'area' => '15',
                    'room_count' => '1',
                ],
            ];

            $result = $this->service->processRentalData($inputData);

            expect($result)->toHaveCount(1)
                ->and($result[0]['price'])->toBe(25000.0);
        });

        it('清理資料格式', function () {
            $inputData = [
                [
                    'price' => '  NT$ 25,000 元  ',
                    'address' => '  台北市中正區重慶南路一段  ',
                    'area' => '20.5坪',
                    'room_count' => '2房',
                ],
            ];

            $result = $this->service->processRentalData($inputData);

            expect($result[0]['price'])->toBe(25000.0)
                ->and($result[0]['area'])->toBe(20.5)
                ->and($result[0]['room_count'])->toBe(2)
                ->and($result[0]['address'])->toBe('台北市中正區重慶南路一段');
        });

        it('正確分類房型', function () {
            $testCases = [
                ['room_count' => 1, 'expected' => '套房'],
                ['room_count' => 2, 'expected' => '2房'],
                ['room_count' => 3, 'expected' => '3房'],
                ['room_count' => 4, 'expected' => '4房'],
                ['room_count' => 5, 'expected' => '5房以上'],
                ['room_count' => 10, 'expected' => '5房以上'],
            ];

            foreach ($testCases as $testCase) {
                $inputData = [
                    [
                        'price' => '25000',
                        'address' => '台北市中正區重慶南路一段',
                        'area' => '20.5',
                        'room_count' => (string) $testCase['room_count'],
                    ],
                ];

                $result = $this->service->processRentalData($inputData);

                expect($result[0]['room_type'])->toBe($testCase['expected']);
            }
        });

        it('計算每坪價格', function () {
            $inputData = [
                [
                    'price' => '30000',
                    'address' => '台北市中正區重慶南路一段',
                    'area' => '20',
                    'room_count' => '2',
                ],
            ];

            $result = $this->service->processRentalData($inputData);

            expect($result[0]['price_per_ping'])->toBe(1500.0);
        });

        it('拋出不支援格式的例外', function () {
            $inputData = [
                [
                    'price' => '25000',
                    'address' => '台北市中正區重慶南路一段',
                    'area' => '20.5',
                    'room_count' => '2',
                ],
            ];

            expect(fn () => $this->service->processRentalData($inputData, 'invalid_format'))
                ->toThrow(\InvalidArgumentException::class, '不支援的格式: invalid_format');
        });

        it('支援不同的資料格式', function () {
            $inputData = [
                [
                    'price' => '25000',
                    'address' => '台北市中正區重慶南路一段',
                    'area' => '20.5',
                    'room_count' => '2',
                ],
            ];

            foreach (['json', 'csv', 'xml'] as $format) {
                $result = $this->service->processRentalData($inputData, $format);
                expect($result)->toHaveCount(1);
            }
        });
    });

    describe('getProcessingStats', function () {
        it('計算處理統計', function () {
            $originalData = [
                ['price' => '25000', 'address' => '台北市', 'area' => '20', 'room_count' => '2'],
                ['price' => '500', 'address' => '新北市', 'area' => '15', 'room_count' => '1'], // 無效
                ['address' => '桃園市', 'area' => '18', 'room_count' => '2'], // 缺少價格
                ['price' => '18000', 'address' => '台中市', 'area' => '12', 'room_count' => '1'],
            ];

            $processedData = $this->service->processRentalData($originalData);
            $stats = $this->service->getProcessingStats($originalData, $processedData);

            expect($stats['original_count'])->toBe(4)
                ->and($stats['processed_count'])->toBe(2)
                ->and($stats['success_rate'])->toBe(50.0)
                ->and($stats['filtered_count'])->toBe(2);
        });

        it('處理空資料的統計', function () {
            $stats = $this->service->getProcessingStats([], []);

            expect($stats['original_count'])->toBe(0)
                ->and($stats['processed_count'])->toBe(0)
                ->and($stats['success_rate'])->toBe(0)
                ->and($stats['filtered_count'])->toBe(0);
        });
    });

    describe('地址正規化', function () {
        it('正規化台灣地址格式', function () {
            $inputData = [
                [
                    'price' => '25000',
                    'address' => '台北市   中正區   重慶南路一段   121號',
                    'area' => '20.5',
                    'room_count' => '2',
                ],
            ];

            $result = $this->service->processRentalData($inputData);

            expect($result[0]['normalized_address'])->toBe('台北市 中正區 重慶南路一段 121號');
        });
    });
});
