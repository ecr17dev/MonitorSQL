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
        Schema::create('connection_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('database_connections')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('schema_name')->nullable();
            $table->string('table_name')->nullable();
            $table->string('column_name')->nullable();
            $table->boolean('can_view')->default(true);
            $table->unsignedInteger('max_rows')->nullable();
            $table->unsignedInteger('max_queries_per_hour')->nullable();
            $table->unsignedInteger('max_exports_per_day')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'schema_name', 'table_name']);
            $table->index(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_permissions');
    }
};
