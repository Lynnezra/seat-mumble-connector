<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Web\Models\User as SeatUser;

/**
 * Mumble 用户认证模型
 * 
 * 存储用户的个人密码和认证状态信息
 */
class MumbleUserAuth extends Model
{
    /**
     * 表名
     */
    protected $table = 'mumble_user_auth';

    /**
     * 可填充字段
     */
    protected $fillable = [
        'seat_user_id',
        'mumble_username',
        'password_hash',
        'enabled',
        'auth_status',
        'last_login',
        'last_updated',
        'notes'
    ];

    /**
     * 字段类型转换
     */
    protected $casts = [
        'enabled' => 'boolean',
        'last_login' => 'datetime',
        'last_updated' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 隐藏字段（不包含在序列化中）
     */
    protected $hidden = [
        'password_hash'
    ];

    /**
     * 关联到 SeAT 用户
     */
    public function seatUser()
    {
        return $this->belongsTo(SeatUser::class, 'seat_user_id', 'id');
    }

    /**
     * 关联到 Connector 用户
     */
    public function connectorUser()
    {
        return $this->hasOne(\Warlof\Seat\Connector\Models\User::class, 'user_id', 'seat_user_id')
                    ->where('connector_type', 'mumble');
    }

    /**
     * 检查用户是否已启用个人密码认证
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 检查用户是否已认证
     */
    public function isAuthenticated(): bool
    {
        return $this->auth_status === 'authenticated';
    }

    /**
     * 设置认证状态
     */
    public function setAuthStatus(string $status): void
    {
        $this->auth_status = $status;
        $this->save();
    }

    /**
     * 更新最后登录时间
     */
    public function updateLastLogin(): void
    {
        $this->last_login = now();
        $this->save();
    }

    /**
     * 获取用户显示名称
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->seatUser && $this->seatUser->main_character) {
            return $this->seatUser->main_character->name;
        }
        
        return $this->mumble_username;
    }

    /**
     * 获取认证状态描述
     */
    public function getAuthStatusDescriptionAttribute(): string
    {
        switch ($this->auth_status) {
            case 'authenticated':
                return '已认证';
            case 'pending':
                return '待认证';
            case 'disabled':
                return '已禁用';
            case 'failed':
                return '认证失败';
            default:
                return '未知状态';
        }
    }

    /**
     * 作用域：仅获取启用的用户
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * 作用域：仅获取已认证的用户
     */
    public function scopeAuthenticated($query)
    {
        return $query->where('auth_status', 'authenticated');
    }

    /**
     * 作用域：按最后登录时间排序
     */
    public function scopeOrderByLastLogin($query, $direction = 'desc')
    {
        return $query->orderBy('last_login', $direction);
    }
}