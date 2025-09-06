<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Models\User;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\MumbleIceService;

/**
 * Mumble客户端实现
 * 
 * 支持通过Mumble的Ice接口或REST API进行用户和频道管理
 */
class MumbleClient implements IClient
{
    private static $instance;
    private $httpClient;
    private $iceService;
    private $users;
    private $channels;
    private $settings;

    private function __construct()
    {
        $this->users = collect();
        $this->channels = collect();
        $this->initializeSettings();
        $this->initializeHttpClient();
        $this->initializeIceService();
    }

    public static function getInstance(): IClient
    {
        if (!isset(self::$instance)) {
            self::$instance = new MumbleClient();
        }
        return self::$instance;
    }

    /**
     * 获取所有用户
     */
    public function getUsers(): array
    {
        if (!$this->users->isEmpty()) {
            return $this->users->all();
        }

        try {
            // 从数据库获取已注册的Mumble用户
            $connectorUsers = User::where('connector_type', 'mumble')->get();
            
            foreach ($connectorUsers as $user) {
                $mumbleUser = new MumbleUser($user);
                $this->users->put($mumbleUser->getUniqueId(), $mumbleUser);
            }

            // 从Mumble服务器同步用户状态
            $this->syncUsersFromServer();

        } catch (\Exception $e) {
            logger()->error('Failed to fetch Mumble users', ['error' => $e->getMessage()]);
        }

        return $this->users->all();
    }

    /**
     * 获取所有频道/组
     */
    public function getSets(): array
    {
        if (!$this->channels->isEmpty()) {
            return $this->channels->all();
        }

        try {
            // 从Mumble服务器获取频道列表
            $channels = $this->fetchChannelsFromServer();
            
            foreach ($channels as $channelData) {
                $channel = new MumbleChannel($channelData);
                $this->channels->put($channel->getId(), $channel);
            }

        } catch (\Exception $e) {
            logger()->error('Failed to fetch Mumble channels', ['error' => $e->getMessage()]);
        }

        return $this->channels->all();
    }

    /**
     * 根据ID获取用户
     */
    public function getUser(string $id): ?IUser
    {
        $user = $this->users->get($id);

        if (!is_null($user)) {
            return $user;
        }

        // 从数据库查找
        $connectorUser = User::where('connector_type', 'mumble')
            ->where('connector_id', $id)
            ->first();

        if (!is_null($connectorUser)) {
            $mumbleUser = new MumbleUser($connectorUser);
            $this->users->put($mumbleUser->getUniqueId(), $mumbleUser);
            return $mumbleUser;
        }

        return null;
    }

