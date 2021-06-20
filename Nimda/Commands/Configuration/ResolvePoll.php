<?php

namespace Nimda\Commands\Configuration;

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