---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Sessão de treino tem duas trancas: binding escopado + policy
O isolamento por usuário (US-1.4) exige policy **e** escopo de query, nunca só um. As duas trancas:
1. `AppServiceProvider::configureRouteBindings()` resolve `{trainingSession}` por `TrainingSession::ownedBy($user)->findOrFail()` — a sessão de outro usuário devolve 404 antes de o controller rodar.
2. `TrainingSessionController` chama `Gate::authorize()` em cada método, contra a `TrainingSessionPolicy` (ligada ao model pelo atributo `#[UsePolicy]`).

Não use `$this->authorizeResource()`: controllers do Laravel 11+ não têm mais `middleware()` e o método explode em runtime (`Call to undefined method ...::middleware()`). Autorize com `Gate::authorize()` no corpo da ação.

`user_id` nunca vem do cliente: as regras da FormRequest não o incluem e o controller preenche o model só com `validated()`. `TrainingSessionResource` também não expõe `user_id`.
