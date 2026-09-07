<?php

use App\Jobs\ProcessFileUploadJob;
use App\Models\FileUpload;
use App\Models\Property;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('FileUploadController upload', function () {
    it('dispatches ProcessFileUploadJob after a successful upload', function () {
        Queue::fake();
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);

        $csv = "編號,縣市,鄉鎮市區,出租型態,總額元,每坪租金,租賃年月日,建物型態,面積坪\n"
            ."A001,台北市,大安區,住宅,25000,1200,1130615,住宅大樓,20.5\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('rental.csv', $csv)
            ->mimeType('text/csv');

        $response = $this->actingAs($admin)->postJson('/admin/api/uploads', ['file' => $file]);

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);

        $uploadId = $response->json('data.upload_id');

        Queue::assertPushed(ProcessFileUploadJob::class, fn ($job) => $job->fileUploadId === $uploadId);
    });

    it('rejects upload from a non-admin user', function () {
        $user = User::factory()->create(['is_admin' => false]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('rental.csv', 10, 'text/csv');

        $response = $this->actingAs($user)->postJson('/admin/api/uploads', ['file' => $file]);

        $response->assertForbidden();
    });
});

describe('FileUploadService::processUploadedFile CSV parsing', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->service = app(FileUploadService::class);
        $this->user = User::factory()->create(['is_admin' => true]);
    });

    it('skips duplicate and missing-serial rows while importing valid rows', function () {
        Queue::fake();

        Property::factory()->create(['serial_number' => 'DUP001']);

        $csv = "\xEF\xBB\xBF編號,縣市,鄉鎮市區,出租型態,總額元,每坪租金,租賃年月日,建物型態,面積坪\n"
            ."DUP001,台北市,大安區,住宅,25000,1200,1130615,住宅大樓,20.5\n"
            .",台北市,大安區,住宅,25000,1200,1130615,住宅大樓,20.5\n"
            ."NEW001,新北市,板橋區,住宅,18000,900,1130520,公寓,15\n";

        $path = 'uploads/government-data/test.csv';
        Storage::disk('local')->put($path, $csv);

        $fileUpload = FileUpload::create([
            'user_id' => $this->user->id,
            'filename' => 'test.csv',
            'original_filename' => 'rental.csv',
            'file_size' => strlen($csv),
            'file_type' => 'text/csv',
            'upload_path' => $path,
            'upload_status' => 'pending',
        ]);

        $result = $this->service->processUploadedFile($fileUpload);

        expect($result)->toBeTrue();

        $fileUpload->refresh();
        expect($fileUpload->upload_status)->toBe('completed');
        expect($fileUpload->processing_result['processed_records'])->toBe(1);
        expect($fileUpload->processing_result['duplicate_records'])->toBe(1);
        expect($fileUpload->processing_result['skipped_records'])->toBe(1);

        expect(Property::where('serial_number', 'NEW001')->exists())->toBeTrue();
        expect(Property::count())->toBe(2); // pre-existing DUP001 + newly created NEW001
    });
});
