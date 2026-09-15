<?php

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('regular users cannot visit the dashboard', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertForbidden();
});

test('admin users can visit the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('document dashboard excludes sensitive user and document fields', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'nip' => '123456789012345678',
        'role' => 'admin',
    ]);
    $category = Category::create([
        'id' => (string) Str::uuid(),
        'name' => 'Peraturan',
        'slug' => 'peraturan',
        'description' => 'Kategori internal',
    ]);
    Document::create([
        'id' => (string) Str::uuid(),
        'user_id' => $admin->id,
        'name' => 'peraturan.pdf',
        'file_path' => 'documents/internal/peraturan.pdf',
        'file_type' => 'pdf',
        'file_size' => 1024,
        'category_id' => $category->id,
        'access_level' => 'internal',
        'status' => 'ready',
        'total_chunks' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('dokumen.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('document/index')
            ->where('auth.user.name', $admin->name)
            ->missing('auth.user.email')
            ->missing('auth.user.nip')
            ->where('documents.data.0.name', 'peraturan.pdf')
            ->missing('documents.data.0.file_path')
            ->missing('documents.data.0.user_id')
            ->missing('categories.0.description'),
        );
});