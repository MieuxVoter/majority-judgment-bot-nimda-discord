<?php declare(strict_types=1);

namespace Nimda;

use CharlotteDunois\Yasmin\Client;
use Nimda\Configuration\Discord;
use Nimda\Core\CommandContainer;
use Nimda\Core\Conversation;
use Nimda\Core\Database;
use Nimda\Core\EventContainer;
use Nimda\Core\TimerContainer;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Throwable;

/**
 * Class Nimda
 * @package Nimda
 */
final class Nimda
{
    /**
     * @var LoopInterface $loop
     */
    private LoopInterface $loop;

    /**
     * @var Client $client
     */
    private Client $client;

    /**
     * @var CommandContainer $commands
     */
    private CommandContainer $commands;

    /**
     * @var EventContainer $events
     */
    private EventContainer $events;

    /**
     * @var TimerContainer $timers
     */
    private TimerContainer $timers;

    /**
     * Nimda constructor.
     * @throws Throwable
     */
    public function __construct()
    {
        $this->startupCheck();
        $this->loop = Factory::create();
        $this->client = new Client(Discord::config()['options'], $this->loop);

        Database::boot();

        $this->commands = new CommandContainer();
        $this->events = new EventContainer($this->client);
        $this->timers = new TimerContainer($this->client);

        $this->register();

        $this->commands->loadCommands();
        $this->events->loadEvents();
        $this->timers->loadTimers();
    }

    /**
     * Nimda run method, boots and runs the discord loop
     */
    public function run(): void
    {
        printf("Running Nimda…\n");
        Conversation::init();
        $this->client->login(Discord::config()['client_token'])->done();
        $this->loop->run();
        printf("Loop has ended.\n");
    }

    /**
     * Runs when a connection is established
     */
    public function onReady(): void
    {
        printf('Logged in as %s created on %s' . PHP_EOL, $this->client->user->tag,
            $this->client->user->createdAt->format('d.m.Y H:i:s')
        );

        $this->client->addPeriodicTimer(Discord::config()['conversation']['timeout'],
            [Conversation::class, 'refreshConversations']
        );
    }


    /**
     * Runs when a connection fails
     */
    public function onError($error): void
    {
        printf('ERROR: %s' . PHP_EOL, $error);
    }

    /**
     * Runs when clients emits debug info
     */
    public function onDebug($msg): void
    {
        printf('Debug: %s' . PHP_EOL, $msg);
    }



    /**
     * @internal Register events for Nimda to handle
     */
    private function register(): void
    {
        $this->client->on('ready', [$this, 'onReady']);
        $this->client->on('error', [$this, 'onError']);
        $this->client->on('debug', [$this, 'onDebug']);
        $this->client->on('message', [$this->commands, 'onMessage']);
    }

    /**
     * @throws \Exception & Throwable
     * @internal Check for invalid options before booting
     *
     */
    private function startupCheck(): void
    {
        throw_if(\PHP_SAPI !== 'cli', \Exception::class, 'Nimda can only be used in the CLI SAPI. Please use PHP CLI to run Nimda.');

        throw_if(Discord::config()['client_token'] === '', \Exception::class, 'No client token set in config.');

        // Let Nimda run as root in docker containers
        // Perhaps we could keep the check but also check an env var that would be set in our Dockerfile?
//        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && posix_getuid() === 0) {
//            printf("[WARNING] Running Nimda as root is dangerous!\nStart anyway? Y/N: ");
//
//            $answer = strcasecmp(rtrim(fgets(STDIN)), 'y');
//            throw_if($answer !== 0, \Exception::class, 'Nimda running as root, user aborted.');
//        }
    }
}