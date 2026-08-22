---
paths:
  - 'app/Services/**'
---

# Services

## Sessão corrente é derivada: promover é consequência, não update
A corrente é sempre `MAX(last_opened_at)` do usuário (`CurrentSessionResolver`), então excluir a corrente promove a mais recente restante sem nenhum update de manutenção. `TrainingSessionController::destroy` chama o resolver depois do `delete()` só para o caso de não sobrar nenhuma — aí uma sessão vazia nasce no lugar (US-11.3) — e responde 204; quem precisa da nova corrente recarrega `/prancheta`.

`SessionCreator::DEFAULT_ESTIMATE` é a estimativa com que a sessão nasce; a `TrainingSessionFactory` referencia a mesma constante, não duplique os números.

`POST /api/sessions` não recebe o estado da sessão corrente — o contrato do project-description tem só `problem_id` e `duration_minutes`. Quem garante que nada se perde ao trocar de sessão é o cliente, que dá o `PUT` de autosave antes do `POST`; o servidor só assegura que a sessão anterior fica intacta.
