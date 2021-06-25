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

        // These are set via getenv() – see self::config() below
//        'client_token' => '',
//        'permissions' => 223296,
//        'name' => 'Majority Judgment',
//        'discriminator' => '6660',

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
//                \Nimda\Core\Commands\PurgeChat::class,
//                \Nimda\Core\Commands\SayHello::class,
//                \Nimda\Core\Commands\Dice::class,
//                \Nimda\Core\Commands\Quotes::class,
            ],
            /**
             * Public commands created by the community. The Nimda Team are not responsible for their functionality.
             */
            'public' => [
                \Nimda\Commands\Join::class,
                \Nimda\Commands\Leave::class,
                \Nimda\Commands\CreatePoll::class,
                \Nimda\Commands\CreateProposal::class,
                \Nimda\Commands\ResolvePoll::class,
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
            ],
            /**
             * Public events created by the community. The Nimda Team are not responsible for their functionality.
             */
            'public' => [
                \Nimda\Events\VoteViaReaction::class,
            ]
        ]
    ];

    /**
     * Configure these values using .env.local
     *
     * @return array
     */
    public static function config() {
        $config = self::$config;
        $config['client_token'] = getenv('DISCORD_TOKEN');
        $config['name'] = getenv('DISCORD_NAME');
        $config['discriminator'] = getenv('DISCORD_DISCRIMINATOR');
        $config['permissions'] = (int) getenv('DISCORD_PERMISSIONS');
        return $config;
    }
}
