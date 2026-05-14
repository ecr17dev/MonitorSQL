<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->text('api_key')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('display_name');
            $table->string('default_model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
