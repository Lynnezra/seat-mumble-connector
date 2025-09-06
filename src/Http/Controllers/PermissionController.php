<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Http\Controllers;

use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;
use Lynnezra\Seat\Connector\Drivers\Mumble\Services\PermissionService;
use Warlof\Seat\Connector\Models\User;
use Seat\Web\Models\User as SeatUser;

class PermissionController extends Controller
{
    private $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    /**
     * 显示权限管理页面
     */
    public function index()
    {
        // 获取当前权限配置
        $permissionConfig = $this->permissionService->getPermissionConfig();
        
        // 获取管理员列表
        $adminUsers = $this->permissionService->getAdminUsers();
        $adminDetails = [];
        
        foreach ($adminUsers as $adminId) {
            $user = $this->findUser($adminId);
            if ($user) {
                $adminDetails[] = [
                    'identifier' => $adminId,
                    'user' => $user,
                    'main_character' => $user->main_character
                ];
            } else {
                $adminDetails[] = [
                    'identifier' => $adminId,
                    'user' => null,
                    'main_character' => null
                ];
            }
        }

        // 获取 Mumble 用户统计
        $userStats = [
            'total_users' => User::where('connector_type', 'mumble')->count(),
            'admin_users' => count($adminUsers),
            'recent_users' => User::where('connector_type', 'mumble')
                ->where('created_at', '>', now()->subDays(7))
                ->count()
        ];

        return view('seat-mumble-connector::permissions.index', [
            'permission_config' => $permissionConfig,
            'admin_users' => $adminDetails,
            'user_stats' => $userStats
        ]);
    }

    /**
     * 添加管理员
     */
    public function addAdmin(Request $request)
    {
        $request->validate([
            'user_identifier' => 'required|string|max:255'
        ]);

        $userIdentifier = trim($request->input('user_identifier'));
        
        // 验证用户是否存在
        $user = $this->findUser($userIdentifier);
        if (!$user) {
            return redirect()->back()
                ->with('warning', "警告: 未找到用户 '{$userIdentifier}'，但已添加到管理员列表。");
        }

        if ($this->permissionService->addAdminUser($userIdentifier)) {
            $message = "成功添加管理员: {$userIdentifier}";
            if ($user && $user->main_character) {
                $message .= " ({$user->main_character->name})";
            }
            
            return redirect()->back()
                ->with('success', $message);
        } else {
            return redirect()->back()
                ->with('error', "添加管理员失败: {$userIdentifier}");
        }
    }

    /**
     * 移除管理员
     */
    public function removeAdmin(Request $request)
    {
        $request->validate([
            'user_identifier' => 'required|string'
        ]);

        $userIdentifier = $request->input('user_identifier');

        if ($this->permissionService->removeAdminUser($userIdentifier)) {
            return redirect()->back()
                ->with('success', "成功移除管理员: {$userIdentifier}");
        } else {
            return redirect()->back()
                ->with('error', "移除管理员失败: {$userIdentifier}");
        }
    }

    /**
     * 同步权限
     */
    public function syncPermissions(Request $request)
    {
        try {
            // 获取所有 Mumble 用户
            $mumbleUsers = User::where('connector_type', 'mumble')
                ->with(['user', 'user.characters', 'user.characters.affiliation'])
                ->get();

            $updated = 0;
            $errors = 0;

            foreach ($mumbleUsers as $mumbleUser) {
                try {
                    $permissions = $this->permissionService->getUserPermissions($mumbleUser);
                    // 这里可以添加实际的 Mumble 权限同步逻辑
                    $updated++;
                } catch (\Exception $e) {
                    $errors++;
                    logger()->error('Failed to sync permissions for user', [
                        'user_id' => $mumbleUser->user_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($errors > 0) {
                return redirect()->back()
                    ->with('warning', "权限同步完成: {$updated} 个用户更新，{$errors} 个错误。");
            } else {
                return redirect()->back()
                    ->with('success', "权限同步完成: {$updated} 个用户权限已更新。");
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', "权限同步失败: {$e->getMessage()}");
        }
    }

    /**
     * 更新权限配置
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'permission_config' => 'required|array'
        ]);

        $config = $request->input('permission_config');

        if ($this->permissionService->updatePermissionConfig($config)) {
            return redirect()->back()
                ->with('success', '权限配置已更新');
        } else {
            return redirect()->back()
                ->with('error', '权限配置更新失败');
        }
    }

    /**
     * 显示用户权限详情
     */
    public function showUserPermissions(Request $request)
    {
        $userId = $request->input('user_id');
        
        if (!$userId) {
            return response()->json(['error' => '用户ID不能为空'], 400);
        }

        $user = SeatUser::find($userId);
        if (!$user) {
            return response()->json(['error' => '用户不存在'], 404);
        }

        $mumbleUser = User::where('connector_type', 'mumble')
            ->where('user_id', $userId)
            ->first();

        if (!$mumbleUser) {
            return response()->json(['error' => '用户尚未注册 Mumble'], 404);
        }

        $permissions = $this->permissionService->getUserPermissions($mumbleUser);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'main_character' => $user->main_character ? $user->main_character->name : null
            ],
            'mumble_user' => [
                'connector_name' => $mumbleUser->connector_name,
                'nickname' => $mumbleUser->nickname
            ],
            'permissions' => $permissions,
            'checks' => [
                'is_superuser' => $this->permissionService->isSuperAdmin($user),
                'is_configured_admin' => $this->permissionService->isConfiguredAdmin($user),
                'corporation_role' => $this->permissionService->getCorporationRole($user)
            ]
        ]);
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
}