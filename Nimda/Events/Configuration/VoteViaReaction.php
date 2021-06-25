<?php

namespace Nimda\Events\Configuration;

final class VoteViaReaction
{
    public static $config = [
        'trigger' => [
            'events' => [
                'messageReactionAdd',
            ],
        ],
    ];
}