<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient;
use Warlof\Seat\Connector\Models\User;
use Warlof\Seat\Connector\Models\Set;

/**
 * 测试和验证 Mumble 连接器与 Access Management 的集成
 */
class TestAccessManagement extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'seat-mumble-connector:test-access-management 
                            {--user-id= : 测试指定用户ID的权限}
                            {--show-sets : 显示所有可用的 Sets}
                            {--dry-run : 仅显示测试结果，不执行实际操作}';

    /**
     * 命令描述
     */
    protected $description = 'Test and validate Mumble connector integration with Access Management';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('Testing Mumble connector Access Management integration...');
        $this->info('');

        try {
            // 测试客户端连接
            $this->testClientConnection();
            
            // 显示所有 Sets（如果请求）
            if ($this->option('show-sets')) {
                $this->showAllSets();
            }
            
            // 测试用户权限（如果指定了用户ID）
            $userId = $this->option('user-id');
            if ($userId) {
                $this->testUserPermissions($userId);
            } else {
                $this->testAllUserPermissions();
            }
            
            $this->info('');
            $this->info('✓ All tests completed successfully!');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            logger()->error('Access Management test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * 测试客户端连接
     */
    private function testClientConnection(): void
    {
        $this->info('Testing Mumble client connection...');
        
        $client = MumbleClient::getInstance();
        
        // 测试获取 Sets
        $sets = $client->getSets();
        $this->info("✓ Retrieved {" . count($sets) . "} channels from Mumble server");
        
        // 测试获取 Users
        $users = $client->getUsers();
        $this->info("✓ Retrieved {" . count($users) . "} users from Mumble server");
        
        $this->info('');
    }

    /**
     * 显示所有可用的 Sets
     */
    private function showAllSets(): void
    {
        $this->info('Available Mumble Sets in Access Management:');
        $this->info('');
        
        $sets = Set::where('connector_type', 'mumble')->get();
        
        if ($sets->isEmpty()) {
            $this->warn('No Mumble sets found in database. Run sync command first:');
            $this->warn('php artisan seat-mumble-connector:sync-sets');
            return;
        }
        
        $headers = ['ID', 'Name', 'Connector ID', 'Public', 'Rules Count'];
        $rows = [];
        
        foreach ($sets as $set) {
            $rulesCount = $set->users()->count() + 
                         $set->roles()->count() + 
                         $set->corporations()->count() + 
                         $set->alliances()->count() + 
                         $set->titles()->count() + 
                         $set->squads()->count();
            
            $rows[] = [
                $set->id,
                $set->name,
                $set->connector_id,
                $set->is_public ? 'Yes' : 'No',
                $rulesCount
            ];
        }
        
        $this->table($headers, $rows);
        $this->info('');
    }

    /**
     * 测试指定用户的权限
     */
    private function testUserPermissions(int $userId): void
    {
        $this->info("Testing permissions for user ID: {$userId}");
        $this->info('');
        
        $user = User::where('connector_type', 'mumble')
                   ->where('user_id', $userId)
                   ->first();
        
        if (!$user) {
            $this->warn("No Mumble user found for SeAT user ID: {$userId}");
            return;
        }
        
        $this->info("Found Mumble user: {$user->connector_name}");
        $this->info("Connector ID: {$user->connector_id}");
        $this->info('');
        
        // 获取用户允许的 Sets
        try {
            $allowedSets = $user->allowedSets();
            $this->info("User has access to {" . count($allowedSets) . "} sets via Access Management");
            
            if (!empty($allowedSets)) {
                $this->info('Allowed channel IDs: ' . implode(', ', $allowedSets));
                
                // 显示详细的频道信息
                $client = MumbleClient::getInstance();
                $this->info('');
                $this->info('Channel details:');
                
                foreach ($allowedSets as $setId) {
                    $channel = $client->getSet($setId);
                    if ($channel) {
                        $this->info("  - {$channel->getName()} (ID: {$setId})");
                    } else {
                        $this->warn("  - Unknown channel (ID: {$setId})");
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to get user permissions: " . $e->getMessage());
        }
        
        $this->info('');
    }

    /**
     * 测试所有用户的权限
     */
    private function testAllUserPermissions(): void
    {
        $this->info('Testing permissions for all Mumble users...');
        $this->info('');
        
        $users = User::where('connector_type', 'mumble')->get();
        
        if ($users->isEmpty()) {
            $this->warn('No Mumble users found in database.');
            return;
        }
        
        $headers = ['SeAT User ID', 'Mumble Username', 'Allowed Sets Count', 'Status'];
        $rows = [];
        
        foreach ($users as $user) {
            try {
                $allowedSets = $user->allowedSets();
                $status = '✓ OK';
            } catch (\Exception $e) {
                $allowedSets = [];
                $status = '✗ Error: ' . $e->getMessage();
            }
            
            $rows[] = [
                $user->user_id,
                $user->connector_name ?: 'N/A',
                count($allowedSets),
                $status
            ];
        }
        
        $this->table($headers, $rows);
        $this->info('');
        
        if (!$this->option('dry-run')) {
            if ($this->confirm('Would you like to run policy sync for all users?', false)) {
                $this->info('Running policy sync...');
                $this->call('seat-connector:apply:policies', ['--driver' => ['mumble']]);
            }
        }
    }
}