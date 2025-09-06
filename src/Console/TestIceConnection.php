<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\MumbleIceService;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\IceValidator;

/**
 * Ice 接口测试命令
 * 
 * 提供完整的 Ice 接口测试和诊断功能
 */
class TestIceConnection extends Command
{
    protected $signature = 'mumble:test-ice 
                            {--detailed : 显示详细的诊断信息}
                            {--config-only : 仅检查配置，不尝试连接}
                            {--report : 生成完整的配置报告}
                            {--fix-permissions : 尝试修复权限问题}';

    protected $description = 'Test Mumble Ice interface connection and configuration';

    public function handle(): int
    {
        $this->info('=== Mumble Ice Connection Test ===');
        $this->newLine();
        
        // 1. 环境检查
        $this->info('1. Checking Ice environment...');
        if (!$this->checkEnvironment()) {
            return 1;
        }
        
        // 2. 配置检查
        $this->info('2. Loading and validating configuration...');
        $settings = $this->loadSettings();
        if (!$settings || !$this->validateConfiguration($settings)) {
            return 1;
        }
        
        // 3. 连接测试（除非只检查配置）
        if (!$this->option('config-only')) {
            $this->info('3. Testing Ice connection...');
            if (!$this->testConnection($settings)) {
                return 1;
            }
        }
        
        // 4. 生成报告（如果请求）
        if ($this->option('report')) {
            $this->generateReport($settings);
        }
        
        $this->newLine();
        $this->info('✅ All checks passed successfully!');
        return 0;
    }
    
    /**
     * 检查环境
     */
    private function checkEnvironment(): bool
    {
        $validator = IceValidator::validateEnvironment();
        
        // 显示结果
        if ($validator['ice_extension']['status']) {
            $this->info('  ✅ PHP Ice extension is loaded');
            if ($validator['ice_version']['version']) {
                $this->line('    Version: ' . $validator['ice_version']['version']);
            }
        } else {
            $this->error('  ❌ PHP Ice extension not found');
            $this->line('    Install: sudo apt-get install php-zeroc-ice');
        }
        
        if ($validator['php_version']['status']) {
            $this->info('  ✅ PHP version compatible: ' . $validator['php_version']['current_version']);
        } else {
            $this->error('  ❌ ' . $validator['php_version']['message']);
        }
        
        if ($validator['required_classes']['status']) {
            $this->info('  ✅ All required Ice classes available');
        } else {
            $this->error('  ❌ Missing Ice classes: ' . implode(', ', $validator['required_classes']['missing_classes']));
        }
        
        if (!$validator['overall_status']) {
            $this->newLine();
            $this->error('❌ Environment check failed. Please fix the issues above.');
            return false;
        }
        
        return true;
    }
    
