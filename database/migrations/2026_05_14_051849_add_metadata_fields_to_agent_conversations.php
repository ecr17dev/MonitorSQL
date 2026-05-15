<?php

use App\Models\DatabaseConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->foreignId('connection_id')->nullable()->after('user_id')->constrained('database_connections')->nullOnDelete();
            $table->boolean('is_archived')->default(false)->after('title');
            $table->boolean('is_pinned')->default(false)->after('is_archived');
            $table->unsignedInteger('message_count')->default(0)->after('is_pinned');
            $table->timestamp('last_message_at')->nullable()->after('message_count');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropForeignIdFor(DatabaseConnection::class, 'connection_id');
            $table->dropColumn(['connection_id', 'is_archived', 'is_pinned', 'message_count', 'last_message_at']);
        });
    }
};
