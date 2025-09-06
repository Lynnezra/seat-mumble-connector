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
        // Mumble 连接器使用 seat-connector 的基础用户表
        // 这个迁移文件确保表存在，如果不存在则创建
        if (!Schema::hasTable('seat_connector_users')) {
            Schema::create('seat_connector_users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('connector_type');
                $table->string('connector_id');
                $table->string('connector_name');
                $table->string('name_override')->nullable();
                $table->string('nickname')->nullable()->comment('用户自定义昵称');
                $table->unsignedBigInteger('user_id');
                $table->string('unique_id');
                $table->timestamps();

                $table->index(['connector_type', 'user_id']);
                $table->unique(['connector_type', 'unique_id']);
                
                $table->foreign('user_id')->references('id')->on('users');
            });
        } else {
            // 如果表已存在，检查是否需要添加 nickname 字段
            if (!Schema::hasColumn('seat_connector_users', 'nickname')) {
                Schema::table('seat_connector_users', function (Blueprint $table) {
                    $table->string('nickname')->nullable()->after('name_override')->comment('用户自定义昵称');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 不删除表，因为可能被其他连接器使用
        // 如果需要删除 nickname 字段：
        if (Schema::hasColumn('seat_connector_users', 'nickname')) {
            Schema::table('seat_connector_users', function (Blueprint $table) {
                $table->dropColumn('nickname');
            });
        }
    }
};