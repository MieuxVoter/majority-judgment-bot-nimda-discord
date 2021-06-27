<?php declare(strict_types=1);

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\User;
use Illuminate\Support\Collection;
use Nimda\Core\Database;
use Nimda\Entity\Channel;
use React\Promise\PromiseInterface;

/**
 * Disables the bot on the channel.
 *
 * !leave
 *
 * Class Leave
 * @package Nimda\Core\Commands
 */
final class Leave extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;
        $actor = $message->author;

        $channel->startTyping();

        $this->log($message, "!leave");

        /** @var Channel $dbChannel */
        $dbChannel = Database::repo(Channel::class)->findOneBy(
            ['discordId' => $channel->getId()]
        );

        $hasBotJoinedChannel = (null !== $dbChannel);

        //$message->delete(0, "command");  // let's keep it, for transparency?

        if ( ! $this->isMentioningMe($message) && $hasBotJoinedChannel) {
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf(
                    "If you want me to leave this channel, " .
                    "please mention me as well, like so: " .
                    "`!leave @Majority Judgment`"
                ),
                [],
                20
            );
        }

        if ( ! $hasBotJoinedChannel) {
            $this->log($message, "Already left channel `%s'.", $dbChannel->getDiscordId());
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf("I am not in this channel."),
                [],
                30
            );
        }

        // Prepare for it, but don't do it yet, since trolls may !join and lock the !leave
        if ( ! $this->isAllowedToRunLeave($message)) {
            $this->log($message, "Not allowed to !leave channel `%s'.", $dbChannel->getDiscordId());
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf("You are not allowed to tell me to `!leave`"),
                [],
                30
            );
        }

        try {
            Database::$entityManager->remove($dbChannel);
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
                300
            );
        }

        $this->log($message, "Left channel `%s' !", $dbChannel->getDiscordId());

        $channel->stopTyping();
        return $channel->send(
            sprintf(
                "Farewell.  Ask me to `!join` again anytime.\n\n" .
                "I have **deleted everything** I knew about this channel, " .
                "in the _eternal sunshine of my spotless mind_."
            ),
            []
        );
    }

    protected function isAllowedToRunLeave(Message $message)
    {
        // Best use roles as well here
        return (
            $this->isMessageActorAdmin($message)
        );
    }

}