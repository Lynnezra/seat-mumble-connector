<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 Mumble 用户认证表
 * 
 * 存储用户个人密码和认证状态
 */
class CreateMumbleUserAuthTable extends Migration
{
    /**
     * 运行迁移
     */
    public function up()
    {
        Schema::create('mumble_user_auth', function (Blueprint $table) {
            $table->id();
            
            // 关联字段
            $table->unsignedInteger('seat_user_id')->comment('SeAT 用户ID');
            $table->string('mumble_username', 64)->comment('Mumble 用户名');
            
            // 认证字段
            $table->string('password_hash')->comment('密码哈希');
            $table->boolean('enabled')->default(true)->comment('是否启用个人密码认证');
            $table->enum('auth_status', ['pending', 'authenticated', 'failed', 'disabled'])
                  ->default('pending')->comment('认证状态');
            
            // 时间字段
            $table->timestamp('last_login')->nullable()->comment('最后登录时间');
            $table->timestamp('last_updated')->useCurrent()->comment('最后更新时间');
            
            // 其他字段
            $table->text('notes')->nullable()->comment('备注');
            
            $table->timestamps();
            
            // 索引
            $table->unique('mumble_username', 'idx_mumble_username');
            $table->index('seat_user_id', 'idx_seat_user_id');
            $table->index('enabled', 'idx_enabled');
            $table->index('auth_status', 'idx_auth_status');
            $table->index('last_login', 'idx_last_login');
            
            // 外键约束
            $table->foreign('seat_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        Schema::dropIfExists('mumble_user_auth');
    }
}