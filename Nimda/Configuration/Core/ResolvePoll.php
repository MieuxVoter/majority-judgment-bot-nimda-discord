<?php

namespace Nimda\Configuration\Core;

class ResolvePoll
{
    public static $config = [
        'trigger' => [
            'commands' => [
                'result {pollId:[0-9]+}',
            ]
        ],
    ];
}