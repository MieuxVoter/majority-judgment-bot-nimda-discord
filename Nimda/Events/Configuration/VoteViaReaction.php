<?php

namespace Nimda\Events\Configuration;

final class VoteViaReaction
{
    public static array $config = [
        'trigger' => [
            'events' => [
                'messageReactionAdd',
            ],
        ],
    ];
}