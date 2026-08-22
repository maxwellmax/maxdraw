<?php

use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/**
 * O registro sob as regras da US-1.1: nome, e-mail e senha confirmada, senha
 * gravada com hash, prancheta como destino e nenhuma verificação de e-mail no
 * caminho.
 */
test('registration_creates_hashed_user_and_authenticates', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Letícia Candidata',
        'email' => 'leticia@example.com',
        'password' => 'senha-de-treino',
        'password_confirmation' => 'senha-de-treino',
    ]);

    $user = User::where('email', 'leticia@example.com')->sole();

    expect($user->name)->toBe('Letícia Candidata')
        ->and($user->password)->not->toBe('senha-de-treino')
        ->and(Hash::check('senha-de-treino', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);

    $response->assertRedirect(route('board', absolute: false));
});

test('registration_rejects_duplicate_email', function () {
    User::factory()->create(['email' => 'ocupado@example.com']);

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Outra Pessoa',
        'email' => 'ocupado@example.com',
        'password' => 'senha-de-treino',
        'password_confirmation' => 'senha-de-treino',
    ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('email', 'ocupado@example.com');

    expect(User::count())->toBe(1)
        ->and(session('_old_input'))->not->toHaveKey('password');

    $this->assertGuest();

    $this->postJson(route('register.store'), [
        'name' => 'Outra Pessoa',
        'email' => 'ocupado@example.com',
        'password' => 'senha-de-treino',
        'password_confirmation' => 'senha-de-treino',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(User::count())->toBe(1);
});

test('registration_rejects_an_invalid_email', function () {
    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Sem E-mail',
        'email' => 'nao-e-um-email',
        'password' => 'senha-de-treino',
        'password_confirmation' => 'senha-de-treino',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

test('registration_requires_password_confirmation', function (array $payload) {
    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Sem Confirmação',
        'email' => 'sem-confirmacao@example.com',
        ...$payload,
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);

    $this->assertGuest();
})->with([
    'sem o campo de confirmação' => [['password' => 'senha-de-treino']],
    'com confirmação diferente' => [[
        'password' => 'senha-de-treino',
        'password_confirmation' => 'outra-senha',
    ]],
]);

test('registration_requires_a_password_of_at_least_eight_characters', function () {
    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Senha Curta',
        'email' => 'curta@example.com',
        'password' => 'curta12',
        'password_confirmation' => 'curta12',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

test('registration_does_not_require_email_verification', function () {
    seedCatalog();

    $this->post(route('register.store'), [
        'name' => 'Direto Na Prancheta',
        'email' => 'direto@example.com',
        'password' => 'senha-de-treino',
        'password_confirmation' => 'senha-de-treino',
    ]);

    expect(User::sole()->email_verified_at)->toBeNull();

    $this->get(route('board'))->assertOk();
});

test('no_route_requires_a_verified_email', function () {
    $middleware = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route): array => $route->gatherMiddleware())
        ->filter(fn ($middleware): bool => is_string($middleware))
        ->unique()
        ->values();

    expect($middleware)->not->toContain('verified')
        ->not->toContain(EnsureEmailIsVerified::class);
});
