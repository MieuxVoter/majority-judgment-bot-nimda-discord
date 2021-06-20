<?php

namespace Nimda\Events\Configuration;

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