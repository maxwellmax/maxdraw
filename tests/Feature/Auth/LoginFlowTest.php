<?php

use App\Models\User;

/**
 * Entrar e sair (US-1.2): sessão do Laravel, mensagem que não entrega quais
 * e-mails existem, throttle padrão e a prancheta fechada para visitante.
 */
test('login_with_valid_credentials_authenticates', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);

    $response->assertRedirect(route('board', absolute: false));
});

test('login_error_message_is_generic_for_unknown_email_and_wrong_password', function () {
    $user = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'senha-errada',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->from(route('login'))->post(route('login.store'), [
        'email' => 'ninguem@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    expect(__('auth.failed'))->not->toContain($user->email)
        ->and(__('auth.failed'))->not->toContain('e-mail');

    $this->assertGuest();
});

test('login_is_throttled_after_repeated_failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertTooManyRequests();

    $this->assertGuest();
});

test('remember_me_sets_recaller_cookie', function () {
    $user = User::factory()->create();

    $recaller = auth()->guard()->getRecallerName();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ])->assertCookie($recaller);

    expect($user->fresh()->remember_token)->not->toBeNull();

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertCookieMissing($recaller);
});

test('logout_invalidates_session', function () {
    seedCatalog();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('board'))->assertOk();

    $token = session()->token();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();

    expect(session()->token())->not->toBe($token);

    $this->get(route('board'))->assertRedirect(route('login'));
});

test('guest_is_redirected_from_board_route', function () {
    $this->get(route('board'))->assertRedirect(route('login'));
});
