<?php

namespace Nimda\Configuration\Core;

class VoteViaReaction
{
    public static $config = [
        'trigger' => [
            'events' => [
                'messageReactionAdd',
            ],
        ],
    ];
}