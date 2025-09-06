<?php

Route::group([
    'namespace' => 'Lynnezra\Seat\Connector\Drivers\Mumble\Http\Controllers',
    'prefix' => 'seat-connector',
    'middleware' => ['web', 'auth', 'locale'],
], function () {

    // 用户注册路由
    Route::group([
        'prefix' => 'registration',
    ], function () {

        Route::get('/mumble', [
            'as' => 'seat-connector.drivers.mumble.registration',
            'uses' => 'RegistrationController@redirectToProvider'
        ]);

        Route::post('/mumble', [
            'as' => 'seat-connector.drivers.mumble.registration.submit',
            'uses' => 'RegistrationController@handleSubmit',
        ]);
    });

    // 设置路由
    Route::group([
        'prefix' => 'settings',
        'middleware' => 'can:global.superuser',
    ], function () {

        Route::post('/mumble', [
            'as' => 'seat-connector.drivers.mumble.settings',
            'uses' => 'SettingsController@store',
        ]);

        Route::post('/mumble/test-connection', [
            'as' => 'seat-connector.drivers.mumble.test-connection',
            'uses' => 'SettingsController@testConnection',
        ]);
    });

    // 权限管理路由
    Route::group([
        'prefix' => 'permissions',
        'middleware' => 'can:global.superuser',
    ], function () {

        Route::get('/mumble', [
            'as' => 'seat-mumble-connector.permissions.index',
            'uses' => 'PermissionController@index',
        ]);

        Route::post('/mumble/add-admin', [
            'as' => 'seat-mumble-connector.permissions.add-admin',
            'uses' => 'PermissionController@addAdmin',
        ]);

        Route::post('/mumble/remove-admin', [
            'as' => 'seat-mumble-connector.permissions.remove-admin',
            'uses' => 'PermissionController@removeAdmin',
        ]);

        Route::post('/mumble/sync', [
            'as' => 'seat-mumble-connector.permissions.sync',
            'uses' => 'PermissionController@syncPermissions',
        ]);

        Route::get('/mumble/user/{user_id}', [
            'as' => 'seat-mumble-connector.permissions.user',
            'uses' => 'PermissionController@showUserPermissions',
        ]);
    });
});