<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\LinkType;
use App\Models\Problem;
use App\Models\SessionDuration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionCreator;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A sessão nova nasce vazia (US-11.2): sem blocos, sem marcações, sem notas,
     * tempo zerado, duração 45 e a numeração das conexões visível.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'problem_id' => null,
            'session_duration_id' => fn (): int => $this->defaultSessionDurationId(),
            'show_connection_order' => true,
            'elapsed_seconds' => 0,
            'notes' => null,
            'nodes' => [],
            'edges' => [],
            'checks' => [],
            'estimate' => SessionCreator::DEFAULT_ESTIMATE,
            'last_opened_at' => now(),
        ];
    }

    /**
     * Indicate that the session already has a problem picked.
     */
    public function withProblem(?Problem $problem = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'problem_id' => is_null($problem) ? Problem::factory() : $problem->id,
        ]);
    }

    /**
     * Indicate that the session carries a diagram of the given size.
     */
    public function withDiagram(int $nodes, int $edges): static
    {
        if ($nodes < 0 || $edges < 0) {
            throw new InvalidArgumentException('Um diagrama não tem quantidade negativa de nós ou arestas.');
        }

        if ($edges > 0 && $nodes < 2) {
            throw new InvalidArgumentException('Uma aresta liga dois nós distintos: gere ao menos dois nós.');
        }

        return $this->state(fn (array $attributes): array => [
            'nodes' => $this->diagramNodes($nodes),
            'edges' => $this->diagramEdges($nodes, $edges),
        ]);
    }

    /**
     * Indicate that the session sits exactly on the payload limits.
     */
    public function atLimit(): static
    {
        return $this->withDiagram(200, 400);
    }

    private function defaultSessionDurationId(): int
    {
        $id = SessionDuration::query()->where('minutes', 45)->value('id');

        return is_null($id)
            ? SessionDuration::factory()->default()->create()->id
            : (int) $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function diagramNodes(int $count): array
    {
        $types = $this->componentSlugs();

        return collect($this->indexes($count))
            ->map(fn (int $index): array => [
                'id' => 'n'.$index,
                'type' => $types[$index % count($types)],
                'label' => 'Bloco '.$index,
                'x' => 120 + ($index % 8) * 140,
                'y' => 100 + intdiv($index, 8) * 90,
            ])
            ->all();
    }

    /**
     * A aresta nasce sem número: quem numera é o usuário, no cliente (RF-14a).
     *
     * @return array<int, array<string, mixed>>
     */
    private function diagramEdges(int $nodeCount, int $count): array
    {
        $kinds = $this->linkTypeSlugs();

        return collect($this->indexes($count))
            ->map(fn (int $index): array => [
                'id' => 'e'.$index,
                'from' => 'n'.($index % $nodeCount),
                'to' => 'n'.(($index + 1) % $nodeCount),
                'kind' => $kinds[$index % count($kinds)],
                'label' => '',
                'dashed' => false,
                'bidir' => false,
                'order' => null,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function indexes(int $count): array
    {
        return $count < 1 ? [] : range(0, $count - 1);
    }

    /**
     * O catálogo é resolvido por slug; sem catálogo seedado, a factory cria o
     * mínimo para o payload continuar apontando para linhas que existem.
     *
     * @return array<int, string>
     */
    private function componentSlugs(): array
    {
        /** @var array<int, string> $slugs */
        $slugs = Component::query()->pluck('slug')->all();

        return $slugs === [] ? [Component::factory()->create(['slug' => 'api'])->slug] : $slugs;
    }

    /**
     * @return array<int, string>
     */
    private function linkTypeSlugs(): array
    {
        /** @var array<int, string> $slugs */
        $slugs = LinkType::query()->pluck('slug')->all();

        return $slugs === [] ? [LinkType::factory()->create(['slug' => 'http'])->slug] : $slugs;
    }
}
