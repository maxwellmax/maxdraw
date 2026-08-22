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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_category_id')->constrained()->restrictOnDelete();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->string('short_name', 60);
            $table->string('icon_key', 40);
            $table->smallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['component_category_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
