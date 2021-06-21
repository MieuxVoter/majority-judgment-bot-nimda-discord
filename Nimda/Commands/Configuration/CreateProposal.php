<?php

namespace Nimda\Commands\Configuration;

class CreateProposal
{
    public static $config = [
        'trigger' => [
            'commands' => [
                '(propos(?:al|e)|candidate?) {pollId:[0-9]+} {name:.+}',
                '(propos(?:al|e)|candidate?) {name:.+}',
            ]
        ],
    ];
}