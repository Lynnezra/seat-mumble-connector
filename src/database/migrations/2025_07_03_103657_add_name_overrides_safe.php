<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 安全地添加 name_override 列（如果不存在）
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查列是否存在，如果不存在才添加
        if (!Schema::hasColumn('seat_connector_users', 'name_override')) {
            Schema::table('seat_connector_users', function (Blueprint $table) {
                $table->string('name_override')->nullable()->after('connector_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('seat_connector_users', 'name_override')) {
            Schema::table('seat_connector_users', function (Blueprint $table) {
                $table->dropColumn('name_override');
            });
        }
    }
};
