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
});