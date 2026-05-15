<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('query_runs', function (Blueprint $table) {
            $table->string('category')->nullable()->after('status');
            $table->json('tags')->nullable()->after('category');
            $table->text('note')->nullable()->after('tags');
            $table->boolean('is_favorite')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('query_runs', function (Blueprint $table) {
            $table->dropColumn(['category', 'tags', 'note', 'is_favorite']);
        });
    }
};
