<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 120);
            $table->string('tag', 80);
            $table->foreignId('problem_level_id')->constrained()->restrictOnDelete();
            $table->text('context');
            $table->smallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['problem_level_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('problems');
    }
};
