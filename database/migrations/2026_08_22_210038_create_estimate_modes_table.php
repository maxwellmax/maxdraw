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
        Schema::create('estimate_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 20)->unique();
            $table->string('highlighted_row', 60);
            $table->smallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimate_modes');
    }
};
