<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Ice;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Mumble Ice 接口服务
 * 
 * 提供与 Mumble 服务器 Ice 接口的完整集成功能
 * 支持用户管理、频道管理、权限控制等功能
 * 
 * 依赖要求：
 * - PHP Ice 扩展 (php-zeroc-ice)
 * - Mumble 服务器启用 Ice 接口
 * - 正确的 Ice 配置参数
 */

/**
 * Mumble Ice 接口服务
 * 用于与 Mumble 服务器的 Ice 接口通信
 */
class MumbleIceService
{
    private $communicator = null;
    private $meta = null;
    private $server = null;
    private $settings;
    private $connected = false;
    private $lastError = null;
    
    // Ice 接口常量
    const ICE_TIMEOUT = 10; // 连接超时时间（秒）
    const DEFAULT_SERVER_ID = 1; // 默认服务器ID

    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->initializeCommunicator();
    }

    /**
     * 初始化 Ice 通信器
     * 
     * @throws Exception 当连接失败时抛出异常
     */
    private function initializeCommunicator(): void
    {
        // 检查 Ice 扩展是否可用
        if (!extension_loaded('ice')) {
            throw new Exception('PHP Ice extension is not loaded. Please install php-zeroc-ice.');
        }
        
        try {
            // 设置 Ice 连接参数
            $ice_host = $this->settings->mumble_ice_host ?? '127.0.0.1';
            $ice_port = $this->settings->mumble_ice_port ?? 6502;
            $ice_secret = $this->settings->mumble_ice_secret ?? '';
            $ice_timeout = $this->settings->mumble_ice_timeout ?? self::ICE_TIMEOUT;
            
            // 验证配置参数
            if (empty($ice_host)) {
                throw new Exception('Mumble Ice host not configured');
            }
            
            if (!is_numeric($ice_port) || $ice_port <= 0) {
                throw new Exception('Invalid Mumble Ice port configuration');
            }
            
            // 创建 Ice 属性
            $properties = \Ice\createProperties();
            
            // 设置连接属性
            $properties->setProperty('Ice.Default.Timeout', $ice_timeout * 1000); // 转换为毫秒
            $properties->setProperty('Ice.RetryIntervals', '0 1000 5000'); // 重试间隔
            $properties->setProperty('Ice.Warn.Connections', '1'); // 启用连接警告
            
            // 如果有密钥，设置认证
            if (!empty($ice_secret)) {
                $properties->setProperty('Ice.ImplicitContext', 'Shared');
            }
            
            // 初始化通信器
            $initData = new \Ice\InitializationData();
            $initData->properties = $properties;
            $this->communicator = \Ice\initialize($initData);
            
            // 构建连接字符串
            $connection_string = "Meta:tcp -h {$ice_host} -p {$ice_port}";
            
            // 连接到 Mumble Meta 服务
            $meta_proxy = $this->communicator->stringToProxy($connection_string);
            
            // 设置超时
            $meta_proxy = $meta_proxy->ice_timeout($ice_timeout * 1000);
            
            // 检查代理类型
            $this->meta = $meta_proxy->ice_checkedCast("::Murmur::Meta");
            
            if (!$this->meta) {
                throw new Exception("Failed to connect to Mumble Meta service at {$ice_host}:{$ice_port}");
            }
            
            // 设置密钥认证
            if (!empty($ice_secret)) {
                $context = $this->communicator->getImplicitContext();
                $context->put('secret', $ice_secret);
                
                // 测试认证
                try {
                    $this->meta->ice_ping();
                } catch (Exception $e) {
                    throw new Exception("Ice authentication failed: " . $e->getMessage());
                }
            } else {
                // 测试连接（无认证）
                $this->meta->ice_ping();
            }
            
            $this->connected = true;
            $this->lastError = null;
            
            Log::info('Successfully connected to Mumble Ice interface', [
                'host' => $ice_host,
                'port' => $ice_port,
                'timeout' => $ice_timeout,
                'authenticated' => !empty($ice_secret)
            ]);
            
        } catch (Exception $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            
            Log::error('Failed to initialize Mumble Ice communicator', [
                'error' => $e->getMessage(),
                'host' => $ice_host ?? 'not set',
                'port' => $ice_port ?? 'not set'
            ]);
            
            throw new Exception('Mumble Ice connection failed: ' . $e->getMessage());
        }
    }

    /**
     * 获取服务器实例
     * 
     * @param int $serverId 服务器ID，默认1
     * @return mixed Mumble 服务器实例
     * @throws Exception 当服务器不存在时抛出异常
     */
    public function getServer(int $serverId = self::DEFAULT_SERVER_ID)
    {
        if (!$this->isConnected()) {
            throw new Exception('Ice connection not established');
        }
        
        // 如果已经获取过同一服务器，直接返回
        if ($this->server && $this->getCurrentServerId() === $serverId) {
            return $this->server;
        }
        
        try {
            // 获取服务器列表
            $servers = $this->meta->getAllServers();
            
            if (!isset($servers[$serverId])) {
                throw new Exception("Mumble server {$serverId} not found");
            }
            
            $this->server = $servers[$serverId];
            
            // 测试服务器连接
            $this->server->ice_ping();
            
            Log::debug('Successfully connected to Mumble server', [
                'server_id' => $serverId
            ]);
            
            return $this->server;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            
            Log::error('Failed to get Mumble server instance', [
                'server_id' => $serverId,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception("Failed to connect to Mumble server {$serverId}: " . $e->getMessage());
        }
    }
    
    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->meta !== null;
    }
    
    /**
     * 获取当前服务器ID
     */
    private function getCurrentServerId(): ?int
    {
        if (!$this->server) {
            return null;
        }
        
        try {
            return $this->server->id();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 创建用户
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email 邮箱地址（可选）
     * @return array 创建结果
     * @throws Exception 当创建失败时抛出异常
     */
    public function createUser(string $username, string $password, string $email = ''): array
    {
        if (!$this->isConnected()) {
            throw new Exception('Ice connection not established');
        }
        
        // 验证参数
        if (empty($username)) {
            throw new Exception('Username cannot be empty');
        }
        
        if (empty($password)) {
            throw new Exception('Password cannot be empty');
        }
        
        // 限制用户名长度和字符
        if (strlen($username) > 255) {
            throw new Exception('Username too long (max 255 characters)');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $username)) {
            throw new Exception('Username contains invalid characters');
        }
        
        try {
            $server = $this->getServer();
            
            // 检查用户是否已存在
            $existingUsers = $server->getRegisteredUsers($username);
            if (!empty($existingUsers)) {
                // 检查是否有完全匹配的用户名
                foreach ($existingUsers as $userId => $userData) {
                    if (isset($userData['name']) && $userData['name'] === $username) {
                        throw new Exception("User '{$username}' already exists with ID {$userId}");
                    }
                }
            }
            
            // 准备用户数据
            $userInfo = [
                'name' => $username,
                'email' => $email,
                'pw' => $password // Mumble 使用 'pw' 作为密码字段
            ];
            
            // 注册用户
            $userId = $server->registerUser($userInfo);
            
            if ($userId <= 0) {
                throw new Exception('Failed to register user - invalid user ID returned');
            }
            
            // 验证用户是否创建成功
            $createdUser = $server->getRegistration($userId);
            if (empty($createdUser)) {
                throw new Exception('User creation verification failed');
            }
            
            $result = [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'success' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            Log::info('Successfully created Mumble user via Ice', [
                'username' => $username,
                'user_id' => $userId,
                'email' => $email
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            
            Log::error('Failed to create user via Ice', [
                'username' => $username,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('User creation failed: ' . $e->getMessage());
        }
    }

    /**
     * 获取用户信息
     * 
     * @param int $userId 用户ID
     * @return array|null 用户信息数组，如果用户不存在则返回null
     */
    public function getUserInfo(int $userId): ?array
    {
        if (!$this->isConnected()) {
            Log::warning('Ice connection not established when getting user info', ['user_id' => $userId]);
            return null;
        }
        
        try {
            $server = $this->getServer();
            $userInfo = $server->getRegistration($userId);
            
            if (empty($userInfo)) {
                Log::debug('User not found', ['user_id' => $userId]);
                return null;
            }
            
            // 获取用户在线状态
            $onlineUsers = $server->getUsers();
            $isOnline = isset($onlineUsers[$userId]);
            $currentChannel = $isOnline ? ($onlineUsers[$userId]['channel'] ?? 0) : 0;
            
            $result = [
                'id' => $userId,
                'username' => $userInfo['name'] ?? '',
                'email' => $userInfo['email'] ?? '',
                'last_active' => $userInfo['last_active'] ?? null,
                'is_online' => $isOnline,
                'current_channel' => $currentChannel,
                'registration_date' => $userInfo['registration_date'] ?? null
            ];
            
            Log::debug('Successfully retrieved user info via Ice', [
                'user_id' => $userId,
                'username' => $result['username'],
                'is_online' => $isOnline
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            
            Log::error('Failed to get user info via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * 更新用户密码
     * 
     * @param int $userId 用户ID
     * @param string $newPassword 新密码
     * @return bool 更新是否成功
     */
    public function updateUserPassword(int $userId, string $newPassword): bool
    {
        if (!$this->isConnected()) {
            Log::warning('Ice connection not established when updating password', ['user_id' => $userId]);
            return false;
        }
        
        if (empty($newPassword)) {
            Log::warning('Empty password provided for user password update', ['user_id' => $userId]);
            return false;
        }
        
        try {
            $server = $this->getServer();
            
            // 获取现有用户信息
            $userInfo = $server->getRegistration($userId);
            if (empty($userInfo)) {
                throw new Exception("User {$userId} not found");
            }
            
            $originalUsername = $userInfo['name'] ?? 'unknown';
            
            // 更新密码
            $userInfo['pw'] = $newPassword;
            $server->updateRegistration($userId, $userInfo);
            
            // 验证更新是否成功
            $updatedInfo = $server->getRegistration($userId);
            if (empty($updatedInfo)) {
                throw new Exception('Password update verification failed');
            }
            
            Log::info('Successfully updated user password via Ice', [
                'user_id' => $userId,
                'username' => $originalUsername
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            
            Log::error('Failed to update user password via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * 删除用户
     */
    public function deleteUser(int $userId): bool
    {
        try {
            $server = $this->getServer();
            $server->unregisterUser($userId);
            
            logger()->info('Successfully deleted user via Ice', [
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to delete user via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取在线用户列表
     */
    public function getOnlineUsers(): array
    {
        try {
            $server = $this->getServer();
            $users = $server->getUsers();
            
            $onlineUsers = [];
            foreach ($users as $userId => $userData) {
                $onlineUsers[] = [
                    'id' => $userId,
                    'name' => $userData['name'] ?? '',
                    'channel' => $userData['channel'] ?? 0,
                    'mute' => $userData['mute'] ?? false,
                    'deaf' => $userData['deaf'] ?? false,
                    'online_time' => $userData['onlinesecs'] ?? 0
                ];
            }
            
            return $onlineUsers;
            
        } catch (\Exception $e) {
            logger()->error('Failed to get online users via Ice', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 创建频道
     */
    public function createChannel(string $name, int $parentId = 0, string $description = ''): ?array
    {
        try {
            $server = $this->getServer();
            
            // 创建频道信息
            $channelInfo = [
                'name' => $name,
                'parent' => $parentId,
                'description' => $description,
                'temporary' => false,
                'position' => 0
            ];
            
            $channelId = $server->addChannel($channelInfo);
            
            if ($channelId <= 0) {
                throw new \Exception("Failed to create channel");
            }
            
            logger()->info('Successfully created channel via Ice', [
                'name' => $name,
                'channel_id' => $channelId
            ]);
            
            return [
                'id' => $channelId,
                'name' => $name,
                'parent_id' => $parentId,
                'description' => $description
            ];
            
        } catch (\Exception $e) {
            logger()->error('Failed to create channel via Ice', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 获取频道列表
     */
    public function getChannels(): array
    {
        try {
            $server = $this->getServer();
            $channels = $server->getChannels();
            
            $channelList = [];
            foreach ($channels as $channelId => $channelData) {
                $channelList[] = [
                    'id' => $channelId,
                    'name' => $channelData['name'] ?? '',
                    'parent_id' => $channelData['parent'] ?? 0,
                    'description' => $channelData['description'] ?? '',
                    'temporary' => $channelData['temporary'] ?? false,
                    'position' => $channelData['position'] ?? 0
                ];
            }
            
            return $channelList;
            
        } catch (\Exception $e) {
            logger()->error('Failed to get channels via Ice', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 移动用户到频道
     */
    public function moveUserToChannel(int $userId, int $channelId): bool
    {
        try {
            $server = $this->getServer();
            
            // 获取用户状态
            $users = $server->getUsers();
            if (!isset($users[$userId])) {
                throw new \Exception("User {$userId} is not online");
            }
            
            // 移动用户
            $userState = $users[$userId];
            $userState['channel'] = $channelId;
            $server->setState($userState);
            
            logger()->info('Successfully moved user to channel via Ice', [
                'user_id' => $userId,
                'channel_id' => $channelId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to move user to channel via Ice', [
                'user_id' => $userId,
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送消息给用户
     */
    public function sendMessageToUser(int $userId, string $message): bool
    {
        try {
            $server = $this->getServer();
            $server->sendMessage($userId, $message);
            
            logger()->info('Successfully sent message to user via Ice', [
                'user_id' => $userId,
                'message_length' => strlen($message)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to send message to user via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 踢出用户
     */
    public function kickUser(int $userId, string $reason = ''): bool
    {
        try {
            $server = $this->getServer();
            $server->kickUser($userId, $reason);
            
            logger()->info('Successfully kicked user via Ice', [
                'user_id' => $userId,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to kick user via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 关闭连接
     */
    public function __destruct()
    {
        $this->disconnect();
    }
    
    /**
     * 手动断开连接
     */
    public function disconnect(): void
    {
        if ($this->communicator) {
            try {
                $this->communicator->destroy();
                Log::debug('Ice communicator destroyed successfully');
            } catch (Exception $e) {
                Log::warning('Error while destroying Ice communicator', [
                    'error' => $e->getMessage()
                ]);
            } finally {
                $this->communicator = null;
                $this->meta = null;
                $this->server = null;
                $this->connected = false;
            }
        }
    }

    /**
     * 测试连接
     * 
     * @return bool 连接是否正常
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->isConnected()) {
                Log::debug('Ice connection test failed: not connected');
                return false;
            }
            
            // 测试 Meta 服务连接
            $this->meta->ice_ping();
            
            // 测试获取服务器列表
            $servers = $this->meta->getAllServers();
            
            // 测试默认服务器连接
            if (!empty($servers)) {
                $server = reset($servers); // 获取第一个服务器
                $server->ice_ping();
            }
            
            Log::info('Ice connection test successful', [
                'servers_count' => count($servers)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            
            Log::error('Ice connection test failed', [
                'error' => $e->getMessage(),
                'connected' => $this->connected
            ]);
            
            return false;
        }
    }
    
    /**
     * 获取最后一次错误信息
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
    
    /**
     * 获取服务器信息
     */
    public function getServerInfo(int $serverId = self::DEFAULT_SERVER_ID): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }
        
        try {
            $server = $this->getServer($serverId);
            
            // 获取服务器配置
            $conf = $server->getAllConf();
            $users = $server->getUsers();
            $channels = $server->getChannels();
            
            return [
                'id' => $serverId,
                'name' => $conf['registername'] ?? "Mumble Server {$serverId}",
                'port' => $conf['port'] ?? 64738,
                'users_online' => count($users),
                'channels_count' => count($channels),
                'max_users' => $conf['users'] ?? 100,
                'version' => $conf['version'] ?? 'unknown'
            ];
            
        } catch (Exception $e) {
            Log::error('Failed to get server info', [
                'server_id' => $serverId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}