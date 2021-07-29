<?php


namespace Nimda\Core;


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

    static function error(string $message)
    {
        self::log(self::LEVEL_ERROR, $message);
    }

    static function warn(string $message)
    {
        self::log(self::LEVEL_WARN, $message);
    }

    static function info(string $message)
    {
        self::log(self::LEVEL_INFO, $message);
    }

    static function debug(string $message)
    {
        self::log(self::LEVEL_DEBUG, $message);
    }

    static function log(int $level, string $message)
    {
        if (
            (
                $level === self::LEVEL_DEBUG
                ||
                $level === self::LEVEL_DEBUG
            )
            &&
            getenv('APP_ENV') !== 'dev'
        ) {
            return;
        }
        $now = new \DateTime();
        printf("%s %s" . PHP_EOL, $now->format('Y-m-d H:i:s'), $message);
    }

}