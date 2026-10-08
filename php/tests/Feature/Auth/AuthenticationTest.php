<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('admin users are redirected to the dashboard after login', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->post(route('login.store'), [
        'nip' => $user->nip,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('admin account is seeded with the configured nip and password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('nip', '123456789123456789')->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and(Hash::check('admin', $admin->password))->toBeTrue();

    $response = $this->post(route('login.store'), [
        'nip' => '123456789123456789',
        'password' => 'admin',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('regular users are redirected to the chatbot after login', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->post(route('login.store'), [
        'nip' => $user->nip,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('chat.index', absolute: false));
});

test('users with an existing account cannot log in with an incorrect password', function () {
    $user = User::factory()->create([
        'nip' => '123456789012345678',
    ]);

    $this->post(route('login.store'), [
        'nip' => $user->nip,
        'password' => $user->nip,
    ]);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
