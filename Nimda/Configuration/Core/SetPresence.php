<?php

namespace Nimda\Configuration\Core;

class SetPresence
{
    public static $config = [
        'avatar' => 'https://app.mieuxvoter.fr/static/media/logo-color.d4b3de28.svg',
        'presence' => [
            'status' => 'online',
            'game' => [
                'name' => 'mieux voter',
                'type' => 1
            ]
        ],
        'interval' => '5',
        'once' => true,
    ];
}