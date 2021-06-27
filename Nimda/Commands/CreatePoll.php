<?php declare(strict_types=1);

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use Nimda\Entity\Poll;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\reject;
use function React\Promise\resolve;

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
        $dbChannel = $this->findDbChannel($channel);

        $amountOfGrades = $args->get('grades');
        if (empty($amountOfGrades)) {
            $amountOfGrades = $defaultAmountOfGrades;
        }
        $amountOfGrades = min($maximumAmountOfGrades, max($minimumAmountOfGrades, (int) $amountOfGrades));

        $subject = trim($args->get('subject'));
        if (empty($subject)) {
            $this->log($message, "Showing documentation for !poll to `%s'…", $actor->username);
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
        $subject = mb_strimwidth($subject, 0, $this->config['subjectMaxLength'], "…");
        $subject = mb_strtoupper($subject);

        $preset = $this->findMatchingPreset($message, $subject);
        if ( ! empty($preset)) {
            if ( ! empty($preset['subject'])) {
                $subject = mb_strtoupper($preset['subject']);
            }
        }

        $this->log($message, "Poll creation by '%s' with the following subject: %s", $message->author, $subject);

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

        $commandPromise = $channel
            ->send("", $options)
            ->then(function (Message $pollMessage) use ($args, $message, $channel, $dbChannel, $subject, $amountOfGrades, $preset) {

                $addedPoll = $this->addPollToDb($message, $pollMessage, $dbChannel, $subject, $amountOfGrades);

                return $addedPoll
                    ->otherwise(function ($error) use ($message) {
                        $this->log($message, "ERROR adding the poll to the database.");
                        dump($error);
                    })
                    ->then(
                        function (Poll $pollObject) use ($pollMessage, $message, $amountOfGrades, $preset) {

                            $this->log($message, "Added new poll to database.");
                            dump($pollObject);

                            $pollId = $pollObject->getId();

                            $pollMessageEdition = $pollMessage->edit(sprintf(
                                "⚖️ Poll #`%s` %s",
                                (string) $pollId ?? '?',
                                $pollMessage->content
                            ));

                            $this->log($message, "Started editing the poll message to add the poll ID…");

                            return $pollMessageEdition
                                ->otherwise(function ($error) use ($message) {
                                    $this->log($message, "ERROR editing the poll to add its ID.");
                                    dump($error);

                                    return $error;
                                })
                                ->then(function (Message $editedPollMessage) use ($message, $preset, $pollObject, $pollId, $amountOfGrades) {
                                    $this->log($message, "Done editing the poll message to add the poll ID.");

                                    $p = resolve();
                                    if ( ! empty($preset) && ! empty($preset['proposals'])) {
                                        foreach ($preset['proposals'] as $proposalName) {
                                            $p = $p->then(function () use ($pollObject, $proposalName, $message, $editedPollMessage) {
                                                return $this->addProposal(
                                                    $editedPollMessage->channel,
                                                    $message,
                                                    $proposalName,
                                                    $pollObject
                                                );
                                            });
                                        }
                                    }

                                    return $p->then(function() use ($message) {
                                        return $message->delete(0, "command");
                                    });
                                });

                        }
                    );
            });

        $commandPromise->then(
            null,
            function ($error) use ($message) {
                $this->log($message, "ERROR with the !poll command:");
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