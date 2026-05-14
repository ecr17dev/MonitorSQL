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
        Schema::create('query_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('database_connections')->nullOnDelete();
            $table->text('sql');
            $table->text('normalized_sql')->nullable();
            $table->string('sql_hash', 64)->nullable();
            $table->string('status')->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('rows_returned')->default(0);
            $table->boolean('is_ai_generated')->default(false);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('sql_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_runs');
    }
};
