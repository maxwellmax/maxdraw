<?php

namespace App\Http\Requests;

use App\Models\ChecklistItem;
use App\Models\Component;
use App\Models\EstimateMode;
use App\Models\LinkType;
use App\Models\SequenceMode;
use App\Models\SessionDuration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingSessionUpdateRequest extends FormRequest
{
    /**
     * Os limites do payload do autosave. São validação, não coluna: revisá-los
     * não pede migration (US-3.1, US-3.3, US-3.2, US-4.2, US-6.3).
     */
    private const MAX_NODES = 200;

    private const MAX_EDGES = 400;

    private const MAX_LABEL = 60;

    private const MAX_NOTES = 5000;

    /**
     * Os parâmetros numéricos da calculadora de capacidade.
     *
     * @var array<int, string>
     */
    private const ESTIMATE_FIELDS = ['dau', 'act', 'per_month', 'ratio', 'size', 'peak', 'ret'];

    /**
     * Prepare the data for validation.
     *
     * O que dá para consertar não derruba um autosave: numeração inválida vira
     * `out` (US-4.3), número negativo na estimativa vira zero (US-6.2) e o texto
     * que o framework anulou volta a ser string vazia.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['seq_mode' => $this->normalizedSeqMode()]);

        if (is_array($this->input('estimate'))) {
            $this->merge(['estimate' => $this->normalizedEstimate()]);
        }

        foreach (['nodes' => ['label'], 'edges' => ['kind', 'label']] as $field => $keys) {
            if (is_array($this->input($field))) {
                $this->merge([$field => $this->withTextKeysBack($field, $keys)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * O dono da sessão nunca vem do cliente: `user_id` fica de fora das regras,
     * e é o que sai de `validated()` que chega ao model (US-1.4).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $nodeIds = $this->collect('nodes')
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) || is_int($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_NOTES],
            'elapsed_seconds' => ['sometimes', 'integer', 'min:0'],
            'duration_minutes' => ['sometimes', 'integer', Rule::in(SessionDuration::query()->pluck('minutes')->all())],
            'seq_mode' => ['required', 'string', Rule::in(SequenceMode::query()->pluck('slug')->all())],

            'nodes' => ['sometimes', 'array', 'max:'.self::MAX_NODES],
            'nodes.*.id' => ['required', 'string', 'distinct'],
            'nodes.*.type' => ['required', 'string', Rule::in(Component::query()->pluck('slug')->all())],
            'nodes.*.label' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_LABEL],
            'nodes.*.x' => ['required', 'numeric'],
            'nodes.*.y' => ['required', 'numeric'],

            'edges' => ['sometimes', 'array', 'max:'.self::MAX_EDGES],
            'edges.*.id' => ['required', 'string', 'distinct'],
            'edges.*.from' => ['required', 'string', Rule::in($nodeIds)],
            'edges.*.to' => ['required', 'string', Rule::in($nodeIds), $this->notTheSourceOfTheEdge()],
            'edges.*.kind' => ['sometimes', 'nullable', 'string', Rule::in($this->linkKinds())],
            'edges.*.label' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_LABEL],
            'edges.*.dashed' => ['sometimes', 'boolean'],
            'edges.*.bidir' => ['sometimes', 'boolean'],

            'checks' => ['sometimes', 'array', $this->knownChecklistItems()],
            'checks.*' => ['boolean'],

            'estimate' => ['sometimes', 'array'],
            'estimate.mode' => ['required_with:estimate', 'string', Rule::in(EstimateMode::query()->pluck('slug')->all())],
            ...collect(self::ESTIMATE_FIELDS)
                ->mapWithKeys(fn (string $field): array => ['estimate.'.$field => ['sometimes', 'numeric', 'min:0']])
                ->all(),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nodes.max' => 'O diagrama comporta no máximo '.self::inWords(self::MAX_NODES).' blocos.',
            'edges.max' => 'O diagrama comporta no máximo '.self::inWords(self::MAX_EDGES).' ligações.',
            'notes.max' => 'As notas têm no máximo '.self::inWords(self::MAX_NOTES).' caracteres.',
            'nodes.*.label.max' => 'O rótulo do bloco tem no máximo '.self::inWords(self::MAX_LABEL).' caracteres.',
            'edges.*.label.max' => 'O rótulo da ligação tem no máximo '.self::inWords(self::MAX_LABEL).' caracteres.',
            'nodes.*.type.in' => 'Este componente não existe no catálogo.',
            'edges.*.kind.in' => 'Este tipo de ligação não existe no catálogo.',
            'edges.*.from.in' => 'A ligação precisa sair de um bloco do próprio diagrama.',
            'edges.*.to.in' => 'A ligação precisa chegar a um bloco do próprio diagrama.',
            'duration_minutes.in' => 'A duração do treino é de 30, 45 ou 60 minutos.',
            'estimate.mode.in' => 'Este modo de estimativa não existe no catálogo.',
        ];
    }

    /**
     * O limite como a mensagem o mostra: milhar com ponto, como se lê em português.
     */
    private static function inWords(int $limit): string
    {
        return number_format($limit, 0, ',', '.');
    }

    /**
     * O `ConvertEmptyStringsToNull` do framework troca `''` por `null` na
     * entrada, e o motor do canvas fala string: "sem tipo" e rótulo em branco
     * são string vazia, não nulo. Só chaves que vieram no payload são tocadas.
     *
     * @param  array<int, string>  $keys
     * @return array<int, mixed>
     */
    private function withTextKeysBack(string $field, array $keys): array
    {
        return $this->collect($field)
            ->map(function (mixed $item) use ($keys): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                foreach ($keys as $key) {
                    if (array_key_exists($key, $item) && is_null($item[$key])) {
                        $item[$key] = '';
                    }
                }

                return $item;
            })
            ->all();
    }

    /**
     * Uma ligação sai de um bloco e chega em outro — laço não é ligação.
     */
    private function notTheSourceOfTheEdge(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === $this->input(str()->beforeLast($attribute, '.').'.from')) {
                $fail('Uma ligação precisa chegar a um bloco diferente do de origem.');
            }
        };
    }

    /**
     * O mapa `checks` é chaveado por `checklist_items.id`: chave que não seja um
     * item do catálogo derruba a gravação (US-7.1).
     */
    private function knownChecklistItems(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $known = ChecklistItem::query()->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all();

            foreach (array_keys((array) $value) as $key) {
                if (! in_array((string) $key, $known, true)) {
                    $fail('O checklist é chaveado pelo id do item: ['.$key.'] não é um item do catálogo.');
                }
            }
        };
    }

    /**
     * "Sem tipo" é uma ligação legítima: a string vazia convive com os 9 slugs.
     *
     * @return array<int, string>
     */
    private function linkKinds(): array
    {
        return [...LinkType::query()->pluck('slug')->all(), ''];
    }

    private function normalizedSeqMode(): string
    {
        $slug = $this->input('seq_mode');

        return is_string($slug) && SequenceMode::query()->where('slug', $slug)->exists()
            ? $slug
            : SequenceMode::DEFAULT_SLUG;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedEstimate(): array
    {
        /** @var array<string, mixed> $estimate */
        $estimate = $this->input('estimate');

        return collect($estimate)
            ->map(fn (mixed $value, string $key): mixed => in_array($key, self::ESTIMATE_FIELDS, true) && is_numeric($value) && $value < 0
                ? 0
                : $value)
            ->all();
    }
}
