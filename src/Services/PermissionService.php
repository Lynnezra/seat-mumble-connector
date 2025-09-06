<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Services;

use Illuminate\Support\Collection;
use Warlof\Seat\Connector\Models\User;
use Seat\Web\Models\User as SeatUser;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Alliances\Alliance;

/**
 * Mumble 权限管理服务
 * 
 * 负责管理用户在 Mumble 服务器上的权限，包括：
 * - 管理员权限分配
 * - 角色权限映射
 * - 频道权限管理
 * - 权限继承和优先级
 */
class PermissionService
{
    /**
     * Mumble 权限常量
     */
    const PERMISSION_NONE = 0;
    const PERMISSION_WRITE = 1;
    const PERMISSION_TRAVERSE = 2;
    const PERMISSION_ENTER = 4;
    const PERMISSION_SPEAK = 8;
    const PERMISSION_MUTEDEAFEN = 16;
    const PERMISSION_MOVE = 32;
    const PERMISSION_MAKECHANNEL = 64;
    const PERMISSION_LINKTHREAD = 128;
    const PERMISSION_WHISPER = 256;
    const PERMISSION_TEXTMESSAGE = 512;
    const PERMISSION_MAKECHANNEL_TEMPORARY = 1024;
    const PERMISSION_KICK = 2048;
    const PERMISSION_BAN = 4096;
    const PERMISSION_REGISTER = 8192;
    const PERMISSION_SELFREGISTER = 16384;

    /**
     * 预定义权限组合
     */
    const ROLE_ADMIN = self::PERMISSION_WRITE | self::PERMISSION_TRAVERSE | 
                       self::PERMISSION_ENTER | self::PERMISSION_SPEAK | 
                       self::PERMISSION_MUTEDEAFEN | self::PERMISSION_MOVE | 
                       self::PERMISSION_MAKECHANNEL | self::PERMISSION_LINKTHREAD |
                       self::PERMISSION_WHISPER | self::PERMISSION_TEXTMESSAGE | 
                       self::PERMISSION_MAKECHANNEL_TEMPORARY | self::PERMISSION_KICK | 
                       self::PERMISSION_BAN | self::PERMISSION_REGISTER | 
                       self::PERMISSION_SELFREGISTER;

    const ROLE_MODERATOR = self::PERMISSION_TRAVERSE | self::PERMISSION_ENTER | 
                          self::PERMISSION_SPEAK | self::PERMISSION_MUTEDEAFEN | 
                          self::PERMISSION_MOVE | self::PERMISSION_MAKECHANNEL | 
                          self::PERMISSION_WHISPER | self::PERMISSION_TEXTMESSAGE | 
                          self::PERMISSION_MAKECHANNEL_TEMPORARY | self::PERMISSION_KICK;

    const ROLE_USER = self::PERMISSION_TRAVERSE | self::PERMISSION_ENTER | 
                     self::PERMISSION_SPEAK | self::PERMISSION_WHISPER | 
                     self::PERMISSION_TEXTMESSAGE;

    const ROLE_GUEST = self::PERMISSION_TRAVERSE | self::PERMISSION_ENTER | 
                      self::PERMISSION_SPEAK | self::PERMISSION_TEXTMESSAGE;

    /**
     * 获取用户权限配置
     */
    public function getUserPermissions(User $mumbleUser): array
    {
        $seatUser = $mumbleUser->user;
        $permissions = [];

        // 1. 检查是否为超级管理员
        if ($this->isSuperAdmin($seatUser)) {
            $permissions['global'] = self::ROLE_ADMIN;
            $permissions['role'] = 'admin';
            logger()->info('User granted admin permissions (superuser)', [
                'user_id' => $seatUser->id,
                'username' => $mumbleUser->connector_name
            ]);
            return $permissions;
        }

        // 2. 检查是否为配置的管理员
        if ($this->isConfiguredAdmin($seatUser)) {
            $permissions['global'] = self::ROLE_ADMIN;
            $permissions['role'] = 'admin';
            logger()->info('User granted admin permissions (configured)', [
                'user_id' => $seatUser->id,
                'username' => $mumbleUser->connector_name
            ]);
            return $permissions;
        }

        // 3. 检查军团/联盟管理员权限
        $corporationRole = $this->getCorporationRole($seatUser);
        if ($corporationRole) {
            $permissions = array_merge($permissions, $this->getCorporationPermissions($corporationRole, $seatUser));
        }

        // 4. 检查 SeAT 角色权限
        $seatRoles = $this->getSeatRolePermissions($seatUser);
        if (!empty($seatRoles)) {
            $permissions = array_merge($permissions, $seatRoles);
        }

        // 5. 默认用户权限
        if (empty($permissions)) {
            $permissions['global'] = self::ROLE_USER;
            $permissions['role'] = 'user';
        }

        return $permissions;
    }

