<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Console;

use Illuminate\Console\Command;
use Warlof\Seat\Connector\Models\User;
use Seat\Web\Models\User as SeatUser;
use Lynnezra\Seat\Connector\Drivers\Mumble\Services\PermissionService;
use Lynnezra\Seat\Connector\Drivers\Mumble\Ice\MumbleIceService;

class ManagePermissions extends Command
{
    protected $signature = 'mumble:permissions 
                           {action : 管理操作 (list|add-admin|remove-admin|sync|show|test)}
                           {--user= : 用户标识 (用户ID、用户名或角色名)}
                           {--role= : 权限角色}
                           {--force : 强制执行操作}
                           {--dry-run : 仅显示将要进行的操作}';

    protected $description = 'Manage Mumble user permissions and admin rights';

    private $permissionService;

    public function handle(): int
    {
        $this->permissionService = new PermissionService();
        $action = $this->argument('action');

        switch ($action) {
            case 'list':
                return $this->listPermissions();
            case 'add-admin':
                return $this->addAdmin();
            case 'remove-admin':
                return $this->removeAdmin();
            case 'sync':
                return $this->syncPermissions();
            case 'show':
                return $this->showUserPermissions();
            case 'test':
                return $this->testPermissions();
            default:
                $this->error("未知操作: {$action}");
                $this->showHelp();
                return 1;
        }
    }

    /**
     * 列出当前权限配置
     */
    private function listPermissions(): int
    {
        $this->info('=== Mumble 权限管理 ===');
        $this->newLine();

        // 显示管理员列表
        $this->info('📋 当前管理员:');
        $admins = $this->permissionService->getAdminUsers();
        
        if (empty($admins)) {
            $this->line('   无配置的管理员用户');
        } else {
            foreach ($admins as $admin) {
                $this->line("   - {$admin}");
            }
        }

        $this->newLine();

        // 显示权限配置
        $this->info('⚙️  权限配置:');
        $config = $this->permissionService->getPermissionConfig();
        
        foreach ($config as $role => $permissions) {
            $this->line("   {$role}:");
            foreach ($permissions as $perm => $enabled) {
                $status = $enabled ? '✅' : '❌';
                $this->line("     {$status} {$perm}");
            }
        }

        $this->newLine();

        // 显示注册用户统计
        $this->info('👥 Mumble 用户统计:');
        $totalUsers = User::where('connector_type', 'mumble')->count();
        $this->line("   总用户数: {$totalUsers}");

        return 0;
    }

    /**
     * 添加管理员
     */
    private function addAdmin(): int
    {
        $userIdentifier = $this->option('user');
        
        if (!$userIdentifier) {
            $userIdentifier = $this->ask('请输入用户标识 (用户ID、用户名或角色名)');
        }

        if (!$userIdentifier) {
            $this->error('用户标识不能为空');
            return 1;
        }

        // 验证用户是否存在
        $user = $this->findUser($userIdentifier);
        if (!$user) {
            $this->warn("警告: 未找到用户 '{$userIdentifier}'，但仍将其添加到管理员列表");
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将添加管理员: {$userIdentifier}");
            return 0;
        }

        if ($this->permissionService->addAdminUser($userIdentifier)) {
            $this->info("✅ 成功添加管理员: {$userIdentifier}");
            
            if ($user) {
                $this->line("   用户名: {$user->name}");
                if ($user->main_character) {
                    $this->line("   主角色: {$user->main_character->name}");
                }
            }

            $this->newLine();
            $this->comment('💡 提示: 运行 "php artisan mumble:permissions sync" 来同步权限到 Mumble 服务器');
        } else {
            $this->error("❌ 添加管理员失败: {$userIdentifier}");
            return 1;
        }

        return 0;
    }

    /**
     * 移除管理员
     */
    private function removeAdmin(): int
    {
        $userIdentifier = $this->option('user');
        
        if (!$userIdentifier) {
            // 显示当前管理员列表供选择
            $admins = $this->permissionService->getAdminUsers();
            if (empty($admins)) {
                $this->warn('当前没有配置的管理员');
                return 0;
            }

            $this->info('当前管理员列表:');
            foreach ($admins as $index => $admin) {
                $this->line("  {$index}: {$admin}");
            }

            $userIdentifier = $this->ask('请输入要移除的用户标识');
        }

        if (!$userIdentifier) {
            $this->error('用户标识不能为空');
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 将移除管理员: {$userIdentifier}");
            return 0;
        }

        if ($this->permissionService->removeAdminUser($userIdentifier)) {
            $this->info("✅ 成功移除管理员: {$userIdentifier}");
            $this->newLine();
            $this->comment('💡 提示: 运行 "php artisan mumble:permissions sync" 来同步权限到 Mumble 服务器');
        } else {
            $this->error("❌ 移除管理员失败: {$userIdentifier}");
            return 1;
        }

        return 0;
    }

