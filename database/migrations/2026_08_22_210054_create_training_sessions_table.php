<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tabela de treino chama-se `training_sessions` porque `sessions` pertence
     * ao driver de sessão do Laravel — as duas coexistem no mesmo banco.
     */
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('problem_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('session_duration_id')->constrained()->restrictOnDelete();
            $table->foreignId('sequence_mode_id')->constrained()->restrictOnDelete();
            $table->integer('elapsed_seconds')->default(0);
            $table->text('notes')->nullable();
            $table->json('nodes');
            $table->json('edges');
            $table->json('checks');
            $table->json('estimate');
            $table->timestamp('last_opened_at');
            $table->timestamps();

            $table->index(['user_id', 'last_opened_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
