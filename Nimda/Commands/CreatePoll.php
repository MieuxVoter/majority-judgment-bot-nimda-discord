<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use React\Promise\PromiseInterface;
use function React\Promise\all;

/**
 *
 * !poll 5 What do you want from life?
 *
 *
 *
 * Class CreatePoll
 * @package Nimda\Core\Commands
 */
class CreatePoll extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {

        $amountOfGrades = $args->get('grades');
        if (empty($amountOfGrades)) {
            $amountOfGrades = 7;
        }
        $amountOfGrades = min(10, max(2, (int) $amountOfGrades));

        $subject = $args->get('subject');
        if (empty($subject)) {
            $subject = "What do we choose?";
        }
        // fixme: sanitize subject   (at least truncate)

        printf("Poll creation by '%s' with the following subject: %s\n", $message->author, $subject);

        $pollMessageBody = sprintf(
            "%s\n_(using %d grades)_",
            $subject,
            $amountOfGrades
        );

        $commandPromise = $message
            ->channel->send($pollMessageBody)
            ->then(function (Message $pollMessage) use ($args, $message, $subject, $amountOfGrades) {

                $addedPoll = $this->addPollToDb($message, $pollMessage, $subject, $amountOfGrades);

                return $addedPoll
                    ->otherwise(function ($error) {
                        printf("ERROR adding the poll to the database.\n");
                        dump($error);
                    })
                    ->then(
                        function ($pollObject) use ($pollMessage, $message, $amountOfGrades) {

                            printf("Added new poll to database.\n");
                            dump($pollObject);

                            $pollId = $pollObject->id;

                            $pollMessageEdition = $pollMessage->edit(sprintf(
                                "Poll N°`%s`: %s",
                                (string) $pollId ?? '?',
                                $pollMessage->content
                            ));

                            printf("Started editing the poll message to add the poll ID…\n");

                            return $pollMessageEdition
                                ->otherwise(function ($error) {
                                    printf("ERROR editing the poll to add its ID.\n");
                                    dump($error);
                                })
                                ->then(function (Message $editedPollMessage) use ($message, $pollId, $amountOfGrades) {
                                    printf("Done editing the poll message to add the poll ID.\n");
                                    return all([
//                                        $this->addProposal(null, $editedPollMessage, "Proposal A", $pollId, $amountOfGrades),
//                                        $this->addProposal(null, $editedPollMessage, "Proposal B", $pollId, $amountOfGrades),
//                                        $this->addProposal(null, $editedPollMessage, "Proposal C", $pollId, $amountOfGrades),
                                    ])->then(function() use ($message) {
                                        return $message->delete();
                                    });
                                });

                        }
                    );
            });

        $commandPromise->then(
            null,
            function ($error) {
                printf("ERROR with the !poll command:\n");
                dump($error);
            }
        );

        return $commandPromise;
    }

}