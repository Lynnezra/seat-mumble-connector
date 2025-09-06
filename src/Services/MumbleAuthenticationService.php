<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Lynnezra\Seat\Connector\Drivers\Mumble\Models\MumbleUserAuth;
use Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient;
use Warlof\Seat\Connector\Models\User;

/**
 * Mumble 自定义身份验证服务
 * 
 * 实现用户使用个人密码登录并自动获得认证权限的功能
 * 支持绕过服务器密码，同时保持服务器密码的存在
 */
class MumbleAuthenticationService
{
    private $client;
    private $iceService;

    public function __construct()
    {
        $this->client = MumbleClient::getInstance();
        // 获取 Ice 服务实例
        if (method_exists($this->client, 'getIceService')) {
            $this->iceService = $this->client->getIceService();
        }
    }

    /**
     * 验证用户个人密码并处理认证
     * 
     * @param string $username Mumble 用户名
     * @param string $password 用户输入的密码
     * @param string $serverPassword 服务器密码（可选）
     * @return array 认证结果
     */
    public function authenticateUser(string $username, string $password, string $serverPassword = null): array
    {
        try {
            // 1. 检查是否启用了自定义认证
            if (!$this->isCustomAuthEnabled()) {
                return $this->handleStandardAuth($username, $password, $serverPassword);
            }

            // 2. 首先检查是否是服务器密码
            if ($this->isServerPassword($password)) {
                logger()->info('User authenticated with server password', ['username' => $username]);
                return [
                    'success' => true,
                    'auth_type' => 'server_password',
                    'authenticated' => true,
                    'message' => 'Authenticated with server password'
                ];
            }

            // 3. 检查个人密码
            $userAuth = $this->getUserAuthRecord($username);
            if ($userAuth && $this->verifyPersonalPassword($userAuth, $password)) {
                // 个人密码验证成功，自动设置为已认证状态
                $this->setUserAuthenticated($username, $userAuth->seat_user_id);
                
                Log::info('User authenticated with personal password', [
                    'username' => $username,
                    'seat_user_id' => $userAuth->seat_user_id
                ]);

                return [
                    'success' => true,
                    'auth_type' => 'personal_password',
                    'authenticated' => true,
                    'auto_auth' => true,
                    'message' => 'Authenticated with personal password, auto-granted auth permissions'
                ];
            }

            // 4. 密码验证失败
            Log::warning('Authentication failed for user', ['username' => $username]);
            return [
                'success' => false,
                'auth_type' => 'unknown',
                'authenticated' => false,
                'message' => 'Invalid password'
            ];

        } catch (\Exception $e) {
            Log::error('Authentication error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'auth_type' => 'error',
                'authenticated' => false,
                'message' => 'Authentication error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 设置用户密码
     * 
     * @param string $username Mumble 用户名
     * @param string $password 新密码
     * @param int $seatUserId SeAT 用户ID
     * @return bool
     */
    public function setUserPassword(string $username, string $password, int $seatUserId): bool
    {
        try {
            $userAuth = MumbleUserAuth::updateOrCreate(
                ['mumble_username' => $username],
                [
                    'seat_user_id' => $seatUserId,
                    'password_hash' => Hash::make($password),
                    'enabled' => true,
                    'last_updated' => now()
                ]
            );

            Log::info('User password updated', [
                'username' => $username,
                'seat_user_id' => $seatUserId
            ]);

            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to set user password', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 移除用户密码
     */
    public function removeUserPassword(string $username): bool
    {
        try {
            MumbleUserAuth::where('mumble_username', $username)->delete();
            
            Log::info('User password removed', ['username' => $username]);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to remove user password', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查用户是否已设置个人密码
     */
    public function hasPersonalPassword(string $username): bool
    {
        return MumbleUserAuth::where('mumble_username', $username)
                           ->where('enabled', true)
                           ->exists();
    }

    /**
     * 获取所有设置了个人密码的用户
     */
    public function getUsersWithPersonalPasswords(): array
    {
        return MumbleUserAuth::where('enabled', true)
                            ->with('seatUser')
                            ->get()
                            ->toArray();
    }

    /**
     * 验证个人密码
     */
    private function verifyPersonalPassword(MumbleUserAuth $userAuth, string $password): bool
    {
        return Hash::check($password, $userAuth->password_hash);
    }

    /**
     * 获取用户认证记录
     */
    private function getUserAuthRecord(string $username): ?MumbleUserAuth
    {
        return MumbleUserAuth::where('mumble_username', $username)
                            ->where('enabled', true)
                            ->first();
    }

    /**
     * 检查是否是服务器密码
     */
    private function isServerPassword(string $password): bool
    {
        $serverPassword = $this->getServerPassword();
        return !empty($serverPassword) && $password === $serverPassword;
    }

    /**
     * 获取服务器密码
     */
    private function getServerPassword(): ?string
    {
        $settings = setting('seat-connector.drivers.mumble', true);
        return is_object($settings) && property_exists($settings, 'server_password') 
               ? $settings->server_password 
               : null;
    }

    /**
     * 检查是否启用了自定义认证
     */
    private function isCustomAuthEnabled(): bool
    {
        $settings = setting('seat-connector.drivers.mumble', true);
        return is_object($settings) && property_exists($settings, 'enable_custom_auth') 
               ? $settings->enable_custom_auth 
               : true; // 默认启用
    }

    /**
     * 设置用户为已认证状态
     */
    private function setUserAuthenticated(string $username, int $seatUserId): void
    {
        try {
            // 通过 Ice 接口设置用户认证状态
            if ($this->iceService) {
                $this->iceService->setUserAuthenticated($username, true);
                
                // 同时设置用户的基本权限
                $this->iceService->setUserPermissions($username, [
                    'authenticated' => true,
                    'can_speak' => true,
                    'can_hear' => true
                ]);
            }

            // 更新用户认证记录
            MumbleUserAuth::where('mumble_username', $username)
                         ->update([
                             'last_login' => now(),
                             'auth_status' => 'authenticated'
                         ]);

        } catch (\Exception $e) {
            logger()->error('Failed to set user authenticated status', [
                'username' => $username,
                'seat_user_id' => $seatUserId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 处理标准认证（当自定义认证禁用时）
     */
    private function handleStandardAuth(string $username, string $password, string $serverPassword = null): array
    {
        // 使用服务器密码或标准认证流程
        $expectedPassword = $serverPassword ?: $this->getServerPassword();
        
        if ($password === $expectedPassword) {
            return [
                'success' => true,
                'auth_type' => 'server_password',
                'authenticated' => true,
                'message' => 'Authenticated with server password'
            ];
        }

        return [
            'success' => false,
            'auth_type' => 'server_password',
            'authenticated' => false,
            'message' => 'Invalid server password'
        ];
    }
}