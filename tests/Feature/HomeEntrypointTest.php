<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/**
 * A porta de entrada da aplicação: `/` serve a tela de login para quem ainda
 * não entrou e desvia para a prancheta quem já está autenticado. O nome de
 * rota `home` continua existindo porque outras telas e testes o resolvem.
 */
it('serve a tela de login em / para quem não está autenticado', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Login')
            ->has('canResetPassword')
            ->has('status'));
});

it('desvia quem já está autenticado de / para a prancheta', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('board', absolute: false));
});

it('mantém o nome de rota home apontando para /', function () {
    $home = Route::getRoutes()->getByName('home');

    expect($home)->not->toBeNull()
        ->and($home->uri())->toBe('/')
        ->and(route('home', absolute: false))->toBe('/');
});
