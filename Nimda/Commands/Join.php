<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use Nimda\Core\Database;
use Nimda\Entity\Channel;
use React\Promise\PromiseInterface;

/**
 * Enables the bot on the channel.
 *
 * !join
 *
 * Class Join
 * @package Nimda\Core\Commands
 */
final class Join extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;

        $channel->startTyping();

        $this->log($message, "!join");

        if ( ! $this->isMessageActorAdmin($message)) {
            $channel->stopTyping();

            $this->log($message, "Actor is not admin.  Cancelling !join…");
            return $this->sendToast(
                $channel, $message,
                sprintf(
                    "You need to be the administrator of this server " .
                    "to let me `!join` this channel.  Sorry about that.  💒"
                ), [], -1
            );
        }

        if ( ! $this->isMentioningMe($message)) {
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf(
                    "If you want me to join this channel, " .
                    "please mention me as well, like so: " .
                    "`!join @Majority Judgment`"
                ),
                [],
                30
            );
        }

        /** @var Channel $dbChannel */
        $dbChannel = Database::repo(Channel::class)->findOneBy(
            ['discordId' => $channel->getId()]
        );


        if (null !== $dbChannel) {
            $this->log($message, "Already joined channel `%s'.", $dbChannel->getDiscordId());
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
            Database::$entityManager->persist($dbChannel);
            Database::$entityManager->flush();
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

        $this->log($message, "Joined channel `%s' !", $dbChannel->getDiscordId());

        $channel->stopTyping();
        return $channel->send(
            sprintf(
                "OK!  **Let's do this!**  Type `!poll` to start a poll."
            ),
            []
        );
    }

}