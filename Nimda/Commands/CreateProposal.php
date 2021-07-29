<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\HTTP\DiscordAPIException;
use CharlotteDunois\Yasmin\Models\Message;
use Exception;
use Illuminate\Support\Collection;
use Nimda\Core\Logger;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
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
 * Class CreateProposal
 * @package Nimda\Commands
 */
final class CreateProposal extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;
        $actor = $message->author;

        if ( ! $this->isChannelJoined($channel)) {
            Logger::warn($message, "Trying to use !proposal on a non-joined channel.");
            return reject();
        }

        $channel->startTyping();

        $name = $args->get('name');
        $name = trim($name);

        Logger::info($message, "Requesting creation of the proposal `%s'…", $name);
        if (empty($name)) {
            Logger::info($message, "Showing documentation for !proposal to `%s'…", $actor);
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
            //$this->log($message, "Guessing the poll identifier…\n");
            try {
                $pollId = $this->getLatestPollIdOfChannel($channel);
            } catch (Exception $exception) {
                $this->log($message, "ERROR failed to fetch the latest poll id of channel `%s'.", $channel);
                dump($exception);
                $channel->stopTyping();
                return reject();
            }
        }

        $this->log($message,
            "[%s:%s] Add proposal `%s' to the poll #%d…",
            $channel->getId(), $actor->username, $name, $pollId
        );

        $commandPromise = new Promise(
            function ($resolve, $reject) use ($channel, $message, $name, $pollId) {

                $message->delete(0, "command");

                if (0 === $pollId) {
                    $this->log($message, "No poll found in channel `%s'.", $channel);
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
                    ->fetchMessage($pollObject->getMessageVendorId())
                    ->otherwise(function ($error) use ($resolve, $reject, $channel, $message, $pollId, $pollObject) {
                        if (get_class($error) === DiscordAPIException::class) {
                            /** @var DiscordAPIException $error */
                            if ($error->getCode() === 10008) {  // Unknown Message
                                $this->log($message, "WARN The message for poll %d has been deleted!", $pollId);
                                $this->removePollFromDb($pollObject);
                                $channel->stopTyping();
                                // We resolve() because we handled the failure case and
                                // there's no reason to go through the command error catcher
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

                        $this->log($message, "Got the poll message, adding the proposal…");

                        $proposalAddition = $this->addProposal(
                            $channel,
                            $message,
                            $name,
                            $pollObject
                        );

                        return $proposalAddition->then(
                            function (Message $proposalMessage) use ($resolve, $channel) {
                                // feels weird to stop typing after the reactions are added, let's stop now
                                $channel->stopTyping(true);
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
                $channel->stopTyping();
                return $thing;
            },
            function ($error) use ($channel, $message) {
                $channel->stopTyping();
                $this->log($message, "ERROR with the !proposal command:");
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