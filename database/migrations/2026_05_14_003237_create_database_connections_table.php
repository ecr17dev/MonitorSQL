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
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('database');
            $table->string('username');
            $table->text('password');
            $table->boolean('ssl_enabled')->default(false);
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_rows')->default(1000);
            $table->unsignedInteger('query_timeout_seconds')->default(30);
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
