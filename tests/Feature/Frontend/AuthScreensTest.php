<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * As telas de registro e login vestidas com a Phase 6: tokens `sd-`, tipografia
 * da prancheta, o botão e o campo compartilhados, erro junto do campo e
 * navegação recíproca entre as duas.
 */
it('serve as telas de conta pelo Inertia', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
})->with([
    'registro' => ['register', 'auth/Register'],
    'login' => ['login', 'auth/Login'],
]);

it('leva quem já entrou direto para a prancheta', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertRedirect(route('board', absolute: false));
})->with(['register', 'login']);

it('monta os campos das duas telas com o campo da prancheta', function (string $page) {
    expect(frontendSource($page))
        ->toContain("import PranchetaInput from '@/components/prancheta/PranchetaInput.vue';")
        ->toContain('<PranchetaInput');
})->with(['pages/auth/Login.vue', 'pages/auth/Register.vue']);

it('envia as duas telas pelo botão primário da prancheta', function (string $page) {
    expect(frontendSource($page))
        ->toContain("import PranchetaButton from '@/components/prancheta/PranchetaButton.vue';")
        ->toContain('variant="primary"')
        ->toContain('type="submit"');
})->with(['pages/auth/Login.vue', 'pages/auth/Register.vue']);

it('mostra o erro de validação junto do campo que o gerou', function (string $page, string $field) {
    expect(frontendSource($page))
        ->toContain('<InputError :message="errors.'.$field.'" />');
})->with([
    ['pages/auth/Login.vue', 'email'],
    ['pages/auth/Login.vue', 'password'],
    ['pages/auth/Register.vue', 'name'],
    ['pages/auth/Register.vue', 'email'],
    ['pages/auth/Register.vue', 'password'],
    ['pages/auth/Register.vue', 'password_confirmation'],
]);

it('pinta o erro com o token de estado crítico', function () {
    expect(frontendSource('components/InputError.vue'))->toContain('text-sd-crit');
});

it('veste o campo com a borda, o fundo e o anel de foco do protótipo', function () {
    expect(frontendSource('components/prancheta/PranchetaInput.vue'))
        ->toContain('border-sd-line-2')
        ->toContain('bg-sd-panel-2')
        ->toContain('text-sd-ink')
        ->toContain('font-sd-ui')
        ->toContain('focus:border-sd-accent')
        ->toContain('focus:shadow-[0_0_0_3px_var(--accent-soft)]');
});

it('assenta as telas de conta sobre o papel e a tipografia da prancheta', function () {
    expect(frontendSource('layouts/auth/AuthSimpleLayout.vue'))
        ->toContain('bg-sd-paper')
        ->toContain('font-sd-ui')
        ->toContain('text-sd-ink')
        ->toContain('bg-sd-panel')
        ->toContain('border-sd-line')
        ->toContain('shadow-sd-2');
});

it('deixa o tema para os tokens, sem cor literal nem variante dark', function (string $file) {
    expect(frontendSource($file))
        ->not->toContain('dark:')
        ->not->toMatch('/#[0-9a-fA-F]{3,8}\b/');
})->with([
    'pages/auth/Login.vue',
    'pages/auth/Register.vue',
    'layouts/auth/AuthSimpleLayout.vue',
    'components/prancheta/PranchetaInput.vue',
    'components/InputError.vue',
]);

it('mantém a ida e a volta entre registro e login', function () {
    expect(frontendSource('pages/auth/Login.vue'))
        ->toContain("import { register } from '@/routes';")
        ->toContain(':href="register()"');

    expect(frontendSource('pages/auth/Register.vue'))
        ->toContain("import { login } from '@/routes';")
        ->toContain(':href="login()"');
});
