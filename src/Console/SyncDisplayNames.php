<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Warlof\Seat\Connector\Models\User;

class SyncDisplayNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mumble:sync-display-names
                            {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Mumble user display names with SeAT character information including corporation ticker';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting to sync Mumble user display names...');

        // 获取所有 Mumble 连接器用户
        $mumbleUsers = User::where('connector_type', 'mumble')
            ->with(['user', 'user.characters', 'user.characters.affiliation'])
            ->get();

        if ($mumbleUsers->isEmpty()) {
            $this->warn('No Mumble users found.');
            return 0;
        }

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($mumbleUsers as $user) {
            try {
                $currentName = $user->connector_name;
                
                // 根据是否有自定义昵称生成格式化名称
                if (!empty($user->nickname)) {
                    $mumbleUser = new \Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleUser($user);
                    $formattedName = $mumbleUser->buildFormattedNameWithNickname($user->nickname);
                } else {
                    $formattedName = $user->buildConnectorNickname();
                }

                if ($currentName === $formattedName) {
                    $this->line("SKIP: User {$user->user_id} - Name already formatted: {$currentName}");
                    $skipped++;
                    continue;
                }

                $this->info("UPDATE: User {$user->user_id}");
                $this->line("  From: {$currentName}");
                $this->line("  To:   {$formattedName}");
                if (!empty($user->nickname)) {
                    $this->line("  Using custom nickname: {$user->nickname}");
                }

                if (!$isDryRun) {
                    $user->connector_name = $formattedName;
                    $user->save();
                }

                $updated++;

            } catch (\Exception $e) {
                $this->error("ERROR: Failed to update user {$user->user_id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        
        if ($isDryRun) {
            $this->info("DRY RUN RESULTS:");
        } else {
            $this->info("SYNC RESULTS:");
        }
        
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");
        $this->line("  Errors:  {$errors}");
        $this->line("  Total:   {$mumbleUsers->count()}");

        if ($isDryRun && $updated > 0) {
            $this->newLine();
            $this->comment('To apply these changes, run the command without --dry-run');
        }

        return 0;
    }
}