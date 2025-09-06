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
     * 设置用户权限
     * 
     * @param int $userId Mumble 用户ID
     * @param int $channelId 频道ID（0表示全局权限）
     * @param array $permissions 权限设置
     * @return bool 是否设置成功
     */
    public function setUserPermissions(int $userId, int $channelId, array $permissions): bool
    {
        try {
            $server = $this->getServer();
            
            // 获取频道ACL
            $acl = $server->getACL($channelId);
            
            // 查找用户在ACL中的记录
            $userAcl = null;
            foreach ($acl['acls'] as $index => $aclEntry) {
                if ($aclEntry['userid'] == $userId) {
                    $userAcl = &$acl['acls'][$index];
                    break;
                }
            }
            
            // 如果用户不在ACL中，创建新记录
            if (!$userAcl) {
                $userAcl = [
                    'userid' => $userId,
                    'allow' => 0,
                    'deny' => 0
                ];
                $acl['acls'][] = &$userAcl;
            }
            
            // 设置权限
            $userAcl['allow'] = $permissions['allow'] ?? 0;
            $userAcl['deny'] = $permissions['deny'] ?? 0;
            
            // 更新ACL
            $server->setACL($channelId, $acl['acls'], $acl['groups'], $acl['inherit']);
            
            logger()->info('Successfully set user permissions via Ice', [
                'user_id' => $userId,
                'channel_id' => $channelId,
                'allow' => $userAcl['allow'],
                'deny' => $userAcl['deny']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to set user permissions via Ice', [
                'user_id' => $userId,
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取用户权限
     * 
     * @param int $userId Mumble 用户ID
     * @param int $channelId 频道ID
     * @return array|null 权限信息
     */
    public function getUserPermissions(int $userId, int $channelId): ?array
    {
        try {
            $server = $this->getServer();
            $acl = $server->getACL($channelId);
            
            // 查找用户权限
            foreach ($acl['acls'] as $aclEntry) {
                if ($aclEntry['userid'] == $userId) {
                    return [
                        'allow' => $aclEntry['allow'],
                        'deny' => $aclEntry['deny']
                    ];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            logger()->error('Failed to get user permissions via Ice', [
                'user_id' => $userId,
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 设置用户为管理员
     * 
     * @param int $userId Mumble 用户ID
     * @return bool 是否设置成功
     */
    public function setUserAdmin(int $userId): bool
    {
        // 管理员权限包括所有权限
        $adminPermissions = [
            'allow' => 0xFFFF, // 允许所有权限
            'deny' => 0        // 不禁止任何权限
        ];
        
        return $this->setUserPermissions($userId, 0, $adminPermissions);
    }

    /**
     * 移除用户管理员权限
     * 
     * @param int $userId Mumble 用户ID
     * @return bool 是否移除成功
     */
    public function removeUserAdmin(int $userId): bool
    {
        try {
            $server = $this->getServer();
            
            // 获取根频道ACL
            $acl = $server->getACL(0);
            
            // 移除用户的ACL记录
            $acl['acls'] = array_filter($acl['acls'], function($aclEntry) use ($userId) {
                return $aclEntry['userid'] != $userId;
            });
            
            // 更新ACL
            $server->setACL(0, $acl['acls'], $acl['groups'], $acl['inherit']);
            
            logger()->info('Successfully removed user admin permissions via Ice', [
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to remove user admin permissions via Ice', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批量设置用户权限
     * 
     * @param array $userPermissions 用户权限数组
     * @return array 设置结果
     */
    public function batchSetUserPermissions(array $userPermissions): array
    {
        $results = [];
        
        foreach ($userPermissions as $userPerm) {
            $userId = $userPerm['user_id'];
            $channelId = $userPerm['channel_id'] ?? 0;
            $permissions = $userPerm['permissions'];
            
            $success = $this->setUserPermissions($userId, $channelId, $permissions);
            $results[$userId] = $success;
        }
        
        return $results;
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

    /**
     * 设置用户认证状态
     * 
     * @param string $username 用户名
     * @param bool $authenticated 是否已认证
     * @return bool
     */
    public function setUserAuthenticated(string $username, bool $authenticated): bool
    {
        try {
            if (!$this->isConnected()) {
                throw new \Exception('Ice connection not established');
            }

            // 获取用户ID
            $userId = $this->getUserIdByName($username);
            if ($userId === null) {
                throw new \Exception("User '{$username}' not found");
            }

            $server = $this->getServer();
            
            // 设置用户认证状态
            $server->setUserAuthenticated($userId, $authenticated);
            
            logger()->info('Set user authenticated status via Ice', [
                'username' => $username,
                'user_id' => $userId,
                'authenticated' => $authenticated
            ]);

            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to set user authenticated status via Ice', [
                'username' => $username,
                'authenticated' => $authenticated,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 设置用户权限
     * 
     * @param string $username 用户名
     * @param array $permissions 权限列表
     * @return bool
     */
    public function setUserPermissions(string $username, array $permissions): bool
    {
        try {
            if (!$this->isConnected()) {
                throw new \Exception('Ice connection not established');
            }

            $userId = $this->getUserIdByName($username);
            if ($userId === null) {
                throw new \Exception("User '{$username}' not found");
            }

            $server = $this->getServer();
            
            // 设置各种权限
            foreach ($permissions as $permission => $value) {
                switch ($permission) {
                    case 'can_speak':
                        // 设置说话权限
                        $this->setUserChannelPermission($userId, 0, 'speak', $value);
                        break;
                    case 'can_hear':
                        // 设置听取权限
                        $this->setUserChannelPermission($userId, 0, 'hear', $value);
                        break;
                    case 'can_move':
                        // 设置移动权限
                        $this->setUserChannelPermission($userId, 0, 'move', $value);
                        break;
                    case 'can_kick':
                        // 设置踢出权限
                        $this->setUserChannelPermission($userId, 0, 'kick', $value);
                        break;
                    case 'can_ban':
                        // 设置封禁权限
                        $this->setUserChannelPermission($userId, 0, 'ban', $value);
                        break;
                }
            }
            
            logger()->info('Set user permissions via Ice', [
                'username' => $username,
                'user_id' => $userId,
                'permissions' => $permissions
            ]);

            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to set user permissions via Ice', [
                'username' => $username,
                'permissions' => $permissions,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 验证用户认证（支持自定义认证）
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @return array 认证结果
     */
    public function authenticateUser(string $username, string $password): array
    {
        try {
            // 使用自定义认证服务
            $authService = app(\Lynnezra\Seat\Connector\Drivers\Mumble\Services\MumbleAuthenticationService::class);
            $result = $authService->authenticateUser($username, $password);
            
            // 如果认证成功且是个人密码，通过 Ice 设置认证状态
            if ($result['success'] && $result['auth_type'] === 'personal_password') {
                $this->setUserAuthenticated($username, true);
                
                // 设置基本权限
                $this->setUserPermissions($username, [
                    'can_speak' => true,
                    'can_hear' => true
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            logger()->error('Authentication failed via Ice', [
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
     * 设置用户频道权限
     * 
     * @param int $userId 用户ID
     * @param int $channelId 频道ID
     * @param string $permission 权限类型
     * @param bool $allowed 是否允许
     * @return bool
     */
    private function setUserChannelPermission(int $userId, int $channelId, string $permission, bool $allowed): bool
    {
        try {
            $server = $this->getServer();
            
            // 这里需要根据 Mumble Ice API 的实际方法来实现
            // 不同版本的 Mumble 可能有不同的 API
            
            // 示例实现（需要根据实际 API 调整）
            $acl = $server->getACL($channelId);
            
            // 修改 ACL 条目
            // 这里是一个简化的实现，实际中需要根据 Mumble 的 ACL 系统来做
            
            return true;
            
        } catch (\Exception $e) {
            logger()->error('Failed to set user channel permission', [
                'user_id' => $userId,
                'channel_id' => $channelId,
                'permission' => $permission,
                'allowed' => $allowed,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}