    /**
     * 同步权限到 Mumble 服务器
     */
    private function syncPermissions(): int
    {
        $this->info('🔄 开始同步权限到 Mumble 服务器...');

        $mumbleUsers = User::where('connector_type', 'mumble')
            ->with(['user', 'user.characters', 'user.characters.affiliation'])
            ->get();

        if ($mumbleUsers->isEmpty()) {
            $this->warn('没有找到 Mumble 用户');
            return 0;
        }

        $updated = 0;
        $errors = 0;

        foreach ($mumbleUsers as $mumbleUser) {
            try {
                $permissions = $this->permissionService->getUserPermissions($mumbleUser);
                
                $this->line("处理用户: {$mumbleUser->connector_name} (角色: {$permissions['role']})");

                if ($this->option('dry-run')) {
                    $this->line("  [DRY RUN] 权限: " . json_encode($permissions));
                    continue;
                }

                // 同步权限到 Mumble 服务器
                if ($this->syncUserPermissionsToMumble($mumbleUser, $permissions)) {
                    $this->line("  ✅ 同步成功");
                } else {
                    $this->line("  ❌ 同步失败");
                    $errors++;
                    continue;
                }
                
                $updated++;

            } catch (\Exception $e) {
                $this->error("处理用户 {$mumbleUser->connector_name} 时出错: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] 权限同步预览完成");
        } else {
            $this->info("✅ 权限同步完成: {$updated} 个用户更新，{$errors} 个错误");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * 显示用户权限
     */
    private function showUserPermissions(): int
    {
        $userIdentifier = $this->option('user');
        
        if (!$userIdentifier) {
            $userIdentifier = $this->ask('请输入用户标识 (用户ID、用户名或角色名)');
        }

        if (!$userIdentifier) {
            $this->error('用户标识不能为空');
            return 1;
        }

        // 查找用户
        $user = $this->findUser($userIdentifier);
        if (!$user) {
            $this->error("未找到用户: {$userIdentifier}");
            return 1;
        }

        // 查找 Mumble 用户
        $mumbleUser = User::where('connector_type', 'mumble')
            ->where('user_id', $user->id)
            ->first();

        if (!$mumbleUser) {
            $this->warn("用户 {$user->name} 尚未注册 Mumble");
            return 1;
        }

        // 获取权限
        $permissions = $this->permissionService->getUserPermissions($mumbleUser);

        $this->info("=== 用户权限信息 ===");
        $this->line("用户名: {$user->name}");
        $this->line("Mumble 用户名: {$mumbleUser->connector_name}");
        if ($user->main_character) {
            $this->line("主角色: {$user->main_character->name}");
        }
        $this->line("权限角色: {$permissions['role']}");

        $this->newLine();
        $this->info("权限详情:");

        // 检查各种权限来源
        if ($this->permissionService->isSuperAdmin($user)) {
            $this->line("  ✅ SeAT 超级管理员");
        }

        if ($this->permissionService->isConfiguredAdmin($user)) {
            $this->line("  ✅ 配置的 Mumble 管理员");
        }

        $corpRole = $this->permissionService->getCorporationRole($user);
        if ($corpRole) {
            $this->line("  🏢 军团角色: {$corpRole}");
        }

        // 显示权限位
        $permissionNames = $this->permissionService->getPermissionNames();
        if (isset($permissions['global'])) {
            $this->newLine();
            $this->line("全局权限:");
            foreach ($permissionNames as $bit => $name) {
                $hasPermission = ($permissions['global'] & $bit) === $bit;
                $status = $hasPermission ? '✅' : '❌';
                $this->line("  {$status} {$name}");
            }
        }

        return 0;
    }

    /**
     * 测试权限系统
     */
    private function testPermissions(): int
    {
        $this->info('🧪 测试权限系统...');

        // 测试权限服务初始化
        try {
            $config = $this->permissionService->getPermissionConfig();
            $this->line("✅ 权限配置加载成功");
        } catch (\Exception $e) {
            $this->error("❌ 权限配置加载失败: {$e->getMessage()}");
            return 1;
        }

        // 测试管理员配置
        $admins = $this->permissionService->getAdminUsers();
        $this->line("✅ 管理员配置: " . count($admins) . " 个管理员");

        // 测试用户权限检查
        $testUsers = User::where('connector_type', 'mumble')
            ->with(['user'])
            ->limit(3)
            ->get();

        $this->newLine();
        $this->info('测试用户权限:');

        foreach ($testUsers as $mumbleUser) {
            try {
                $permissions = $this->permissionService->getUserPermissions($mumbleUser);
                $this->line("  ✅ {$mumbleUser->connector_name}: {$permissions['role']}");
            } catch (\Exception $e) {
                $this->line("  ❌ {$mumbleUser->connector_name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('✅ 权限系统测试完成');

        return 0;
    }

    /**
     * 查找用户
     */
    private function findUser($identifier): ?SeatUser
    {
        // 尝试按用户ID查找
        if (is_numeric($identifier)) {
            $user = SeatUser::find($identifier);
            if ($user) return $user;
        }

        // 尝试按用户名查找
        $user = SeatUser::where('name', $identifier)->first();
        if ($user) return $user;

        // 尝试按角色名查找
        $user = SeatUser::whereHas('characters', function ($query) use ($identifier) {
            $query->where('name', $identifier);
        })->first();
        
        return $user;
    }

    /**
     * 显示帮助信息
     */
    private function showHelp(): void
    {
        $this->newLine();
        $this->info('可用操作:');
        $this->line('  list         - 列出当前权限配置');
        $this->line('  add-admin    - 添加管理员用户');
        $this->line('  remove-admin - 移除管理员用户');
        $this->line('  sync         - 同步权限到 Mumble 服务器');
        $this->line('  show         - 显示用户权限详情');
        $this->line('  test         - 测试权限系统');

        $this->newLine();
        $this->info('示例:');
        $this->line('  php artisan mumble:permissions list');
        $this->line('  php artisan mumble:permissions add-admin --user="John Doe"');
        $this->line('  php artisan mumble:permissions show --user=123');
        $this->line('  php artisan mumble:permissions sync --dry-run');
    }

    /**
     * 同步用户权限到 Mumble 服务器
     */
    private function syncUserPermissionsToMumble(User $mumbleUser, array $permissions): bool
    {
        try {
            // 获取 Mumble 用户ID
            $mumbleUserId = $this->getMumbleUserId($mumbleUser);
            if (!$mumbleUserId) {
                $this->warn("  警告: 无法获取 Mumble 用户ID");
                return false;
            }

            // 初始化 Ice 服务
            $settings = setting('seat-connector.drivers.mumble', true);
            if (!$settings) {
                throw new \Exception('Mumble settings not found');
            }

            $iceService = new MumbleIceService($settings);
            if (!$iceService->isConnected()) {
                throw new \Exception('Ice connection not available');
            }

            // 根据权限角色设置权限
            switch ($permissions['role']) {
                case 'admin':
                case 'seat_admin':
                    return $iceService->setUserAdmin($mumbleUserId);
                    
                case 'seat_moderator':
                case 'corp_ceo':
                case 'corp_director':
                    $moderatorPerms = [
                        'allow' => PermissionService::ROLE_MODERATOR,
                        'deny' => 0
                    ];
                    return $iceService->setUserPermissions($mumbleUserId, 0, $moderatorPerms);
                    
                default:
                    $userPerms = [
                        'allow' => PermissionService::ROLE_USER,
                        'deny' => 0
                    ];
                    return $iceService->setUserPermissions($mumbleUserId, 0, $userPerms);
            }
            
        } catch (\Exception $e) {
            $this->error("  错误: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * 获取 Mumble 用户ID
     */
    private function getMumbleUserId(User $mumbleUser): ?int
    {
        // 尝试从 connector_id 获取
        if (is_numeric($mumbleUser->connector_id)) {
            return (int) $mumbleUser->connector_id;
        }

        // 尝试通过 Ice 接口查找用户
        try {
            $settings = setting('seat-connector.drivers.mumble', true);
            if (!$settings) {
                return null;
            }

            $iceService = new MumbleIceService($settings);
            if (!$iceService->isConnected()) {
                return null;
            }

            $users = $iceService->getRegisteredUsers($mumbleUser->connector_name);
            if (!empty($users)) {
                return (int) array_key_first($users);
            }
            
        } catch (\Exception $e) {
            logger()->warning('Failed to get Mumble user ID via Ice', [
                'username' => $mumbleUser->connector_name,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
}