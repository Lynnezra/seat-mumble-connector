<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\MumbleIceService;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\IceValidator;

/**
 * Mumble Ice 管理命令
 * 
 * 提供完整的 Mumble Ice 接口管理功能
 */
class ManageIceInterface extends Command
{
    protected $signature = 'mumble:ice 
                            {action : 操作类型: test|validate|info|users|channels|create-user|report}
                            {--user= : 用户名（用于 create-user 操作）}
                            {--password= : 密码（用于 create-user 操作）}
                            {--email= : 邮箱（用于 create-user 操作，可选）}
                            {--detailed : 显示详细信息}
                            {--format=table : 输出格式: table|json|csv}';

    protected $description = 'Manage Mumble Ice interface operations';

    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'test':
                return $this->testConnection();
            case 'validate':
                return $this->validateConfiguration();
            case 'info':
                return $this->showServerInfo();
            case 'users':
                return $this->listUsers();
            case 'channels':
                return $this->listChannels();
            case 'create-user':
                return $this->createUser();
            case 'report':
                return $this->generateReport();
            default:
                $this->error("Unknown action: {$action}");
                $this->showHelp();
                return 1;
        }
    }

    /**
     * 测试连接
     */
    private function testConnection(): int
    {
        $this->info('=== Testing Mumble Ice Connection ===');
        
        try {
            $settings = $this->loadSettings();
            if (!$settings) {
                return 1;
            }

            $iceService = new MumbleIceService($settings);
            
            if ($iceService->testConnection()) {
                $this->info('✅ Connection successful!');
                
                if ($this->option('detailed')) {
                    $serverInfo = $iceService->getServerInfo();
                    if ($serverInfo) {
                        $this->table(
                            ['Property', 'Value'],
                            [
                                ['Server Name', $serverInfo['name']],
                                ['Port', $serverInfo['port']],
                                ['Users Online', $serverInfo['users_online'] . '/' . $serverInfo['max_users']],
                                ['Channels', $serverInfo['channels_count']],
                                ['Version', $serverInfo['version']]
                            ]
                        );
                    }
                }
                
                return 0;
            } else {
                $this->error('❌ Connection failed');
                if ($iceService->getLastError()) {
                    $this->line('Error: ' . $iceService->getLastError());
                }
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 验证配置
     */
    private function validateConfiguration(): int
    {
        $this->info('=== Validating Ice Configuration ===');
        
        $settings = $this->loadSettings();
        if (!$settings) {
            return 1;
        }

        $envCheck = IceValidator::validateEnvironment();
        $configCheck = IceValidator::validateConfiguration($settings);

        // 显示环境检查结果
        $this->line('Environment Check:');
        $this->displayValidationResults($envCheck);

        $this->newLine();

        // 显示配置检查结果
        $this->line('Configuration Check:');
        $this->displayValidationResults($configCheck);

        $overallSuccess = $envCheck['overall_status'] && $configCheck['overall_status'];

        $this->newLine();
        if ($overallSuccess) {
            $this->info('✅ All validations passed');
            return 0;
        } else {
            $this->error('❌ Some validations failed');
            return 1;
        }
    }

    /**
     * 显示服务器信息
     */
    private function showServerInfo(): int
    {
        $this->info('=== Mumble Server Information ===');
        
        try {
            $settings = $this->loadSettings();
            if (!$settings) {
                return 1;
            }

            $iceService = new MumbleIceService($settings);
            $serverInfo = $iceService->getServerInfo();

            if (!$serverInfo) {
                $this->error('❌ Could not retrieve server information');
                return 1;
            }

            switch ($this->option('format')) {
                case 'json':
                    $this->line(json_encode($serverInfo, JSON_PRETTY_PRINT));
                    break;
                case 'csv':
                    $this->line('property,value');
                    foreach ($serverInfo as $key => $value) {
                        $this->line("{$key},{$value}");
                    }
                    break;
                default:
                    $this->table(
                        ['Property', 'Value'],
                        array_map(function($key, $value) {
                            return [ucfirst(str_replace('_', ' ', $key)), $value];
                        }, array_keys($serverInfo), $serverInfo)
                    );
                    break;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 列出用户
     */
    private function listUsers(): int
    {
        $this->info('=== Online Users ===');
        
        try {
            $settings = $this->loadSettings();
            if (!$settings) {
                return 1;
            }

            $iceService = new MumbleIceService($settings);
            $users = $iceService->getOnlineUsers();

            if (empty($users)) {
                $this->info('No users currently online');
                return 0;
            }

            switch ($this->option('format')) {
                case 'json':
                    $this->line(json_encode($users, JSON_PRETTY_PRINT));
                    break;
                case 'csv':
                    $this->line('id,name,channel,online_time,mute,deaf');
                    foreach ($users as $user) {
                        $this->line(sprintf('%d,%s,%d,%d,%s,%s',
                            $user['id'],
                            $user['name'],
                            $user['channel'],
                            $user['online_time'],
                            $user['mute'] ? 'yes' : 'no',
                            $user['deaf'] ? 'yes' : 'no'
                        ));
                    }
                    break;
                default:
                    $this->table(
                        ['ID', 'Name', 'Channel', 'Online Time', 'Mute', 'Deaf'],
                        array_map(function($user) {
                            return [
                                $user['id'],
                                $user['name'],
                                $user['channel'],
                                $user['online_time'] . 's',
                                $user['mute'] ? 'Yes' : 'No',
                                $user['deaf'] ? 'Yes' : 'No'
                            ];
                        }, $users)
                    );
                    break;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 列出频道
     */
    private function listChannels(): int
    {
        $this->info('=== Channels ===');
        
        try {
            $settings = $this->loadSettings();
            if (!$settings) {
                return 1;
            }

            $iceService = new MumbleIceService($settings);
            $channels = $iceService->getChannels();

            if (empty($channels)) {
                $this->info('No channels found');
                return 0;
            }

            switch ($this->option('format')) {
                case 'json':
                    $this->line(json_encode($channels, JSON_PRETTY_PRINT));
                    break;
                case 'csv':
                    $this->line('id,name,parent_id,description,temporary,position');
                    foreach ($channels as $channel) {
                        $this->line(sprintf('%d,"%s",%d,"%s",%s,%d',
                            $channel['id'],
                            str_replace('"', '""', $channel['name']),
                            $channel['parent_id'],
                            str_replace('"', '""', $channel['description']),
                            $channel['temporary'] ? 'yes' : 'no',
                            $channel['position']
                        ));
                    }
                    break;
                default:
                    $this->table(
                        ['ID', 'Name', 'Parent', 'Description', 'Temporary', 'Position'],
                        array_map(function($channel) {
                            return [
                                $channel['id'],
                                $channel['name'],
                                $channel['parent_id'],
                                mb_substr($channel['description'], 0, 50) . (mb_strlen($channel['description']) > 50 ? '...' : ''),
                                $channel['temporary'] ? 'Yes' : 'No',
                                $channel['position']
                            ];
                        }, $channels)
                    );
                    break;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 创建用户
     */
    private function createUser(): int
    {
        $username = $this->option('user');
        $password = $this->option('password');
        $email = $this->option('email') ?: '';

        if (!$username) {
            $username = $this->ask('Username');
        }

        if (!$password) {
            $password = $this->secret('Password');
        }

        $this->info("=== Creating User: {$username} ===");
        
        try {
            $settings = $this->loadSettings();
            if (!$settings) {
                return 1;
            }

            $iceService = new MumbleIceService($settings);
            $result = $iceService->createUser($username, $password, $email);

            $this->info('✅ User created successfully!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['User ID', $result['id']],
                    ['Username', $result['username']],
                    ['Email', $result['email'] ?: 'Not set'],
                    ['Created', $result['created_at']]
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 生成报告
     */
    private function generateReport(): int
    {
        $this->info('=== Generating Ice Configuration Report ===');
        
        $settings = $this->loadSettings();
        if (!$settings) {
            return 1;
        }

        $report = IceValidator::generateReport($settings);
        
        $reportPath = storage_path('logs/mumble-ice-report-' . date('Y-m-d-H-i-s') . '.txt');
        file_put_contents($reportPath, $report);
        
        $this->info('Report saved to: ' . $reportPath);
        
        if ($this->option('detailed')) {
            $this->newLine();
            $this->line($report);
        }
        
        return 0;
    }

    /**
     * 加载设置
     */
    private function loadSettings()
    {
        try {
            $settings = setting('seat-connector.drivers.mumble', true);
            
            if (!$settings) {
                $this->error('❌ Mumble connector settings not found');
                $this->line('Please configure the Mumble connector in SeAT settings');
                return null;
            }
            
            return $settings;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to load settings: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 显示验证结果
     */
    private function displayValidationResults(array $results): void
    {
        foreach ($results as $key => $result) {
            if (in_array($key, ['overall_status', 'errors', 'warnings'])) {
                continue;
            }

            if (is_array($result) && isset($result['status'])) {
                $icon = $result['status'] ? '✅' : '❌';
                $this->line("  {$icon} " . ($result['message'] ?? $key));
                
                if (isset($result['warning'])) {
                    $this->warn("    ⚠️  " . $result['warning']);
                }
            }
        }

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors:');
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        if (!empty($results['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($results['warnings'] as $warning) {
                $this->line("  - {$warning}");
            }
        }
    }

    /**
     * 显示帮助信息
     */
    private function showHelp(): void
    {
        $this->newLine();
        $this->info('Available actions:');
        $this->line('  test         - Test Ice connection');
        $this->line('  validate     - Validate Ice configuration');
        $this->line('  info         - Show server information');
        $this->line('  users        - List online users');
        $this->line('  channels     - List channels');
        $this->line('  create-user  - Create a new user');
        $this->line('  report       - Generate configuration report');
        
        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan mumble:ice test');
        $this->line('  php artisan mumble:ice users --format=json');
        $this->line('  php artisan mumble:ice create-user --user=john --password=secret');
        $this->line('  php artisan mumble:ice info --detailed');
    }
}