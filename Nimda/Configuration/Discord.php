<?php

namespace Nimda\Configuration;

/**
 * Class Discord
 * @package Nimda\Configuration
 */
class Discord
{

    /**
     * id: "855361650844631090"
     * username: "Majority Judgment"
     * discriminator: "6660"
     * bot: true
     *
     * @var array $config Nimda master configuration
     */
    public static $config = [
        /**
         * Discord API configuration
         */
        'client_token' => '', // getenv('DISCORD_TOKEN') see self::config()
        'permissions' => 223296,
        'name' => 'Majority Judgment',
//        'id' => '855361650844631090',
        'options' => [
            'disableClones' => true,
            'disableEveryone' => true,
            'fetchAllMembers' => false,
            'messageCache' => true,
            'messageCacheLifetime' => 600,
            'messageSweepInterval' => 600,
            'presenceCache' => false,
            'userSweepInterval' => 600,
            'ws.disabledEvents' => [],
        ],
        /**
         * Command prefix, change this to what ever you wish (Note: / @ is reserved and interpreted by Discord)
         */
        'prefix' => '!',
        'deleteCommands' => false,

        'conversation' => [
            'safeword' => '!cancel',
            'timeout' => 10,  // Timeout in minutes, after this time stale conversations will be removed.
        ],

        'commands' => [
            /**
             * Core commands provided with Nimda with basic fundamental features
             */
            'core' => [
                # \Nimda\Core\Commands\MessageLogger::class,
                \Nimda\Core\Commands\CreatePoll::class,
                \Nimda\Core\Commands\ResolvePoll::class,
//                \Nimda\Core\Commands\SayHello::class,
//                \Nimda\Core\Commands\PurgeChat::class,
//                \Nimda\Core\Commands\Dice::class,
//                \Nimda\Core\Commands\Quotes::class,
            ],
            /**
             * Public commands created by the community. The Nimda Team are not responsible for their functionality.
             */
            'public' => [

            ],
        ],

        'timers' => [
            'core' => [
//                \Nimda\Core\Timers\Announcement::class,
                \Nimda\Core\Timers\SetPresence::class,
            ],
            'public' => [

            ],
        ],

        'events' => [
            /**
             * Core events provided with Nimda.
             */
            'core' => [
//                \Nimda\Core\Events\WelcomeMessage::class,
                \Nimda\Core\Events\VoteViaReaction::class,
            ],
            /**
             * Public events created by the community. The Nimda Team are not responsible for their functionality.
             */
            'public' => [

            ]
        ]
    ];

    public static function config() {
        $config = self::$config;
        $config['client_token'] = getenv('DISCORD_TOKEN');
        return $config;
    }
}
