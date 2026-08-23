<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Com a ordem explícita na aresta, o modo de numeração deixa de existir: a
     * FK sai antes da coluna, e a coluna antes do lookup. O `dropForeign` é
     * obrigatório também no SQLite dos testes — sem ele o `DROP COLUMN` nativo
     * deixa a constraint órfã na DDL e o banco recusa a alteração.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['sequence_mode_id']);
            $table->dropColumn('sequence_mode_id');
        });

        Schema::dropIfExists('sequence_modes');
    }

    /**
     * Reverse the migrations.
     *
     * A estrutura volta, o conteúdo não: o lookup deixou de existir, então a
     * coluna renasce nullable e nenhum diagrama é tocado para preenchê-la.
     */
    public function down(): void
    {
        Schema::create('sequence_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 10)->unique();
            $table->string('legend_text', 255);
            $table->smallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('sequence_mode_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
};
