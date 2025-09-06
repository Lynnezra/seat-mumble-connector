<?php

namespace Lynnezra\Seat\Connector\Drivers\Mumble\Http\Controllers;

use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Models\User;
use Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient;

class RegistrationController extends Controller
{
    public function redirectToProvider()
    {
        $seat_user = auth()->user();
        
        $driver_user = User::where('connector_type', 'mumble')
            ->where('user_id', $seat_user->id)
            ->first();

        if (is_null($driver_user)) {
            $driver_user = new User();
        }

        // 获取设置，如果没有则使用默认值
        try {
            $settings = setting('seat-connector.drivers.mumble', true);
            $allow_registration = is_object($settings) && property_exists($settings, 'allow_user_registration') 
                ? $settings->allow_user_registration 
                : true; // 默认允许注册
        } catch (\Exception $e) {
            logger()->warning('Failed to load Mumble settings, using defaults', ['error' => $e->getMessage()]);
            $allow_registration = true;
        }

        return view('seat-mumble-connector::registration.mumble', [
            'driver_user' => $driver_user,
            'seat_user' => $seat_user,
            'allow_registration' => $allow_registration
        ]);
    }

    public function handleSubmit(Request $request)
    {
        $validatedData = $request->validate([
            'mumble_username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            'mumble_password' => [
                'required',
                'string',
                'min:6',
                'max:128'
            ],
            'nickname' => [
                'nullable',
                'string',
                'max:32'
            ]
        ]);

        $seat_user = auth()->user();
        $existing_user = User::where('connector_type', 'mumble')
            ->where('user_id', $seat_user->id)
            ->first();

        if ($existing_user) {
            return $this->handleUpdate($request, $existing_user);
        }

        return $this->handleRegistration($request);
    }

    private function handleRegistration(Request $request)
    {
        $seat_user = auth()->user();
        $mumble_username = $request->input('mumble_username');
        $mumble_password = $request->input('mumble_password');
        $nickname = $request->input('nickname');

        try {
            // 在Mumble服务器上创建用户
            $client = MumbleClient::getInstance();
            $mumble_user = $client->createUser($mumble_username, $mumble_password);

            if (!$mumble_user) {
                return redirect()->back()
                    ->with('error', trans('seat-mumble-connector::seat.registration_failed'));
            }

            // 在数据库中记录
            $driver_user = new User();
            $driver_user->connector_type = 'mumble';
            $driver_user->user_id = $seat_user->id;
            $driver_user->connector_id = $mumble_user->getClientId();
            $driver_user->unique_id = md5($seat_user->id . $mumble_username . time());
            $driver_user->connector_name = $mumble_username;
            $driver_user->nickname = $nickname;
            $driver_user->save();

            return redirect()->route('seat-connector.identities')
                ->with('success', trans('seat-mumble-connector::seat.registration_success'));

        } catch (\Exception $e) {
            logger()->error('Mumble user registration failed', [
                'user_id' => $seat_user->id,
                'username' => $mumble_username,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', trans('seat-mumble-connector::seat.registration_failed'));
        }
    }

    private function handleUpdate(Request $request, User $driver_user)
    {
        $nickname = $request->input('nickname');

        try {
            $driver_user->nickname = $nickname;
            $driver_user->save();

            return redirect()->route('seat-connector.identities')
                ->with('success', trans('seat-mumble-connector::seat.update_success'));

        } catch (\Exception $e) {
            logger()->error('Mumble user update failed', [
                'user_id' => $driver_user->user_id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', trans('seat-mumble-connector::seat.update_failed'));
        }
    }
}