    /**
     * 加载设置
     */
    private function loadSettings()
    {
        try {
            $settings = setting('seat-connector.drivers.mumble', true);
            
            if (!$settings) {
                $this->error('  ❌ Mumble connector settings not found');
                $this->line('    Please configure the Mumble connector in SeAT settings');
                return null;
            }
            
            if (!is_object($settings)) {
                $this->error('  ❌ Invalid settings format');
                return null;
            }
            
            $this->info('  ✅ Settings loaded successfully');
            return $settings;
            
        } catch (\Exception $e) {
            $this->error('  ❌ Failed to load settings: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 验证配置
     */
    private function validateConfiguration($settings): bool
    {
        $validator = IceValidator::validateConfiguration($settings);
        
        // 显示配置检查结果
        if ($validator['host_config']['status']) {
            $this->info('  ✅ Host: ' . $validator['host_config']['host']);
        } else {
            $this->error('  ❌ ' . $validator['host_config']['message']);
        }
        
        if ($validator['port_config']['status']) {
            $this->info('  ✅ Port: ' . $validator['port_config']['port']);
            if (isset($validator['port_config']['warning'])) {
                $this->warn('    ' . $validator['port_config']['warning']);
            }
        } else {
            $this->error('  ❌ ' . $validator['port_config']['message']);
        }
        
        if ($validator['secret_config']['status']) {
            if (isset($validator['secret_config']['secret_length'])) {
                $this->info('  ✅ Secret: configured (' . $validator['secret_config']['secret_length'] . ' chars)');
            } else {
                $this->warn('  ⚠️  Secret: not configured (authentication disabled)');
            }
            if (isset($validator['secret_config']['warning'])) {
                $this->warn('    ' . $validator['secret_config']['warning']);
            }
        } else {
            $this->error('  ❌ ' . $validator['secret_config']['message']);
        }
        
        if ($validator['timeout_config']['status']) {
            $this->info('  ✅ Timeout: ' . $validator['timeout_config']['timeout'] . 's');
            if (isset($validator['timeout_config']['warning'])) {
                $this->warn('    ' . $validator['timeout_config']['warning']);
            }
        } else {
            $this->error('  ❌ ' . $validator['timeout_config']['message']);
        }
        
        // 连接性测试
        if ($validator['connectivity']['status']) {
            $this->info('  ✅ TCP connectivity test passed');
        } else {
            $this->error('  ❌ ' . $validator['connectivity']['message']);
        }
        
        if (!$validator['overall_status']) {
            $this->newLine();
            $this->error('❌ Configuration validation failed.');
            return false;
        }
        
        return true;
    }
    
    /**
     * 测试连接
     */
    private function testConnection($settings): bool
    {
        try {
            $this->line('  Initializing Ice service...');
            $iceService = new MumbleIceService($settings);
            
            $this->line('  Testing Ice connection...');
            if (!$iceService->testConnection()) {
                $this->error('  ❌ Ice connection test failed');
                if ($iceService->getLastError()) {
                    $this->line('    Error: ' . $iceService->getLastError());
                }
                return false;
            }
            
            $this->info('  ✅ Ice connection successful');
            
            // 如果请求详细信息，执行更多测试
            if ($this->option('detailed')) {
                return $this->performDetailedTests($iceService);
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error('  ❌ Connection test failed: ' . $e->getMessage());
            
            $this->newLine();
            $this->info('🔧 Troubleshooting suggestions:');
            $this->line('  1. Ensure Mumble server is running');
            $this->line('  2. Check Ice interface is enabled in murmur.ini');
            $this->line('  3. Verify firewall allows Ice port');
            $this->line('  4. Check Ice secret configuration');
            
            return false;
        }
    }
    
    /**
     * 执行详细测试
     */
    private function performDetailedTests(MumbleIceService $iceService): bool
    {
        $this->newLine();
        $this->info('  Performing detailed tests...');
        
        try {
            // 测试获取服务器信息
            $this->line('    Getting server info...');
            $serverInfo = $iceService->getServerInfo();
            if ($serverInfo) {
                $this->info('    ✅ Server: ' . $serverInfo['name']);
                $this->line('      Port: ' . $serverInfo['port']);
                $this->line('      Users online: ' . $serverInfo['users_online'] . '/' . $serverInfo['max_users']);
                $this->line('      Channels: ' . $serverInfo['channels_count']);
            } else {
                $this->warn('    ⚠️  Could not retrieve server info');
            }
            
            // 测试获取在线用户
            $this->line('    Getting online users...');
            $onlineUsers = $iceService->getOnlineUsers();
            $this->info('    ✅ Online users: ' . count($onlineUsers));
            
            // 测试获取频道列表
            $this->line('    Getting channels...');
            $channels = $iceService->getChannels();
            $this->info('    ✅ Channels: ' . count($channels));
            
            // 显示一些详细信息
            if (!empty($onlineUsers)) {
                $this->newLine();
                $this->line('  Recent online users:');
                $this->table(
                    ['User ID', 'Name', 'Channel', 'Online Time'],
                    array_map(function($user) {
                        return [
                            $user['id'],
                            $user['name'],
                            $user['channel'],
                            $user['online_time'] . 's'
                        ];
                    }, array_slice($onlineUsers, 0, 5))
                );
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error('    ❌ Detailed test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 生成报告
     */
    private function generateReport($settings): void
    {
        $this->newLine();
        $this->info('Generating configuration report...');
        
        $report = IceValidator::generateReport($settings);
        
        // 保存报告到文件
        $reportPath = storage_path('logs/mumble-ice-report-' . date('Y-m-d-H-i-s') . '.txt');
        file_put_contents($reportPath, $report);
        
        $this->info('Report saved to: ' . $reportPath);
        
        // 在控制台显示简化版本
        $this->newLine();
        $this->line($report);
    }
}