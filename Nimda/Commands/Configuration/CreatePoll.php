<?php

namespace Nimda\Commands\Configuration;

class CreatePoll
{
    public static $config = [
        'trigger' => [
            'commands' => [
                'poll {grades:[0-9]*} {subject:.*}',
//                'poll *(?P<grades>\d*) *(?P<subject>.*)',
//                'p',
            ]
        ],
    ];
}