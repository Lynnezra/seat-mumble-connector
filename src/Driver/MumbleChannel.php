<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Driver;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Mumble频道实现
 */
class MumbleChannel implements ISet
{
    private $id;
    private $name;
    private $description;
    private $parent_id;
    private $position;
    private $temporary;
    private $max_users;
    private $members;

    public function __construct(array $data)
    {
        $this->id = (string) $data['id'];
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->parent_id = $data['parent_id'] ?? 0;
        $this->position = $data['position'] ?? 0;
        $this->temporary = $data['temporary'] ?? false;
        $this->max_users = $data['max_users'] ?? 0;
        $this->members = collect();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $this->loadMembers();
        }
        
        return $this->members->all();
    }

    public function addMember(IUser $user): void
    {
        try {
            if ($user instanceof MumbleUser) {
                $success = $user->moveToChannel($this);
                if ($success) {
                    $this->members->put($user->getUniqueId(), $user);
                } else {
                    throw new DriverException("Failed to move user to channel");
                }
            }
        } catch (\Exception $e) {
            throw new DriverException("Failed to add member to channel: " . $e->getMessage());
        }
    }

    public function removeMember(IUser $user): void
    {
        try {
            // 将用户移动到根频道或默认频道
            if ($user instanceof MumbleUser) {
                $client = MumbleClient::getInstance();
                $rootChannel = $this->getRootChannel();
                
                if ($rootChannel && $rootChannel->getId() !== $this->getId()) {
                    $success = $user->moveToChannel($rootChannel);
                    if ($success) {
                        $this->members->forget($user->getUniqueId());
                    } else {
                        throw new DriverException("Failed to move user out of channel");
                    }
                }
            }
        } catch (\Exception $e) {
            throw new DriverException("Failed to remove member from channel: " . $e->getMessage());
        }
    }

    /**
     * 获取频道描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 设置频道描述
     */
    public function setDescription(string $description): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->put("api/channels/{$this->id}", [
                'json' => [
                    'description' => $description
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $this->description = $description;
                return true;
            }
            return false;
            
        } catch (\Exception $e) {
            logger()->error('Failed to update channel description', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取父频道ID
     */
    public function getParentId(): int
    {
        return $this->parent_id;
    }

    /**
     * 检查是否为临时频道
     */
    public function isTemporary(): bool
    {
        return $this->temporary;
    }

    /**
     * 获取最大用户数
     */
    public function getMaxUsers(): int
    {
        return $this->max_users;
    }

    /**
     * 设置最大用户数
     */
    public function setMaxUsers(int $maxUsers): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->put("api/channels/{$this->id}", [
                'json' => [
                    'max_users' => $maxUsers
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $this->max_users = $maxUsers;
                return true;
            }
            return false;
            
        } catch (\Exception $e) {
            logger()->error('Failed to update channel max users', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取子频道
     */
    public function getSubChannels(): array
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->get("api/channels/{$this->id}/children");
            $childrenData = json_decode($response->getBody(), true);
            
            $children = [];
            foreach ($childrenData as $childData) {
                $children[] = new MumbleChannel($childData);
            }
            
            return $children;
            
        } catch (\Exception $e) {
            logger()->error('Failed to get sub channels', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 创建子频道
     */
    public function createSubChannel(string $name, string $description = ''): ?MumbleChannel
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->post('api/channels', [
                'json' => [
                    'name' => $name,
                    'description' => $description,
                    'parent_id' => $this->id
                ]
            ]);
            
            $channelData = json_decode($response->getBody(), true);
            return new MumbleChannel($channelData);
            
        } catch (\Exception $e) {
            logger()->error('Failed to create sub channel', [
                'parent_id' => $this->id,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 删除频道
     */
    public function delete(): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->delete("api/channels/{$this->id}");
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to delete channel', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 发送频道消息
     */
    public function sendMessage(string $message): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->post("api/channels/{$this->id}/message", [
                'json' => ['message' => $message]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to send channel message', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 设置频道权限
     */
    public function setPermissions(array $permissions): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->put("api/channels/{$this->id}/permissions", [
                'json' => $permissions
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to set channel permissions', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 从服务器加载频道成员
     */
    private function loadMembers(): void
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->get("api/channels/{$this->id}/users");
            $usersData = json_decode($response->getBody(), true);
            
            foreach ($usersData as $userData) {
                $user = new MumbleUser($userData);
                $this->members->put($user->getUniqueId(), $user);
            }
            
        } catch (\Exception $e) {
            logger()->error('Failed to load channel members', [
                'channel_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取根频道
     */
    private function getRootChannel(): ?MumbleChannel
    {
        try {
            $client = MumbleClient::getInstance();
            return $client->getSet('0'); // 假设根频道ID为0
        } catch (\Exception $e) {
            return null;
        }
    }
}