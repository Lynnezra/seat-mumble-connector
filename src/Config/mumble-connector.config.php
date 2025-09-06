<?php

return [
    'version' => '1.0.0',
    'name' => 'SeAT Mumble Connector',
    'description' => 'Mumble voice communication integration for SeAT',
    
    // 默认配置
    'defaults' => [
        'server_host' => 'localhost',
        'server_port' => 64738,
        'auto_create_channels' => true,
        'allow_user_registration' => true,
    ],
    
    // 频道模板
    'channel_templates' => [
        'alliance' => [
            'name_format' => '[{alliance_ticker}] {alliance_name}',
            'description_format' => 'Alliance: {alliance_name}',
            'sub_channels' => [
                'General',
                'Fleet Operations', 
                'Leadership'
            ]
        ],
        'corporation' => [
            'name_format' => '[{corp_ticker}] {corp_name}',
            'description_format' => 'Corporation: {corp_name}',
            'sub_channels' => [
                'General',
                'Industry',
                'PvP',
                'Logistics'
            ]
        ]
    ],
    
    // 权限映射
    'permission_mapping' => [
        'superuser' => [
            'admin' => true,
            'kick' => true,
            'ban' => true,
            'mute' => true,
            'move' => true
        ],
        'corporation_ceo' => [
            'kick' => true,
            'mute' => true,
            'move' => true
        ],
        'corporation_director' => [
            'mute' => true,
            'move' => true
        ],
        'member' => [
            'speak' => true,
            'whisper' => true
        ]
    ]
];