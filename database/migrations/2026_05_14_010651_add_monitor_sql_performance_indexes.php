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
        Schema::table('query_runs', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'query_runs_status_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'audit_logs_status_created_at_idx');
            $table->index(['connection_id', 'created_at'], 'audit_logs_connection_created_at_idx');
        });

        Schema::table('data_exports', function (Blueprint $table): void {
            $table->index(['connection_id', 'status'], 'data_exports_connection_status_idx');
            $table->index(['format', 'created_at'], 'data_exports_format_created_at_idx');
        });

        Schema::table('connection_permissions', function (Blueprint $table): void {
            $table->index(['table_name', 'can_view'], 'connection_permissions_table_can_view_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_runs', function (Blueprint $table): void {
            $table->dropIndex('query_runs_status_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_status_created_at_idx');
            $table->dropIndex('audit_logs_connection_created_at_idx');
        });

        Schema::table('data_exports', function (Blueprint $table): void {
            $table->dropIndex('data_exports_connection_status_idx');
            $table->dropIndex('data_exports_format_created_at_idx');
        });

        Schema::table('connection_permissions', function (Blueprint $table): void {
            $table->dropIndex('connection_permissions_table_can_view_idx');
        });
    }
};
