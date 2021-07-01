<?php

namespace Nimda\Commands\Configuration;

class ResolvePoll
{
    public static $config = [
        'trigger' => [
            'commands' => [
                '(?:result|judgment) {pollId:[0-9]+}',
                '(?:result|judgment)',
            ]
        ],
    ];
}