---
paths:
  - 'routes/**'
---

# Routes

## Destino pós-autenticação é a prancheta, e nada exige e-mail verificado
Login e registro caem em `/prancheta`, não no dashboard: `fortify.home = '/prancheta'` (config/fortify.php) manda o redirect pós-login/registro e `$middleware->redirectUsersTo('/prancheta')` (bootstrap/app.php) manda quem já entrou e volta às telas de conta. Trocar um sem o outro deixa os dois caminhos divergentes. Sair devolve ao login, não a `/`: `App\Http\Responses\LogoutResponse` está ligado no `FortifyServiceProvider::register()` (US-1.2).

O middleware `verified` foi removido de todas as rotas (US-1.1: a conta nasce utilizável). `tests/Feature/Auth/RegistrationFlowTest.php::no_route_requires_a_verified_email` trava a decisão varrendo o middleware de todas as rotas — reintroduzir `verified` em qualquer grupo deixa a suíte vermelha. A feature `Features::emailVerification()` continua registrada (as rotas de verificação existem e são opcionais), e o `User` não implementa `MustVerifyEmail`.

`prancheta` (rota `board`) está sob `auth`: teste que bate nela precisa de `actingAs`.
