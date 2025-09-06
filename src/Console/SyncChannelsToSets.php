<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient;

/**
 * 同步 Mumble 频道到 seat-connector Sets 表
 * 
 * 这个命令将从 Mumble 服务器获取所有频道信息，
 * 并将它们同步到 seat-connector 的 Sets 表中，
 * 使它们可以在 Access Management 界面中使用。
 */
class SyncChannelsToSets extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'seat-mumble-connector:sync-sets 
                            {--cleanup : 清理不存在的频道记录}
                            {--force : 强制同步，不询问确认}';

    /**
     * 命令描述
     */
    protected $description = 'Sync Mumble channels to seat-connector Sets table for Access Management';

    /**
     * 执行命令
     */
    public function handle()
    {
        $this->info('Starting Mumble channels synchronization to Access Management...');

        try {
            $client = MumbleClient::getInstance();
            
            // 询问确认（除非使用 --force）
            if (!$this->option('force') && !$this->confirm('This will sync all Mumble channels to Access Management. Continue?', true)) {
                $this->info('Operation cancelled.');
                return 0;
            }

            // 同步所有频道
            $this->info('Syncing channels from Mumble server...');
            $syncedCount = $client->syncAllChannelsToSets();
            $this->info("✓ Synced {$syncedCount} channels to Sets table.");

            // 清理孤立记录（如果指定了 --cleanup 选项）
            if ($this->option('cleanup')) {
                $this->info('Cleaning up orphaned channel records...');
                $deletedCount = $client->cleanupOrphanedSets();
                
                if ($deletedCount > 0) {
                    $this->warn("✓ Removed {$deletedCount} orphaned channel records.");
                } else {
                    $this->info('✓ No orphaned records found.');
                }
            }

            $this->info('');
            $this->info('Synchronization completed successfully!');
            $this->info('You can now manage Mumble channel access through:');
            $this->info('SeAT → Connector → Access Management');
            $this->info('');

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to sync Mumble channels: ' . $e->getMessage());
            logger()->error('Mumble channels sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}