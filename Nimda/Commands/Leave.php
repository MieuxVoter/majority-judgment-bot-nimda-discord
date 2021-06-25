<?php declare(strict_types=1);

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use Nimda\Core\DatabaseDoctrine;
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

        $channel->startTyping();

        printf("!leave\n");

        /** @var Channel $dbChannel */
        $dbChannel = DatabaseDoctrine::repo(Channel::class)->findOneBy(
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
            printf("Already left channel `%s'.\n", $dbChannel->getDiscordId());
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf("I am already subscribed to this channel."),
                [],
                20
            );
        }

        try {
            DatabaseDoctrine::$entityManager->remove($dbChannel);
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
                300
            );
        }

        printf("Left channel `%s' !\n", $dbChannel->getDiscordId());

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

}