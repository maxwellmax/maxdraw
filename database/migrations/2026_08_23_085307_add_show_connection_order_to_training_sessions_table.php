<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A numeração deixa de ser derivada do desenho e passa a ser um atributo da
     * aresta. Nenhum diagrama antigo nasce numerado: o backfill grava
     * `order = null` em toda aresta de toda sessão, sem consultar o modo de
     * numeração que a sessão tinha e sem tocar em nenhuma outra chave.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->boolean('show_connection_order')->default(true);
        });

        DB::table('training_sessions')
            ->select(['id', 'edges'])
            ->chunkById(100, function (Collection $sessions): void {
                foreach ($sessions as $session) {
                    DB::table('training_sessions')
                        ->where('id', $session->id)
                        ->update(['edges' => $this->edgesWithOrder($session->edges)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * A chave `order` fica onde está: remover a coluna não é motivo para
     * reescrever o JSON de nenhum diagrama.
     */
    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('show_connection_order');
        });
    }

    /**
     * O JSON da coluna `edges` com a chave nova em cada aresta. O que não for
     * uma lista de arestas volta como veio — a migration não corrige diagrama.
     */
    private function edgesWithOrder(?string $edges): string
    {
        $decoded = json_decode((string) $edges, true);

        if (! is_array($decoded)) {
            return (string) $edges;
        }

        return (string) json_encode(array_map(
            fn (mixed $edge): mixed => is_array($edge) ? [...$edge, 'order' => null] : $edge,
            $decoded,
        ));
    }
};
