<?php

/**
 * This file is part of SeAT Mumble Connector.
 *
 * Copyright (C) 2024 Lynnezra <lynnezra@gmail.com>
 *
 * SeAT Mumble Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Mumble Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

return [
    'name'     => 'Mumble',
    'icon'     => 'fas fa-microphone',
    'client'   => \Lynnezra\Seat\Connector\Drivers\Mumble\Driver\MumbleClient::class,
    'settings' => [
        [
            'name' => 'mumble_server_host',
            'label' => 'seat-mumble-connector::seat.server_host',
            'type' => 'text'
        ],
        [
            'name' => 'mumble_server_port', 
            'label' => 'seat-mumble-connector::seat.server_port',
            'type' => 'number'
        ],
        [
            'name' => 'mumble_ice_host',
            'label' => 'seat-mumble-connector::seat.ice_host',
            'type' => 'text'
        ],
        [
            'name' => 'mumble_ice_port',
            'label' => 'seat-mumble-connector::seat.ice_port',
            'type' => 'number'
        ],
        [
            'name' => 'mumble_ice_secret',
            'label' => 'seat-mumble-connector::seat.ice_secret',
            'type' => 'password'
        ],
        [
            'name' => 'mumble_admin_username',
            'label' => 'seat-mumble-connector::seat.admin_username', 
            'type' => 'text'
        ],
        [
            'name' => 'mumble_admin_password',
            'label' => 'seat-mumble-connector::seat.admin_password',
            'type' => 'password'
        ],
        [
            'name' => 'auto_create_channels',
            'label' => 'seat-mumble-connector::seat.auto_create_channels',
            'type' => 'checkbox'
        ],
        [
            'name' => 'allow_user_registration',
            'label' => 'seat-mumble-connector::seat.allow_user_registration',
            'type' => 'checkbox'
        ]
    ]
];