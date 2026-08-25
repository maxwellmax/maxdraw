<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A sessão de treino ganha um rótulo escrito pelo candidato. É texto livre:
     * nenhum lookup, nenhum Enum, nenhuma FK. Nascendo nullable, toda sessão
     * preexistente já fica sem nome sem precisar de backfill. O teto de 60
     * caracteres é validação da FormRequest, não largura de coluna.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->string('name')->nullable()->after('problem_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * A coluna não tem FK nem índice, então `dropColumn` sozinho basta — a regra
     * de `dropForeign` antes do `dropColumn` só vale para coluna com FK.
     */
    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