    /**
     * 检查是否为超级管理员
     */
    public function isSuperAdmin(SeatUser $user): bool
    {
        // 检查是否有 global.superuser 权限
        return $user->can('global.superuser') || $user->hasRole('superuser');
    }

    /**
     * 检查是否为配置的管理员
     */
    public function isConfiguredAdmin(SeatUser $user): bool
    {
        $adminConfig = setting('seat-connector.drivers.mumble.admin_users', true);
        
        if (empty($adminConfig)) {
            return false;
        }

        // 支持多种配置方式
        if (is_string($adminConfig)) {
            $adminList = explode(',', $adminConfig);
        } elseif (is_array($adminConfig)) {
            $adminList = $adminConfig;
        } else {
            return false;
        }

        // 检查用户ID、用户名或主角色名
        $mainCharacter = $user->main_character;
        
        foreach ($adminList as $admin) {
            $admin = trim($admin);
            
            // 检查用户ID
            if (is_numeric($admin) && $user->id == $admin) {
                return true;
            }
            
            // 检查用户名
            if ($user->name === $admin) {
                return true;
            }
            
            // 检查主角色名
            if ($mainCharacter && $mainCharacter->name === $admin) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取军团角色
     */
    public function getCorporationRole(SeatUser $user): ?string
    {
        $mainCharacter = $user->main_character;
        if (!$mainCharacter) {
            return null;
        }

        // 检查是否为军团 CEO 或董事
        $corporation = CorporationInfo::find($mainCharacter->affiliation->corporation_id);
        if (!$corporation) {
            return null;
        }

        // 检查 CEO 权限
        if ($mainCharacter->character_id == $corporation->ceo_id) {
            return 'ceo';
        }

        // 检查董事权限（通过角色）
        $titles = $mainCharacter->titles;
        foreach ($titles as $title) {
            if (in_array(strtolower($title->name), ['director', '董事', 'leadership', 'officer'])) {
                return 'director';
            }
        }

        return 'member';
    }

    /**
     * 获取军团权限
     */
    public function getCorporationPermissions(string $role, SeatUser $user): array
    {
        $permissions = [];

        switch ($role) {
            case 'ceo':
                $permissions['corporation'] = self::ROLE_MODERATOR;
                $permissions['role'] = 'corp_ceo';
                break;
            case 'director':
                $permissions['corporation'] = self::ROLE_MODERATOR & ~self::PERMISSION_KICK;
                $permissions['role'] = 'corp_director';
                break;
            case 'member':
                $permissions['corporation'] = self::ROLE_USER;
                $permissions['role'] = 'corp_member';
                break;
        }

        return $permissions;
    }

    /**
     * 获取 SeAT 角色权限
     */
    public function getSeatRolePermissions(SeatUser $user): array
    {
        $permissions = [];
        $roles = $user->roles;

        foreach ($roles as $role) {
            switch (strtolower($role->title)) {
                case 'mumble_admin':
                case 'voice_admin':
                    $permissions['global'] = self::ROLE_ADMIN;
                    $permissions['role'] = 'seat_admin';
                    break;
                case 'mumble_moderator':
                case 'voice_moderator':
                    $permissions['global'] = self::ROLE_MODERATOR;
                    $permissions['role'] = 'seat_moderator';
                    break;
                case 'fleet_commander':
                case 'fc':
                    $permissions['fleet'] = self::ROLE_MODERATOR;
                    $permissions['role'] = 'fleet_commander';
                    break;
            }
        }

        return $permissions;
    }

    /**
     * 获取权限配置
     */
    public function getPermissionConfig(): array
    {
        return config('mumble-connector.permission_mapping', [
            'superuser' => [
                'admin' => true,
                'kick' => true,
                'ban' => true,
                'mute' => true,
                'move' => true,
                'create_channel' => true,
                'delete_channel' => true,
            ],
            'corporation_ceo' => [
                'kick' => true,
                'mute' => true,
                'move' => true,
                'create_channel' => true,
            ],
            'corporation_director' => [
                'mute' => true,
                'move' => true,
                'create_channel' => true,
            ],
            'member' => [
                'speak' => true,
                'whisper' => true,
                'text_message' => true,
            ]
        ]);
    }

    /**
     * 更新权限配置
     */
    public function updatePermissionConfig(array $config): bool
    {
        try {
            setting(['seat-connector.drivers.mumble.permission_mapping', $config], true);
            return true;
        } catch (\Exception $e) {
            logger()->error('Failed to update permission config', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 添加管理员用户
     */
    public function addAdminUser($userIdentifier): bool
    {
        try {
            $currentAdmins = setting('seat-connector.drivers.mumble.admin_users', true) ?: '';
            
            if (is_string($currentAdmins)) {
                $adminList = array_filter(explode(',', $currentAdmins));
            } else {
                $adminList = is_array($currentAdmins) ? $currentAdmins : [];
            }

            if (!in_array($userIdentifier, $adminList)) {
                $adminList[] = $userIdentifier;
                setting(['seat-connector.drivers.mumble.admin_users', implode(',', $adminList)], true);
                
                logger()->info('Added Mumble admin user', [
                    'user_identifier' => $userIdentifier
                ]);
            }

            return true;
        } catch (\Exception $e) {
            logger()->error('Failed to add admin user', [
                'user_identifier' => $userIdentifier,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 移除管理员用户
     */
    public function removeAdminUser($userIdentifier): bool
    {
        try {
            $currentAdmins = setting('seat-connector.drivers.mumble.admin_users', true) ?: '';
            
            if (is_string($currentAdmins)) {
                $adminList = array_filter(explode(',', $currentAdmins));
            } else {
                $adminList = is_array($currentAdmins) ? $currentAdmins : [];
            }

            $adminList = array_filter($adminList, function($admin) use ($userIdentifier) {
                return trim($admin) !== trim($userIdentifier);
            });

            setting(['seat-connector.drivers.mumble.admin_users', implode(',', $adminList)], true);
            
            logger()->info('Removed Mumble admin user', [
                'user_identifier' => $userIdentifier
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error('Failed to remove admin user', [
                'user_identifier' => $userIdentifier,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取当前管理员列表
     */
    public function getAdminUsers(): array
    {
        $adminConfig = setting('seat-connector.drivers.mumble.admin_users', true) ?: '';
        
        if (is_string($adminConfig)) {
            return array_filter(explode(',', $adminConfig));
        } elseif (is_array($adminConfig)) {
            return $adminConfig;
        }

        return [];
    }

    /**
     * 权限名称映射
     */
    public function getPermissionNames(): array
    {
        return [
            self::PERMISSION_WRITE => 'write',
            self::PERMISSION_TRAVERSE => 'traverse',
            self::PERMISSION_ENTER => 'enter',
            self::PERMISSION_SPEAK => 'speak',
            self::PERMISSION_MUTEDEAFEN => 'mute_deafen',
            self::PERMISSION_MOVE => 'move',
            self::PERMISSION_MAKECHANNEL => 'make_channel',
            self::PERMISSION_LINKTHREAD => 'link_thread',
            self::PERMISSION_WHISPER => 'whisper',
            self::PERMISSION_TEXTMESSAGE => 'text_message',
            self::PERMISSION_MAKECHANNEL_TEMPORARY => 'make_temp_channel',
            self::PERMISSION_KICK => 'kick',
            self::PERMISSION_BAN => 'ban',
            self::PERMISSION_REGISTER => 'register',
            self::PERMISSION_SELFREGISTER => 'self_register',
        ];
    }
}