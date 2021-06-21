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
        if (empty($name)) {
            printf("ERROR No proposal name\n");
            return reject("No proposal name");
        }

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
                    printf("ERROR poll `%s' not found.", $pollId);
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

}