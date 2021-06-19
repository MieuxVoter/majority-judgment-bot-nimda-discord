<?php

require_once("vendor/autoload.php");

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->usePutenv();
$dotenv->overload(__DIR__.'/.env');

if (\is_readable(__DIR__.'/.env.local')) {
    $dotenv->overload(__DIR__.'/.env.local');
} else {
    print("\nWARNING: You should create a .env.local file like the .env, but with your own secret configuration.  That file will be ignored by git.\n\n");
}

DEFINE('NIMDA_PATH' ,__DIR__.'/Nimda/');

$nimda = new \Nimda\Nimda();
$nimda->run();