<?php


namespace Nimda\Core;


use CharlotteDunois\Yasmin\Models\Message;

/**
 * Homemade logger utility, so we can later on use monolog or such.
 *
 * Class Logger
 * @package Nimda\Core
 */
class Logger
{

    const LEVEL_ERROR = 1;
    const LEVEL_WARN = 2;
    const LEVEL_INFO = 3;
    const LEVEL_DEBUG = 4;

    static function error(...$things)
    {
        self::log(self::LEVEL_ERROR, ...$things);
    }

    static function warn(...$things)
    {
        self::log(self::LEVEL_WARN, ...$things);
    }

    static function info(...$things)
    {
        self::log(self::LEVEL_INFO, ...$things);
    }

    static function debug(...$things)
    {
        self::log(self::LEVEL_DEBUG, ...$things);
    }

    static function log(int $level, ...$things)
    {
        if (
            (
                $level === self::LEVEL_DEBUG
                ||
                $level === self::LEVEL_INFO
            )
            &&
            getenv('APP_ENV') !== 'dev'
        ) {
            return;
        }
        $triggerMessage = null;
        if (0 < count($things)) {
            if ($things[0] instanceof Message) {
                $triggerMessage = array_shift($things);
            }
        }
        if (0 === count($things)) {
            throw new \InvalidArgumentException();
        }
        $message = array_shift($things);
        $now = new \DateTime();
        array_unshift($things, $now->format('Y-m-d H:i:s'));
        printf("%s " . $message . PHP_EOL, ...$things);
    }

}