<?php

use App\Models\User;

/**
 * O menu de usuário da prancheta: o único caminho para Configurações e Sair
 * dentro do treino (US-1.2). A prancheta roda fora do shell do starter kit,
 * então o gatilho vive na própria barra superior, depois do botão de salvar.
 */
it('serve a prancheta com o menu de usuário para quem entrou', function () {
    seedCatalog();

    $this->actingAs(User::factory()->create())
        ->get(route('board'))
        ->assertOk();
});

it('põe o menu de usuário na barra superior, depois do botão de salvar', function () {
    $topbar = frontendSource('components/prancheta/BoardTopBar.vue');
    $template = substr($topbar, strpos($topbar, '<template>'));

    expect($topbar)
        ->toContain("import BoardUserMenu from '@/components/prancheta/BoardUserMenu.vue';")
        ->and($template)->toContain('<BoardUserMenu data-testid="user-menu" />');

    $positions = array_map(
        fn (string $testId): int => strpos($template, 'data-testid="'.$testId.'"'),
        ['problem-picker', 'sessions-button', 'topbar-spacer', 'save-chip', 'theme-button', 'export-button', 'save-button', 'user-menu'],
    );

    $sorted = $positions;
    sort($sorted);

    expect($positions)->toBe($sorted);
});

it('identifica quem está treinando pelas iniciais e pelo nome compartilhado', function () {
    $menu = frontendSource('components/prancheta/BoardUserMenu.vue');

    expect($menu)
        ->toContain('auth.user')
        ->toContain('getInitials')
        ->toContain('{{ initials }}')
        ->toContain('{{ user.name }}')
        ->toContain('sr-only')
        ->toContain('md:not-sr-only')
        ->not->toContain('avatar');
});

it('abre um menu com Configurações e Sair, nessa ordem e só eles', function () {
    $menu = frontendSource('components/prancheta/BoardUserMenu.vue');

    expect($menu)
        ->toContain('role="menu"')
        ->toContain("import { edit } from '@/routes/profile';")
        ->toContain("import { logout } from '@/routes';")
        ->toContain('data-testid="user-menu-settings"')
        ->toContain(':href="edit()"')
        ->toContain('Configurações')
        ->toContain('data-testid="user-menu-logout"')
        ->toContain(':href="logout()"')
        ->toContain('as="button"')
        ->toContain('router.flushAll()')
        ->toContain('Sair');

    expect(strpos($menu, 'data-testid="user-menu-settings"'))
        ->toBeLessThan(strpos($menu, 'data-testid="user-menu-logout"'));

    expect(preg_match_all('/role="menuitem"/', $menu))->toBe(2);
});

it('deixa o menu operável por teclado', function () {
    $menu = frontendSource('components/prancheta/BoardUserMenu.vue');

    expect($menu)
        ->toContain('aria-haspopup="menu"')
        ->toContain(':aria-expanded="open"')
        ->toMatch('/@keydown\.esc\b|Escape/')
        ->toContain('.focus()');
});

it('deixa o tema do menu para os tokens, sem cor literal nem variante dark', function () {
    expect(frontendSource('components/prancheta/BoardUserMenu.vue'))
        ->toContain('bg-sd-panel')
        ->toContain('border-sd-line-2')
        ->toContain('text-sd-ink-2')
        ->toContain('rounded-sd')
        ->toContain('shadow-sd-2')
        ->not->toContain('dark:')
        ->not->toMatch('/#[0-9a-fA-F]{3,8}\b/');
});

it('encerra a sessão pelo item Sair e devolve o navegador ao login', function () {
    $this->actingAs(User::factory()->create());

    $tokenBefore = session()->token();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();

    expect(session()->token())->not->toBe($tokenBefore);

    $this->get(route('board'))->assertRedirect(route('login'));
});
