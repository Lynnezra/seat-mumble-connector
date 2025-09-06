@extends('web::layouts.grids.12')

@section('title', 'Mumble 权限管理')
@section('page_header', 'Mumble 权限管理')

@section('full')

<div class="row">
    <!-- 管理员管理 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-shield"></i>
                    管理员用户
                </h3>
            </div>
            <div class="card-body">
                <!-- 添加管理员表单 -->
                <form method="post" action="{{ route('seat-mumble-connector.permissions.add-admin') }}">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="user_identifier">添加管理员</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="user_identifier" name="user_identifier" 
                                   placeholder="用户ID、用户名或角色名" required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> 添加
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            支持输入：用户ID、SeAT用户名或EVE角色名
                        </small>
                    </div>
                </form>

                <!-- 当前管理员列表 -->
                <h5 class="mt-4">当前管理员 ({{ count($admin_users) }})</h5>
                @if(empty($admin_users))
                    <p class="text-muted">暂无配置的管理员用户</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>标识</th>
                                    <th>用户信息</th>
                                    <th>主角色</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($admin_users as $admin)
                                <tr>
                                    <td><code>{{ $admin['identifier'] }}</code></td>
                                    <td>
                                        @if($admin['user'])
                                            <span class="text-success">{{ $admin['user']->name }}</span>
                                        @else
                                            <span class="text-warning">未找到用户</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($admin['main_character'])
                                            {{ $admin['main_character']->name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('seat-mumble-connector.permissions.remove-admin') }}" 
                                              style="display: inline;" 
                                              onsubmit="return confirm('确定要移除此管理员吗？')">
                                            {{ csrf_field() }}
                                            <input type="hidden" name="user_identifier" value="{{ $admin['identifier'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 用户统计 -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    用户统计
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">总用户数</span>
                                <span class="info-box-number">{{ $user_stats['total_users'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-user-shield"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">管理员</span>
                                <span class="info-box-number">{{ $user_stats['admin_users'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-user-plus"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">7天新增</span>
                                <span class="info-box-number">{{ $user_stats['recent_users'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 权限同步 -->
                <div class="mt-3">
                    <h5>权限同步</h5>
                    <p class="text-muted">将 SeAT 中的权限配置同步到 Mumble 服务器</p>
                    
                    <form method="post" action="{{ route('seat-mumble-connector.permissions.sync') }}" 
                          onsubmit="return confirm('确定要同步所有用户权限吗？这可能需要一些时间。')">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync"></i> 同步权限到 Mumble
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 权限配置 -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cogs"></i>
                    权限配置
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>角色</th>
                                <th>管理员</th>
                                <th>踢出用户</th>
                                <th>封禁用户</th>
                                <th>静音用户</th>
                                <th>移动用户</th>
                                <th>创建频道</th>
                                <th>删除频道</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permission_config as $role => $permissions)
                            <tr>
                                <td><strong>{{ ucfirst(str_replace('_', ' ', $role)) }}</strong></td>
                                <td>
                                    @if(isset($permissions['admin']) && $permissions['admin'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['kick']) && $permissions['kick'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['ban']) && $permissions['ban'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['mute']) && $permissions['mute'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['move']) && $permissions['move'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['create_channel']) && $permissions['create_channel'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($permissions['delete_channel']) && $permissions['delete_channel'])
                                        <span class="badge badge-success">✓</span>
                                    @else
                                        <span class="badge badge-secondary">✗</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <h5>权限说明</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Superuser:</strong> SeAT 超级管理员，拥有所有权限</li>
                                <li><strong>Corporation CEO:</strong> 军团CEO，拥有管理权限</li>
                                <li><strong>Corporation Director:</strong> 军团董事，拥有部分管理权限</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Member:</strong> 普通成员，基本语音权限</li>
                                <li><strong>Guest:</strong> 访客，仅限基本功能</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('javascript')
<script>
$(document).ready(function() {
    // 权限同步进度提示
    $('form[action*="sync"]').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 同步中...');
    });
});
</script>
@endpush