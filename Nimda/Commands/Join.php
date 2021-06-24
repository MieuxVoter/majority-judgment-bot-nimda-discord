<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Doctrine\ORM\Query\AST\ConditionalExpression;
use Doctrine\ORM\Query\AST\WhereClause;
use Illuminate\Support\Collection;
use Nimda\Core\DatabaseDoctrine;
use Nimda\Entity\Channel;
use React\Promise\PromiseInterface;
use function Doctrine\ORM\QueryBuilder;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Enables the bot on the channel.
 *
 * !join
 *
 * Class Join
 * @package Nimda\Core\Commands
 */
class Join extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;

        $channel->startTyping();

        printf("!join\n");

        /** @var Channel $dbChannel */
        $dbChannel = DatabaseDoctrine::repo(Channel::class)->findOneBy(
            ['discordId' => $channel->getId()]
        );

        $message->delete(0, "command");

        if (null !== $dbChannel) {
            printf("Already joined channel `%s'.\n", $dbChannel->getDiscordId());
            //dump($dbChannel);
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf("I am already subscribed to this channel."),
                [],
                20
            );
        }

        $dbChannel = new Channel();
        $dbChannel->setDiscordId($channel->getId());
        $dbChannel->setGuildId($message->guild->id);
        $dbChannel->setGuildName($message->guild->name);
        $dbChannel->setJoinerId($message->author->id);
        $dbChannel->setJoinerUsername($message->author->username);
//        $dbChannel->setName(); // How do | can we get the channel's name?

        try {
            DatabaseDoctrine::$entityManager->persist($dbChannel);
            DatabaseDoctrine::$entityManager->flush();
        } catch (\Exception $exception) {
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf(
                    " CRITICAL DATABASE FAILURE : ALL HANDS ON DECK !\n```\n%s\n```",
                    $exception->getMessage()
                ),
                [],
                20
            );
        }

        printf("Joined channel `%s' !\n", $dbChannel->getDiscordId());

        $channel->stopTyping();

        return $this->sendToast(
            $channel, $message,
            sprintf(
                "OK! Let's do this!  Type `!poll` to start a poll."
            ),
            [],
            20
        );
    }

    public function isConfigured(): bool
    {
        return (
            parent::isConfigured()
        );
    }


}