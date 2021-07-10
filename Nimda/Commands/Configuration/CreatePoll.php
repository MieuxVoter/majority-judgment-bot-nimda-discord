<?php

namespace Nimda\Commands\Configuration;

class CreatePoll
{
    public static array $config = [
        'subjectMaxLength' => 255,
        'trigger' => [
            'commands' => [
                'poll {grades:[0-9]*} {subject:.*}',
//                'poll *(?P<grades>\d*) *(?P<subject>.*)',
//                'p',
            ],
        ],
    ];
}