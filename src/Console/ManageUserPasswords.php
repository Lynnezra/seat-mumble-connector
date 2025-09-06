<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Lynnezra\Seat\Connector\Drivers\Mumble\Services\MumbleAuthenticationService;
use Lynnezra\Seat\Connector\Drivers\Mumble\Models\MumbleUserAuth;
use Warlof\Seat\Connector\Models\User;

/**
 * Mumble 用户密码管理命令
 * 
 * 提供设置、修改、删除用户个人密码的功能
 */
class ManageUserPasswords extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'seat-mumble-connector:manage-passwords 
                            {action : 操作类型 (set/remove/list/test)}
                            {--username= : Mumble 用户名}
                            {--password= : 新密码}
                            {--seat-user-id= : SeAT 用户ID}
                            {--all : 应用到所有用户}';

    /**
     * 命令描述
     */
    protected $description = 'Manage Mumble user personal passwords for custom authentication';

    private $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new MumbleAuthenticationService();
    }

    /**
     * 执行命令
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'set':
                return $this->setPassword();
            case 'remove':
                return $this->removePassword();
            case 'list':
                return $this->listUsers();
            case 'test':
                return $this->testAuthentication();
            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: set, remove, list, test');
                return 1;
        }
    }

    /**
     * 设置用户密码
     */
    private function setPassword(): int
    {
        $username = $this->option('username');
        $password = $this->option('password');
        $seatUserId = $this->option('seat-user-id');

        if (!$username) {
            $username = $this->ask('Enter Mumble username');
        }

        if (!$password) {
            $password = $this->secret('Enter new password');
        }

        if (!$seatUserId) {
            // 尝试从 connector 表中找到对应的 SeAT 用户ID
            $connectorUser = User::where('connector_type', 'mumble')
                                ->where('connector_name', 'like', "%{$username}%")
                                ->first();
            
            if ($connectorUser) {
                $seatUserId = $connectorUser->user_id;
                $this->info("Found SeAT user ID: {$seatUserId} for username: {$username}");
            } else {
                $seatUserId = $this->ask('Enter SeAT user ID');
            }
        }

        if (!$username || !$password || !$seatUserId) {
            $this->error('Username, password, and SeAT user ID are required');
            return 1;
        }

        if ($this->authService->setUserPassword($username, $password, (int) $seatUserId)) {
            $this->info("✓ Password set successfully for user: {$username}");
            
            // 显示认证测试
            $this->info('Testing authentication...');
            $result = $this->authService->authenticateUser($username, $password);
            
            if ($result['success']) {
                $this->info("✓ Authentication test passed ({$result['auth_type']})");
            } else {
                $this->warn("✗ Authentication test failed: {$result['message']}");
            }
            
            return 0;
        } else {
            $this->error("✗ Failed to set password for user: {$username}");
            return 1;
        }
    }

    /**
     * 移除用户密码
     */
    private function removePassword(): int
    {
        $username = $this->option('username');

        if (!$username) {
            $username = $this->ask('Enter Mumble username');
        }

        if (!$username) {
            $this->error('Username is required');
            return 1;
        }

        if (!$this->authService->hasPersonalPassword($username)) {
            $this->warn("User {$username} does not have a personal password set");
            return 0;
        }

        if ($this->confirm("Are you sure you want to remove the personal password for {$username}?")) {
            if ($this->authService->removeUserPassword($username)) {
                $this->info("✓ Password removed successfully for user: {$username}");
                return 0;
            } else {
                $this->error("✗ Failed to remove password for user: {$username}");
                return 1;
            }
        }

        $this->info('Operation cancelled');
        return 0;
    }

    /**
     * 列出所有设置了个人密码的用户
     */
    private function listUsers(): int
    {
        $users = $this->authService->getUsersWithPersonalPasswords();

        if (empty($users)) {
            $this->info('No users have personal passwords set');
            return 0;
        }

        $this->info('Users with personal passwords:');
        $this->info('');

        $headers = ['Username', 'SeAT User ID', 'Display Name', 'Status', 'Last Login'];
        $rows = [];

        foreach ($users as $user) {
            $displayName = $user['seat_user']['main_character']['name'] ?? 'N/A';
            $lastLogin = $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never';
            
            $rows[] = [
                $user['mumble_username'],
                $user['seat_user_id'],
                $displayName,
                $user['enabled'] ? '✓ Enabled' : '✗ Disabled',
                $lastLogin
            ];
        }

        $this->table($headers, $rows);
        $this->info('');
        $this->info('Total users: ' . count($users));

        return 0;
    }

    /**
     * 测试用户认证
     */
    private function testAuthentication(): int
    {
        $username = $this->option('username');
        $password = $this->option('password');

        if (!$username) {
            $username = $this->ask('Enter Mumble username');
        }

        if (!$password) {
            $password = $this->secret('Enter password to test');
        }

        if (!$username || !$password) {
            $this->error('Username and password are required');
            return 1;
        }

        $this->info("Testing authentication for user: {$username}");
        $this->info('');

        $result = $this->authService->authenticateUser($username, $password);

        if ($result['success']) {
            $this->info("✓ Authentication successful!");
            $this->info("Auth Type: {$result['auth_type']}");
            $this->info("Authenticated: " . ($result['authenticated'] ? 'Yes' : 'No'));
            
            if (isset($result['auto_auth']) && $result['auto_auth']) {
                $this->info("Auto Auth: Yes (user automatically granted auth permissions)");
            }
            
            $this->info("Message: {$result['message']}");
        } else {
            $this->error("✗ Authentication failed!");
            $this->error("Auth Type: {$result['auth_type']}");
            $this->error("Message: {$result['message']}");
        }

        return $result['success'] ? 0 : 1;
    }
}