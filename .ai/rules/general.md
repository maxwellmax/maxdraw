---
paths:
  - package.json
---

# General

## Inertia 3 é a versão do projeto — nunca fazer downgrade para 2
A `.spec` original (project-description, project-phases Phase 1.1) pedia "Inertia 2" porque foi escrita antes do starter kit oficial do Laravel 13 subir para Inertia 3. O projeto roda `@inertiajs/vue3` ^3.0 + `inertiajs/inertia-laravel` ^3.0, e o layout raiz (`resources/views/app.blade.php`) usa a sintaxe de componentes v3 (`<x-inertia::app />`, `<x-inertia::head>`) junto com o plugin `@inertiajs/vite` e o input por página no `@vite`.

Downgrade para Inertia 2 quebra o bootstrap inteiro do starter kit (blade, `resources/js/app.ts` sem `resolve`/`setup`, `vite.config.ts`). Decisão fechada: manter o 3.x. Os documentos da `.spec` já foram corrigidos e `tests/Feature/StackContractTest.php` trava as versões.
