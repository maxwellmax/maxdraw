<?php

namespace App\Services;

use App\Http\Resources\ComponentCategoryResource;
use App\Http\Resources\EstimateModeResource;
use App\Http\Resources\LinkTypeResource;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\ProblemResource;
use App\Http\Resources\SequenceModeResource;
use App\Http\Resources\SessionDurationResource;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\Phase;
use App\Models\Problem;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * O catálogo como a prancheta o consome: uma única leitura, servida junto da
 * sessão corrente. Só linhas ativas chegam ao cliente — com uma exceção
 * deliberada, o que a sessão carregada já referencia, para que um item
 * aposentado continue renderizando o desenho antigo em vez de sumir dele.
 */
class CatalogService
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function payload(TrainingSession $session): array
    {
        $components = $this->components($session);

        return [
            'problems' => ProblemResource::collection($this->problems($session))->resolve(),
            'component_categories' => ComponentCategoryResource::collection($this->categories($components))->resolve(),
            'link_types' => LinkTypeResource::collection($this->linkTypes($session))->resolve(),
            'phases' => PhaseResource::collection($this->phases())->resolve(),
            'sequence_modes' => SequenceModeResource::collection(SequenceMode::query()->active()->get())->resolve(),
            'session_durations' => SessionDurationResource::collection(SessionDuration::query()->active()->get())->resolve(),
            'estimate_modes' => EstimateModeResource::collection(EstimateMode::query()->active()->get())->resolve(),
        ];
    }

    /**
     * @return Collection<int, Problem>
     */
    private function problems(TrainingSession $session): Collection
    {
        return Problem::query()
            ->with(['problemLevel', 'problemItems.problemItemType'])
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when(! is_null($session->problem_id), fn (Builder $query) => $query->orWhere('id', $session->problem_id)))
            ->get();
    }

    /**
     * @return Collection<int, Component>
     */
    private function components(TrainingSession $session): Collection
    {
        $drawn = collect($session->nodes)->pluck('type')->filter()->unique()->all();

        return Component::query()
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->orWhereIn('slug', $drawn))
            ->get();
    }

    /**
     * A paleta é lida categoria a categoria, e a categoria é a única fonte de
     * cor — por isso os componentes chegam aninhados nela, nunca soltos.
     *
     * @param  Collection<int, Component>  $components
     * @return Collection<int, ComponentCategory>
     */
    private function categories(Collection $components): Collection
    {
        return ComponentCategory::query()
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $components->pluck('component_category_id')->unique()))
            ->get()
            ->each(fn (ComponentCategory $category) => $category->setRelation(
                'components',
                $components->where('component_category_id', $category->id)->values(),
            ));
    }

    /**
     * @return Collection<int, LinkType>
     */
    private function linkTypes(TrainingSession $session): Collection
    {
        $drawn = collect($session->edges)->pluck('kind')->filter()->unique()->all();

        return LinkType::query()
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->orWhereIn('slug', $drawn))
            ->get();
    }

    /**
     * @return Collection<int, Phase>
     */
    private function phases(): Collection
    {
        return Phase::query()
            ->active()
            ->with(['checklistItems' => fn ($query) => $query->active()])
            ->get();
    }
}
