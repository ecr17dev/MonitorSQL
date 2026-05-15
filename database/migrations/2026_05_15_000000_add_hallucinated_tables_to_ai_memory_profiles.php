<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_memory_profiles', function (Blueprint $table): void {
            $table->json('hallucinated_tables')->nullable()->after('successful_query_patterns');
        });
    }

    public function down(): void
    {
        Schema::table('ai_memory_profiles', function (Blueprint $table): void {
            $table->dropColumn('hallucinated_tables');
        });
    }
};
