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

/**
 * Mumble客户端实现
 * 
 * 支持通过Mumble的Ice接口或REST API进行用户和频道管理
 */
class MumbleClient implements IClient
{
    private static $instance;
    private $httpClient;
    private $users;
    private $channels;
    private $settings;

    private function __construct()
    {
        $this->users = collect();
        $this->channels = collect();
        $this->initializeSettings();
        $this->initializeHttpClient();
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
            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        }

        if (is_null($this->settings) || !is_object($this->settings)) {
            throw new DriverSettingsException('The Mumble driver has not been configured yet.');
        }

        $requiredSettings = ['mumble_server_host', 'mumble_server_port'];
        foreach ($requiredSettings as $setting) {
            if (!property_exists($this->settings, $setting) || empty($this->settings->$setting)) {
                throw new DriverSettingsException("Missing required setting: {$setting}");
            }
        }
    }

    /**
     * 初始化HTTP客户端
     */
    private function initializeHttpClient(): void
    {
        $this->httpClient = new Client([
            'base_uri' => "http://{$this->settings->mumble_server_host}:{$this->settings->mumble_server_port}/",
            'timeout' => 10,
            'verify' => false,
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
     */
    public function createUser(string $username, string $password, array $groups = []): ?IUser
    {
        try {
            $response = $this->httpClient->post('api/users', [
                'json' => [
                    'username' => $username,
                    'password' => $password,
                    'groups' => $groups
                ]
            ]);

            $userData = json_decode($response->getBody(), true);
            return new MumbleUser($userData);

        } catch (\Exception $e) {
            logger()->error('Failed to create Mumble user', ['username' => $username, 'error' => $e->getMessage()]);
            return null;
        }
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