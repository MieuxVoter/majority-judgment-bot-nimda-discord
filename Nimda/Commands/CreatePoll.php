<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use Nimda\Entity\Poll;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;

/**
 *
 * !poll What do you want from life?
 * !poll 5 What do you want from life?
 *
 * Class CreatePoll
 * @package Nimda\Core\Commands
 */
final class CreatePoll extends PollCommand
{

    /**
     * @inheritDoc
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $defaultAmountOfGrades = 5;
        $minimumAmountOfGrades = 2;
        $maximumAmountOfGrades = 10; // if you change this, change $gradesEmotes in PollCommand as well

        $channel = $message->channel;
        $actor = $message->author;

        if ( ! $this->isChannelJoined($channel)) {
            return $this->remindThatJoinIsRequired($message);
        }

        $amountOfGrades = $args->get('grades');
        if (empty($amountOfGrades)) {
            $amountOfGrades = $defaultAmountOfGrades;
        }
        $amountOfGrades = min($maximumAmountOfGrades, max($minimumAmountOfGrades, (int) $amountOfGrades));

        $subject = trim($args->get('subject'));
        if (empty($subject)) {
            printf("Showing documentation for !poll to `%s'…\n", $actor->username);
            $documentationContent =
                "Please provide the **poll subject** as well, for example:\n".
                "⌨️ `!poll What should we do tonight?`\n".
                "⌨️ `!poll Which do you prefer?`\n".
                "\n".
                "You may also provide how many grades should be available ".
                sprintf(
                    "_(default=`%d`, min=`%d`, max=`%d`)_:\n",
                    $defaultAmountOfGrades, $minimumAmountOfGrades, $maximumAmountOfGrades
                ).
                "⌨️ `!poll 3 What's the answer?`\n".
                "_(this syntax is under construction and may change without warning)_\n".
                "\n".
                "Thank you for using Majority Judgment!\n".
                "You will find the source code of this bot here:\n".
                "https://github.com/MieuxVoter/majority-judgment-bot-nimda-discord\n".
                "\n".
                "_(this message will self-destruct in a minute)_\n".
                ""
            ;
            $message->delete(0, "command");
            $documentationShowed = $this->sendToast($channel, $message, $documentationContent, [], 60);

            return reject($documentationShowed);
        }
        // will perhaps fail with RTL languages
        $subject = mb_strimwidth($subject, 0, $this->config['subjectMaxLength'], "…");
        $subject = mb_strtoupper($subject);

        printf("Poll creation by '%s' with the following subject: %s\n", $message->author, $subject);

        $pollMessageBody = sprintf(
            "%s",
            $subject
        );
        $description = sprintf(
            "Using %d grades.  Add proposals with the `!proposal` command.",
            $amountOfGrades
        );

        $options = [
            'embed' => [
                'title' => $pollMessageBody,
                'description' => $description,
            ]
        ];

        $commandPromise = $message
            ->channel->send("", $options)
            ->then(function (Message $pollMessage) use ($args, $message, $subject, $amountOfGrades) {

                $addedPoll = $this->addPollToDb($message, $pollMessage, $subject, $amountOfGrades);

                return $addedPoll
                    ->otherwise(function ($error) {
                        printf("ERROR adding the poll to the database.\n");
                        dump($error);
                    })
                    ->then(
                        function (Poll $pollObject) use ($pollMessage, $message, $amountOfGrades) {

                            printf("Added new poll to database.\n");
                            dump($pollObject);

                            $pollId = $pollObject->getId();

                            $pollMessageEdition = $pollMessage->edit(sprintf(
                                "⚖️ Poll #`%s` %s",
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

    public function isConfigured(): bool
    {
        return (
            parent::isConfigured()
            &&
            !empty($this->config['subjectMaxLength'])
        );
    }


}