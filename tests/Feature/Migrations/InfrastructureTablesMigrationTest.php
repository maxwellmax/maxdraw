<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

test('laravel_infrastructure_tables_are_published', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'sessions',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
    'password_reset_tokens',
]);

test('the_session_driver_table_and_the_training_table_coexist', function () {
    expect(Schema::hasTable('sessions'))->toBeTrue()
        ->and(Schema::hasTable('training_sessions'))->toBeTrue()
        ->and(Schema::hasColumns('sessions', ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']))->toBeTrue()
        ->and(Schema::hasColumn('sessions', 'nodes'))->toBeFalse()
        ->and(Schema::hasColumn('training_sessions', 'payload'))->toBeFalse();
});

test('password_recovery_is_not_exposed_on_any_route', function () {
    expect(Features::enabled(Features::resetPasswords()))->toBeFalse();

    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => (string) $route->getName())
        ->all();

    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => $route->uri())
        ->all();

    expect($names)->not->toContain('password.request')
        ->not->toContain('password.email')
        ->not->toContain('password.reset')
        ->not->toContain('password.update')
        ->and($uris)->not->toContain('forgot-password');

    expect(collect($uris)->filter(fn (string $uri): bool => str_starts_with($uri, 'reset-password')))->toBeEmpty();
});
