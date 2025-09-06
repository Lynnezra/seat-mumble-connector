<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Driver;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Models\User;

/**
 * Mumble用户实现
 */
class MumbleUser implements IUser
{
    public $user_id;
    public $connector_id;
    public $unique_id;
    public $connector_name;
    public $nickname;
    public $user_model;
    
    // Mumble特有属性
    public $mumble_user_id;
    public $is_online;
    public $channel_id;
    public $last_seen;

    public function __construct($data)
    {
        if ($data instanceof User) {
            // 从数据库模型构造
            $this->user_id = $data->user_id;
            $this->connector_id = $data->connector_id;
            $this->unique_id = $data->unique_id;
            $this->connector_name = $data->connector_name;
            $this->nickname = $data->nickname;
            $this->user_model = $data;
            $this->mumble_user_id = $data->connector_id;
        } elseif (is_array($data)) {
            // 从服务器数据构造
            $this->mumble_user_id = $data['id'];
            $this->connector_id = $data['id'];
            $this->connector_name = $data['username'] ?? '';
            $this->nickname = $data['nickname'] ?? null;
            $this->unique_id = $data['unique_id'] ?? $data['id'];
            $this->is_online = $data['online'] ?? false;
            $this->channel_id = $data['channel_id'] ?? null;
            $this->last_seen = $data['last_seen'] ?? null;
        }
    }

    public function getClientId(): string
    {
        return (string) $this->connector_id;
    }

    public function getUniqueId(): string
    {
        return (string) $this->unique_id;
    }

    public function getName(): string
    {
        // 如果有用户模型，使用格式化的连接器昵称
        if ($this->user_model) {
            // 如果有自定义昵称，使用昵称替换角色名生成格式化名称
            if (!empty($this->user_model->nickname)) {
                return $this->buildFormattedNameWithNickname($this->user_model->nickname);
            }
            
            // 否则使用标准的格式化名称（包含军团标识）
            return $this->user_model->buildConnectorNickname();
        }
        
        // 如果没有用户模型，回退到基本逻辑
        return $this->nickname ?: $this->connector_name;
    }

    public function setName(string $name): bool
    {
        try {
            if ($this->user_model) {
                // 设置昵称而不是connector_name
                return $this->user_model->setNickname($name);
            } else {
                $this->nickname = $name;
                return true;
            }
        } catch (\Exception $e) {
            logger()->error('Failed to set Mumble user name', [
                'user_id' => $this->getUniqueId(),
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSets(): array
    {
        $channels = [];
        
        try {
            // 获取用户所在的频道
            if ($this->channel_id) {
                $client = MumbleClient::getInstance();
                $channel = $client->getSet((string) $this->channel_id);
                if ($channel) {
                    $channels[] = $channel;
                }
            }
            
            // 获取用户有权限的频道
            $authorizedChannels = $this->getAuthorizedChannels();
            $channels = array_merge($channels, $authorizedChannels);
            
        } catch (\Exception $e) {
            logger()->error('Failed to get user channels', [
                'user_id' => $this->getUniqueId(),
                'error' => $e->getMessage()
            ]);
        }

        return array_unique($channels, SORT_REGULAR);
    }

    public function addSet(ISet $channel): void
    {
        try {
            $this->addToChannel($channel);
        } catch (\Exception $e) {
            throw new DriverException("Failed to add user to channel: " . $e->getMessage());
        }
    }

    public function removeSet(ISet $channel): void
    {
        try {
            $this->removeFromChannel($channel);
        } catch (\Exception $e) {
            throw new DriverException("Failed to remove user from channel: " . $e->getMessage());
        }
    }

    /**
     * 更新用户在线状态
     */
    public function updateFromServerData(array $serverData): void
    {
        $this->is_online = $serverData['online'] ?? false;
        $this->channel_id = $serverData['channel_id'] ?? null;
        $this->last_seen = $serverData['last_seen'] ?? null;
    }

    /**
     * 检查用户是否在线
     */
    public function isOnline(): bool
    {
        return $this->is_online ?? false;
    }

    /**
     * 获取用户当前频道ID
     */
    public function getCurrentChannelId(): ?int
    {
        return $this->channel_id;
    }

    /**
     * 移动用户到指定频道
     */
    public function moveToChannel(ISet $channel): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->post("api/users/{$this->mumble_user_id}/move", [
                'json' => ['channel_id' => $channel->getId()]
            ]);
            
            $this->channel_id = (int) $channel->getId();
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to move user to channel', [
                'user_id' => $this->getUniqueId(),
                'channel_id' => $channel->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 给用户发送消息
     */
    public function sendMessage(string $message): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->post("api/users/{$this->mumble_user_id}/message", [
                'json' => ['message' => $message]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to send message to user', [
                'user_id' => $this->getUniqueId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 踢出用户
     */
    public function kick(string $reason = ''): bool
    {
        try {
            $client = MumbleClient::getInstance();
            $response = $client->httpClient->post("api/users/{$this->mumble_user_id}/kick", [
                'json' => ['reason' => $reason]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            logger()->error('Failed to kick user', [
                'user_id' => $this->getUniqueId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取用户有权限的频道
     */
    private function getAuthorizedChannels(): array
    {
        // 这里实现权限逻辑，基于SeAT的角色和权限系统
        // 可以根据用户的军团、联盟、角色等信息决定可访问的频道
        return [];
    }

    /**
     * 将用户添加到频道
     */
    private function addToChannel(ISet $channel): void
    {
        // 实现将用户添加到频道的逻辑
        // 可能需要设置频道权限或组成员关系
    }

    /**
     * 将用户从频道移除
     */
    private function removeFromChannel(ISet $channel): void
    {
        // 实现将用户从频道移除的逻辑
    }

    /**
     * 使用自定义昵称生成格式化的显示名称
     * 格式: [Corporation Ticker] Custom Nickname
     * 
     * @param string $nickname 自定义昵称
     * @return string 格式化的显示名称
     */
    public function buildFormattedNameWithNickname(string $nickname): string
    {
        try {
            // 检查是否启用了 ticker 功能
            if (!setting('seat-connector.ticker', true)) {
                return $nickname;
            }

            // 获取用户的主角色
            $character = $this->user_model->user->main_character;
            if (is_null($character) || is_null($character->name)) {
                $character = $this->user_model->user->characters->first();
            }

            if (is_null($character)) {
                return $nickname;
            }

            // 获取军团和联盟信息
            $corporation = \Seat\Eveapi\Models\Corporation\CorporationInfo::find($character->affiliation->corporation_id);
            $alliance = is_null($character->affiliation->alliance_id) ? null : \Seat\Eveapi\Models\Alliances\Alliance::find($character->affiliation->alliance_id);
            
            // 获取格式设置
            $format = setting('seat-connector.format', true) ?: '[%2$s] %1$s';
            
            $corp_ticker = $corporation->ticker ?? '';
            $alliance_ticker = $alliance->ticker ?? '';
            
            // 使用自定义昵称替换角色名
            return sprintf($format, $nickname, $corp_ticker, $alliance_ticker);
            
        } catch (\Exception $e) {
            logger()->warning('Failed to build formatted name with nickname', [
                'user_id' => $this->user_model->user_id ?? 'unknown',
                'nickname' => $nickname,
                'error' => $e->getMessage()
            ]);
            
            // 如果出错，返回原始昵称
            return $nickname;
        }
    }
}