    /**
     * 根据ID获取频道
     */
    public function getSet(string $id): ?ISet
    {
        $channel = $this->channels->get($id);

        if (!is_null($channel)) {
            return $channel;
        }

        try {
            $channelData = $this->fetchChannelFromServer($id);
            if ($channelData) {
                $channel = new MumbleChannel($channelData);
                $this->channels->put($channel->getId(), $channel);
                return $channel;
            }
        } catch (\Exception $e) {
            logger()->error('Failed to fetch Mumble channel', ['id' => $id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * 初始化设置
     */
    private function initializeSettings(): void
    {
        try {
            $this->settings = setting('seat-connector.drivers.mumble', true);
        } catch (SettingException $e) {
            // 如果没有设置，使用默认配置
            $this->settings = (object) [
                'mumble_server_host' => 'localhost',
                'mumble_server_port' => 64738,
                'allow_user_registration' => true
            ];
            
            logger()->warning('Mumble connector settings not found, using defaults', [
                'error' => $e->getMessage()
            ]);
        }

        if (is_null($this->settings)) {
            // 使用默认配置
            $this->settings = (object) [
                'mumble_server_host' => 'localhost',
                'mumble_server_port' => 64738,
                'allow_user_registration' => true
            ];
            
            logger()->info('Using default Mumble connector settings');
        }
        
        // 确保设置是对象
        if (!is_object($this->settings)) {
            throw new DriverSettingsException('Invalid Mumble driver configuration format.');
        }
        
        // 检查基本设置（但不强制要求）
        if (!property_exists($this->settings, 'mumble_server_host')) {
            $this->settings->mumble_server_host = 'localhost';
            logger()->info('Using default Mumble server host: localhost');
        }
        
        if (!property_exists($this->settings, 'mumble_server_port')) {
            $this->settings->mumble_server_port = 64738;
            logger()->info('Using default Mumble server port: 64738');
        }
    }

    /**
     * 初始化HTTP客户端
     */
    private function initializeHttpClient(): void
    {
        $host = $this->settings->mumble_server_host ?? 'localhost';
        $port = $this->settings->mumble_server_port ?? 64738;
        
        $this->httpClient = new Client([
            'base_uri' => "http://{$host}:{$port}/",
            'timeout' => 10,
            'verify' => false,
            'http_errors' => false, // 不抛出HTTP错误异常
        ]);
        
        logger()->debug('Initialized Mumble HTTP client', [
            'base_uri' => "http://{$host}:{$port}/"
        ]);
    }

    /**
     * 从Mumble服务器同步用户状态
     */
    private function syncUsersFromServer(): void
    {
        try {
            // 这里实现与Mumble服务器的通信逻辑
            // 可以使用Ice接口或REST API
            $response = $this->httpClient->get('api/users');
            $serverUsers = json_decode($response->getBody(), true);

            foreach ($serverUsers as $userData) {
                $existingUser = $this->users->first(function ($user) use ($userData) {
                    return $user->getClientId() == $userData['id'];
                });

                if ($existingUser) {
                    // 更新用户状态
                    $existingUser->updateFromServerData($userData);
                }
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to sync users from Mumble server', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 从Mumble服务器获取频道列表
     */
    private function fetchChannelsFromServer(): array
    {
        try {
            $response = $this->httpClient->get('api/channels');
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            logger()->error('Failed to fetch channels from Mumble server', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 从Mumble服务器获取单个频道
     */
    private function fetchChannelFromServer(string $id): ?array
    {
        try {
            $response = $this->httpClient->get("api/channels/{$id}");
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            logger()->error('Failed to fetch channel from Mumble server', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 创建Mumble用户
     * 注意：这里需要根据实际的Mumble服务器配置来实现
     * 可能的实现方式：
     * 1. 使用Ice接口
     * 2. 直接操作Mumble数据库
     * 3. 使用自定义的Mumble管理API
     */
    public function createUser(string $username, string $password, array $groups = []): ?IUser
    {
        try {
            // 方法1：如果你有自定义的Mumble REST API
            if ($this->hasRestApi()) {
                return $this->createUserViaRestApi($username, $password, $groups);
            }
            
            // 方法2：使用Ice接口（推荐）
            if ($this->hasIceInterface()) {
                return $this->createUserViaIce($username, $password, $groups);
            }
            
            // 方法3：直接在SeAT数据库中记录（最简单的实现）
            return $this->createUserRecord($username, $password, $groups);
            
        } catch (\Exception $e) {
            logger()->error('Failed to create Mumble user', ['username' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 检查是否有REST API可用
     */
    private function hasRestApi(): bool
    {
        try {
            $response = $this->httpClient->get('api/status', ['timeout' => 5]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 初始化Ice服务
     */
    private function initializeIceService(): void
    {
        try {
            // 检查是否配置了Ice接口
            if ($this->hasIceInterface()) {
                $this->iceService = new MumbleIceService($this->settings);
                logger()->info('Ice service initialized successfully', [
                    'host' => $this->settings->mumble_ice_host,
                    'port' => $this->settings->mumble_ice_port
                ]);
            } else {
                logger()->debug('Ice interface not configured, skipping Ice service initialization', [
                    'ice_extension_loaded' => extension_loaded('ice'),
                    'ice_host_set' => property_exists($this->settings, 'mumble_ice_host'),
                    'ice_port_set' => property_exists($this->settings, 'mumble_ice_port')
                ]);
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to initialize Ice service', [
                'error' => $e->getMessage(),
                'note' => 'Falling back to other connection methods'
            ]);
            $this->iceService = null;
        }
    }
    
    /**
     * 通过REST API创建用户
     */
    private function createUserViaRestApi(string $username, string $password, array $groups): ?IUser
    {
        $response = $this->httpClient->post('api/users', [
            'json' => [
                'username' => $username,
                'password' => $password,
                'groups' => $groups
            ]
        ]);
        
        $userData = json_decode($response->getBody(), true);
        return new MumbleUser($userData);
    }
    /**
     * 检查是否有Ice接口可用
     */
    private function hasIceInterface(): bool
    {
        // 检查Ice配置是否存在且Ice扩展可用
        return extension_loaded('ice') &&
               property_exists($this->settings, 'mumble_ice_host') && 
               property_exists($this->settings, 'mumble_ice_port') &&
               !empty($this->settings->mumble_ice_host) &&
               !empty($this->settings->mumble_ice_port);
    }
    
    /**
     * 通过Ice接口创建用户
     */
    private function createUserViaIce(string $username, string $password, array $groups): ?IUser
    {
        if (!$this->iceService) {
            throw new \Exception('Ice service not available');
        }
        
        try {
            // 生成随机邮箱（Mumble需要邮箱字段）
            $email = $username . '@seat-mumble-connector.local';
            
            $result = $this->iceService->createUser($username, $password, $email);
            
            if ($result['success']) {
                // 创建用户数据对象
                $userData = [
                    'id' => $result['id'],
                    'username' => $result['username'],
                    'email' => $result['email'] ?? $email,
                    'online' => false,
                    'channel_id' => 0,
                    'mumble_user_id' => $result['id'],
                    'created_via_ice' => true
                ];
                
                logger()->info('Successfully created user via Ice interface', [
                    'username' => $username,
                    'mumble_user_id' => $result['id'],
                    'email' => $email
                ]);
                
                // 创建并缓存用户对象
                $mumbleUser = new MumbleUser($userData);
                $this->users->put($mumbleUser->getUniqueId(), $mumbleUser);
                
                return $mumbleUser;
            }
            
            throw new \Exception('Ice user creation returned false');
            
        } catch (\Exception $e) {
            logger()->error('Failed to create user via Ice interface', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            
            // 如果Ice创建失败，回退到简单记录模式
            logger()->info('Falling back to simple record creation for user', ['username' => $username]);
            return $this->createUserRecord($username, $password, $groups);
        }
    }
    
    /**
     * 创建用户记录（最简单的实现）
     */
    private function createUserRecord(string $username, string $password, array $groups): ?IUser
    {
        // 生成一个唯一的连接器ID
        $connector_id = 'mumble_' . md5($username . time());
        
        // 创建用户数据
        $userData = [
            'id' => $connector_id,
            'username' => $username,
            'online' => false,
            'channel_id' => 0,
            'last_seen' => null
        ];
        
        logger()->info('Created Mumble user record', [
            'username' => $username,
            'connector_id' => $connector_id,
            'note' => 'User created in SeAT database. Manual Mumble server setup may be required.'
        ]);
        
        return new MumbleUser($userData);
    }

    /**
     * 创建Mumble频道
     */
    public function createChannel(string $name, string $description = '', int $parentId = 0): ?ISet
    {
        try {
            $response = $this->httpClient->post('api/channels', [
                'json' => [
                    'name' => $name,
                    'description' => $description,
                    'parent_id' => $parentId
                ]
            ]);

            $channelData = json_decode($response->getBody(), true);
            return new MumbleChannel($channelData);

        } catch (\Exception $e) {
            logger()->error('Failed to create Mumble channel', ['name' => $name, 'error' => $e->getMessage()]);
            return null;
        }
    }
}