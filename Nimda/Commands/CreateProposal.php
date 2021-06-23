<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\HTTP\DiscordAPIException;
use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;

/**
 *
 * Add a proposal to the latest poll of the channel:
 *
 *     !proposal Pizza
 *
 * Or add it to a specific poll of the channel:
 *
 *     !proposal 54 Pizza
 *
 *
 *
 * Class CreatePoll
 * @package Nimda\Commands
 */
class CreateProposal extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;
        $actor = $message->author;

        $channel->startTyping();

        printf("CreateProposal triggered.\n");

        $name = $args->get('name');
        $name = trim($name);
        if (empty($name)) {
            printf("Showing documentation for !proposal to `%s'…\n", $actor);
            $documentationContent =
                "Please provide the **proposal name** as well, for example:\n".
                "- `!proposal Bleu de Bresse`\n".
                "- `!proposal My awesome proposal`\n".
                "\n".
                "You may also target a specific poll in this channel using its identifier:\n".
                "- `!proposal 42 Don't Panic!`\n".
                "\n".
                "_(this message will self-destruct in a minute)_\n".
                ""
            ;
            $message->delete(0, "command");
            $documentationShowed = $this->sendToast($channel, $message, $documentationContent, [], 60);

            $channel->stopTyping();
            return reject($documentationShowed);
        }
        $name = mb_strimwidth($name, 0, $this->config['proposalMaxLength'], "…");
        $name = mb_strtoupper($name);


        $pollId = $args->get('pollId');
        if (empty($pollId)) {
            //printf("Guessing the poll identifier…\n");
            try {
                $pollId = $this->getLatestPollIdOfChannel($channel);
            } catch (\Exception $exception) {
                printf("ERROR failed to fetch the latest poll id of channel `%s'.\n", $channel);
                dump($exception);
                $channel->stopTyping();
                return reject();
            }
        }

        printf(
            "[%s:%s] Add proposal `%s' to the poll #%d…\n",
            $channel->getId(), $actor->username, $name, $pollId
        );

        $commandPromise = new Promise(
            function ($resolve, $reject) use ($channel, $message, $name, $pollId) {

                $triggerMessageDeletion = $message->delete(0, "command");
                $triggerMessageDeletion->done(); // todo: is done() blocking?  Care about errors as well!

                if (0 === $pollId) {
                    printf("No poll found in channel `%s'.\n", $channel);
                }

                $pollObject = $this->findPollById($pollId);
                if (empty($pollObject)) {
                    $channel->stopTyping();
                    return $reject($this->sendToast(
                        $channel,
                        $message,
                        sprintf(
                            "The poll %s `%d` could not be found.  ".
                            "Either you misspelled it, someone deleted the original message, ".
                            "or we deleted the database, ".
                            "because we have no migration system in-place during alpha.  ".
                            "_Contributions are welcome._",
                            $this->getPollEmoji(), $pollId
                        ),
                        [],
                        30
                    ));
                }

                // Check against the channel id, so we don't add proposals to foreign polls.
                // fetchMessage handles this check for now, yet best add one later here for good measure

                return $channel
                    ->fetchMessage($pollObject->messageId)
                    ->otherwise(function ($error) use ($resolve, $reject, $channel, $message, $pollId) {
                        if (get_class($error) === DiscordAPIException::class) {
                            /** @var DiscordAPIException $error */
                            if ($error->getCode() === 10008) {  // Unknown Message
                                printf("WARN The message for poll %d has been deleted!\n", $pollId);
                                $this->removePoll($pollId);
                                $channel->stopTyping();
                                // We resolve because we handled the case and there's no reason to go through the command error catcher
                                return $resolve($this->sendToast(
                                    $channel,
                                    $message,
                                    sprintf(
                                        "%s The poll %s `%d` was probably deleted by someone.",
                                        $this->getErrorEmoji(), $this->getPollEmoji(), $pollId
                                    ),
                                    [],
                                    20
                                ));
                            }
                        }
                        return $reject($error);
                    })
                    ->then(function (Message $pollMessage) use ($resolve, $reject, $message, $channel, $name, $pollObject) {

                        printf("Got the poll message, adding the proposal…\n");

                        $proposalAddition = $this->addProposal(
                            $channel,
                            $message,
                            $name,
                            $pollObject->id,
                            $pollObject->amountOfGrades
                        );

                        return $proposalAddition->then(
                            function (Message $proposalMessage) use ($resolve) {
                                return $resolve($proposalMessage);
                            },
                            function ($error) use ($reject) {
//                                $this->sendToast(
//                                    $channel,
//                                    $message,
//                                    sprintf("The  ?."),
//                                    [],
//                                    10
//                                );
                                return $reject($error);
                            }
                        );
                    });
            }
        );

        return $commandPromise->then(
            function ($thing) use ($channel) {
                $channel->stopTyping(true);
                return $thing;
            },
            function ($error) use ($channel) {
                $channel->stopTyping(true);
                printf("ERROR with the !proposal command:\n");
                dump($error);
                return $error;
            }
        );
    }

    public function isConfigured(): bool
    {
        return (
            parent::isConfigured() &&
            (! empty($this->config['proposalMaxLength']))
        );
    }


}