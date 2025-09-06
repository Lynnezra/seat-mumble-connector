<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Http\Controllers;

use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'mumble_server_host' => 'required|string|max:255',
            'mumble_server_port' => 'required|integer|between:1,65535',
            'mumble_admin_username' => 'nullable|string|max:255',
            'mumble_admin_password' => 'nullable|string|max:255',
            'auto_create_channels' => 'boolean',
            'allow_user_registration' => 'boolean'
        ]);

        // 清空现有设置
        setting(['seat-connector.drivers.mumble', null], true);

        // 保存新设置
        $settings = (object) [
            'mumble_server_host' => $request->input('mumble_server_host'),
            'mumble_server_port' => $request->input('mumble_server_port'),
            'mumble_admin_username' => $request->input('mumble_admin_username'),
            'mumble_admin_password' => $request->input('mumble_admin_password'),
            'auto_create_channels' => $request->input('auto_create_channels', false),
            'allow_user_registration' => $request->input('allow_user_registration', true),
        ];

        setting(['seat-connector.drivers.mumble', $settings], true);

        return redirect()->route('seat-connector.settings')
            ->with('success', trans('seat-mumble-connector::seat.settings_updated'));
    }

    public function testConnection(Request $request)
    {
        try {
            $client = \Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient::getInstance();
            
            // 测试连接
            $users = $client->getUsers();
            
            return response()->json([
                'success' => true,
                'message' => trans('seat-mumble-connector::seat.connection_success'),
                'user_count' => count($users)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => trans('seat-mumble-connector::seat.connection_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}