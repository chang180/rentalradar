<?php

use App\Events\MapDataUpdated;
use App\Events\RealTimeNotification;
use Illuminate\Support\Facades\Event;

it('can broadcast map data updates', function () {
    Event::fake();

    $testData = [
        'rentals' => [
            [
                'id' => 1,
                'title' => 'Test Property',
                'price' => 50000,
                'area' => 100,
                'location' => [
                    'lat' => 25.0330,
                    'lng' => 121.5654,
                    'address' => 'Test Address',
                ],
            ],
        ],
        'statistics' => [
            'count' => 1,
            'districts' => ['信義區' => 1],
        ],
    ];

    broadcast(new MapDataUpdated($testData, 'properties'));

    Event::assertDispatched(MapDataUpdated::class, function ($event) use ($testData) {
        return $event->data === $testData && $event->type === 'properties';
    });
});

it('can broadcast real-time notifications', function () {
    Event::fake();

    $notification = new RealTimeNotification(
        message: 'Test notification',
        type: 'info',
        data: ['test' => 'data'],
        userId: null
    );

    broadcast($notification);

    Event::assertDispatched(RealTimeNotification::class, function ($event) {
        return $event->message === 'Test notification'
            && $event->type === 'info'
            && $event->data === ['test' => 'data'];
    });
});

it('can send notifications via API endpoint', function () {
    $response = $this->postJson('/api/map/notify', [
        'message' => 'API Test notification',
        'type' => 'success',
        'data' => ['api' => 'test'],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Notification sent successfully',
    ]);
});

it('validates notification data properly', function () {
    $response = $this->postJson('/api/map/notify', [
        'message' => '', // Invalid empty message
        'type' => 'invalid_type', // Invalid type
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['message', 'type']);
});

it('can broadcast map data when fetching properties', function () {
    Event::fake();
    
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/map/rentals?limit=10');

    $response->assertStatus(200);

    Event::assertDispatched(MapDataUpdated::class, function ($event) {
        return $event->type === 'properties';
    });
});

it('can broadcast cluster data when generating clusters', function () {
    Event::fake();
    
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/map/clusters?algorithm=kmeans&clusters=5');

    $response->assertStatus(200);

    Event::assertDispatched(MapDataUpdated::class, function ($event) {
        return $event->type === 'clusters';
    });
});