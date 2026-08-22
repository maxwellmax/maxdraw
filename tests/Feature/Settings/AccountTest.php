<?php

use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Perfil e exclusão de conta (US-1.3): nome e e-mail com a validação do
 * registro, troca de senha com a senha atual, e exclusão que leva junto todas
 * as sessões de treino daquele usuário.
 */
test('profile_update_validates_unique_email', function () {
    $user = User::factory()->create(['email' => 'dono@example.com']);
    User::factory()->create(['email' => 'ocupado@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Nome Novo',
            'email' => 'ocupado@example.com',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('email', 'ocupado@example.com');

    expect($user->fresh()->email)->toBe('dono@example.com');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Nome Novo',
            'email' => 'nao-e-um-email',
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nome Novo',
            'email' => 'dono@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Nome Novo');
});

test('password_change_requires_current_password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'senha-errada',
            'password' => 'senha-nova-forte',
            'password_confirmation' => 'senha-nova-forte',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'senha-nova-forte',
            'password_confirmation' => 'outra-coisa',
        ])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'senha-nova-forte',
            'password_confirmation' => 'senha-nova-forte',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('senha-nova-forte', $user->fresh()->password))->toBeTrue();
});

test('account_deletion_requires_current_password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'senha-errada'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('password');

    expect($user->fresh())->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('account_deletion_cascades_training_sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    TrainingSession::factory()->count(3)->create(['user_id' => $user->id]);
    TrainingSession::factory()->count(2)->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(TrainingSession::where('user_id', $user->id)->count())->toBe(0)
        ->and(TrainingSession::where('user_id', $other->id)->count())->toBe(2)
        ->and(User::find($user->id))->toBeNull();

    $this->assertGuest();
});

test('deleted_account_cannot_login', function () {
    $user = User::factory()->create();
    $email = $user->email;

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->post(route('login.store'), [
        'email' => $email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
