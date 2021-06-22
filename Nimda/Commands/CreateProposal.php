<?php

namespace Nimda\Commands;

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

        printf("CreateProposal triggered.\n");

        $pollId = $args->get('pollId');
        if (empty($pollId)) {
            printf("Guessing the poll identifier…\n");
            try {
                $pollId = $this->getLatestPollIdOfChannel($channel);
            } catch (\Exception $exception) {
                printf("ERROR failed to fetch the latest poll id of channel `%s'.\n", $channel);
                dump($exception);
                return reject();
            }
        }

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

            return reject($documentationShowed);
        }
        // will perhaps fail with RTL languages
        $name = mb_strimwidth($name, 0, $this->config['proposalMaxLength'], "…");
        $name = mb_strtoupper($name);

        printf(
            "[%s:%s] Add proposal `%s' to the poll #%d…\n",
            $channel->getId(), $actor->username, $name, $pollId
        );

        $commandPromise = new Promise(
            function ($resolve, $reject) use ($channel, $message, $name, $pollId) {

                $triggerMessageDeletion = $message->delete(0, "cleanup");
                $triggerMessageDeletion->done(); // todo: is done() blocking?  Care about errors as well!

                if (0 === $pollId) {
                    printf("No poll found in channel `%s'.\n", $channel);
                }

                $pollObject = $this->findPollById($pollId);
                if (empty($pollObject)) {
                    printf("ERROR poll `%s' not found.\n", $pollId);
                    return $reject();
                }

                // Check against the channel id, so we don't add proposals to foreign polls.
                // fetchMessage handles this check for now, yet best add one later here for good measure

                return $channel->fetchMessage($pollObject->messageId)
                    ->otherwise($reject)
                    ->then(function (Message $pollMessage) use ($resolve, $channel, $message, $name, $pollObject) {

                        printf("Got the poll message, adding the proposal…");

                        $addition = $this->addProposal(
                            $message,
                            $pollMessage,
                            $name,
                            $pollObject->id,
                            $pollObject->amountOfGrades
                        );

                        $addition->then(function (Message $proposalMessage) use ($resolve) {
                            return $resolve($proposalMessage);
                        });

                        return $addition;
                    });
            }
        );

        $commandPromise->then(
            null,
            function ($error) {
                printf("ERROR with the !proposal command:\n");
                dump($error);
            }
        );

        return $commandPromise;
    }

    public function isConfigured(): bool
    {
        return (
            parent::isConfigured() &&
            (! empty($this->config['proposalMaxLength']))
        );
    }


}