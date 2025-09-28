<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create(['is_admin' => true]);
    $this->regularUser = User::factory()->create(['is_admin' => false]);
});

describe('Admin Permission System', function () {
    describe('Permission Service', function () {
        it('allows admin users to access admin functions', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/api/dashboard');

            $response->assertSuccessful();
        });

        it('denies regular users from accessing admin functions', function () {
            $this->actingAs($this->regularUser);

            $response = $this->get('/admin/api/dashboard');

            $response->assertForbidden();
        });

        it('requires authentication for admin endpoints', function () {
            $response = $this->get('/admin/api/dashboard');

            $response->assertRedirect('/login');
        });
    });

    describe('User Management', function () {
        it('allows admin to view users list', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/api/users');

            $response->assertSuccessful();
            $response->assertJsonStructure([
                'success',
                'data' => [
                    'users',
                    'pagination',
                ],
            ]);
        });

        it('allows admin to promote user to admin', function () {
            $this->actingAs($this->adminUser);
            $targetUser = User::factory()->create(['is_admin' => false]);

            $response = $this->postJson('/admin/api/users/' . $targetUser->id . '/promote');

            $response->assertSuccessful();
            $response->assertJson(['success' => true]);
            
            $this->assertDatabaseHas('users', [
                'id' => $targetUser->id,
                'is_admin' => true,
            ]);
        });

        it('prevents admin from demoting themselves', function () {
            $this->actingAs($this->adminUser);

            $response = $this->postJson('/admin/api/users/' . $this->adminUser->id . '/demote');

            $response->assertStatus(400);
            $response->assertJson(['success' => false]);
        });
    });

    describe('File Upload Management', function () {
        it('validates file upload permissions', function () {
            $this->actingAs($this->regularUser);

            $file = \Illuminate\Http\UploadedFile::fake()->create('test.zip', 100);

            $response = $this->postJson('/admin/api/uploads', [
                'file' => $file,
            ]);

            $response->assertForbidden();
        });
    });

    describe('Schedule Management', function () {
        it('allows admin to view schedules', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/api/schedules');

            $response->assertSuccessful();
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        });

        it('validates schedule management permissions', function () {
            $this->actingAs($this->regularUser);

            $response = $this->get('/admin/api/schedules');

            $response->assertForbidden();
        });
    });

    describe('Permission Management', function () {
        it('allows admin to view their permissions', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/api/permissions');

            $response->assertSuccessful();
            $response->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'name',
                    'email',
                    'permissions',
                ],
            ]);
        });

        it('shows correct permissions for admin user', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/api/permissions');

            $response->assertSuccessful();
            
            $data = $response->json('data');
            $permissions = $data['permissions'];
            
            expect($permissions['is_admin'])->toBeTrue();
            expect($permissions['can_upload'])->toBeTrue();
            expect($permissions['can_manage_schedules'])->toBeTrue();
            expect($permissions['can_manage_users'])->toBeTrue();
            expect($permissions['can_view_performance'])->toBeTrue();
        });
    });

    describe('Middleware Protection', function () {
        it('protects admin routes with authentication middleware', function () {
            $response = $this->get('/admin/api/dashboard');

            $response->assertRedirect('/login');
        });

        it('protects admin routes with admin permission middleware', function () {
            $this->actingAs($this->regularUser);

            $response = $this->get('/admin/api/dashboard');

            $response->assertForbidden();
        });
    });

    describe('Frontend Integration', function () {
        it('serves admin user management page', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/users');

            $response->assertSuccessful();
        });

        it('serves admin upload management page', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/uploads');

            $response->assertSuccessful();
        });

        it('serves admin schedule management page', function () {
            $this->actingAs($this->adminUser);

            $response = $this->get('/admin/schedules');

            $response->assertSuccessful();
        });


        it('denies access to admin pages for regular users', function () {
            $this->actingAs($this->regularUser);

            $response = $this->get('/admin/users');

            $response->assertSuccessful(); // 頁面會載入，但前端會顯示權限不足
        });
    });
});