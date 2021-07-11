<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use React\Promise\PromiseInterface;

/**
 * Displays information on how to use the bot.
 *
 * !help
 *
 * Class Help
 * @package Nimda\Core\Commands
 */
final class Help extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;

        $this->log($message, "!help");

        $channel->startTyping();

        // Should we only respond on channels we joined already?  Not sure.
//        /** @var Channel $dbChannel */
//        $dbChannel = $this->findDbChannel($channel);
//        if (null === $dbChannel) {
//            $channel->stopTyping();
//            return resolve();
//        }

        if ( ! $this->isMentioningMe($message)) {
            $channel->stopTyping();
            return $this->sendToast(
                $channel, $message,
                sprintf(
                    "If you want me to help, " .
                    "please mention me as well, like so: " .
                    "`!help @Majority Judgment`"
                ),
                [],
                30
            );
        }

        return $channel->send(
            sprintf(
<<<MSG
Greetings!  _I am help to help._ 🤖

Here are the commands I will respond to:
⌨ `!join` to have me **join a channel** (admins only)
⌨ `!leave` to have me **leave a channel** _(I will forget everything ⚠)_
⌨ `!poll` to **start a new poll**
⌨ `!proposal` to **add a proposal**
⌨ `!result` to **show the result** of a poll

The full 📖 documentation and source code can be found here : https://github.com/MieuxVoter/majority-judgment-bot-nimda-discord
If you'd like to support this bot, please consider giving some 💰 money to someone that needs it.
> _We're all in this together._
MSG
            ),
            []
        )->otherwise(
            function ($error) use ($message, $channel) {
                $channel->stopTyping();
                $this->log($message, "Failed to respond to !help");
            }
        )->then(
            function (Message $m) use ($channel) {
                $channel->stopTyping();
            }
        );
    }

}