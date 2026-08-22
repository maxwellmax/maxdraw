<?php

namespace App\Providers;

use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRouteBindings();
    }

    /**
     * Resolve os parâmetros de rota que precisam de dono.
     *
     * A sessão de treino é carregada já escopada ao usuário autenticado
     * (US-1.4): a linha de outra pessoa não chega a existir para a requisição,
     * mesmo antes de a `TrainingSessionPolicy` opinar.
     */
    protected function configureRouteBindings(): void
    {
        Route::bind('trainingSession', function (string $value): TrainingSession {
            $user = auth()->user();

            if (! $user instanceof User) {
                abort(401);
            }

            return TrainingSession::ownedBy($user)->findOrFail($value);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
