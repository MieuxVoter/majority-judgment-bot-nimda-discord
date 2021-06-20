<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Models\Message;
use Nimda\Core\Command;
use Nimda\Core\Database;
use Nimda\DB;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

/**
 * Common code to our various poll-related commands.
 *
 * Class PollCommand
 * @package Nimda\Core\Commands
 */
abstract class PollCommand extends Command
{
    // From worst to best, for each grading size. Let's hope 10 grades is enough.
    // Would also love literal grades like "Reject", "Passable", etc.  Gotta do what we can.
    // The numbers are unicode, not the usual ASCII.
    protected $gradesEmotes = [
        2 => ["👎", "👍"],
        3 => ["👎", "👊", "👍"],
        4 => ["🤬", "😐", "🙂", "😍"],
        5 => ["🤬", "😣", "😐", "🙂", "😍"],
        6 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣"],
        7 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣"],
        8 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣"],
        9 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣"],
        10 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣"],
        // If you add more, remember to clamp $amountOfGrades accordingly below
    ];

    protected function getMessages(TextChannelInterface $channel, array $messagesIds) : ExtendedPromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($channel, $messagesIds) {

                $messages = [];
                $p = resolve(null);

                foreach ($messagesIds as $messagesId) {
                    $p = $p->then(
                        function (?Message $message) use (&$messages, $channel, $messagesId) {
                            if (null !== $message) {
                                $messages[] = $message;
                            }

                            return $channel->fetchMessage($messagesId);
                        }
                    );
                }

                $p->then(function (?Message $message) use (&$messages) {
                    if (null !== $message) {
                        $messages[] = $message;
                    }
                    return $messages;
                })
                ->done(
                    function (array $messages) use ($resolve) {
                        printf("Done fetching %d messages\n", count($messages));
//                        dump($messages);
                        $resolve($messages);
                    },
                    $reject
                );

            }
        );
    }

    /**
     * The Promise yields an object, not an array.
     * Use $dbPoll->id for example, not $dbPöll['id']
     * It holds the table columns as properties:
     * +"id": "1"
     * +"author_id": "238596624908025856"
     * +"channel_id": "855665583869919233"
     * +"created_at": null // these are not set, it appears
     * +"updated_at": null
     * See Database.php for the complete reference.
     *
     * @param Message $triggerMessage
     * @param Message $pollMessage
     * @return ExtendedPromiseInterface
     */
    protected function addPollToDb(Message $triggerMessage, Message $pollMessage) : ExtendedPromiseInterface
    {
        return new Promise(function($resolve) use ($triggerMessage, $pollMessage) {

            $insertedId = DB::table(Database::POLLS)->insertGetId([
                'author_id' => $triggerMessage->author->id,
                'channel_id' => $triggerMessage->channel->getId(),
                'message_id' => $pollMessage->id,
                'trigger_message_id' => $triggerMessage->id,
            ]);

            $result = DB::table(Database::POLLS)
                ->where('id', $insertedId)
                ->first();

            if ($result) {
//                dump("POLL from DB");
//                dump($result);
                //+"id": "1"
                //+"author_id": "238596624908025856"
                //+"channel_id": "855665583869919233"
                //+"created_at": null
                //+"updated_at": null

                return $resolve($result);
            }

            // $reject ?  throw ?
            return $resolve(false);

        });
    }

    /**
     * The Promise yields an object, not an array.
     * Use $dbProposal->id for example, not $dbProposal['id']
     * It holds the table columns as properties:
     * See Database.php for the complete reference.
     *
     * @param Message $triggerMessage
     * @param Message $proposalMessage
     * @param string $proposalName
     * @return ExtendedPromiseInterface
     */
    protected function addProposalToDb(?Message $triggerMessage, Message $proposalMessage, string $proposalName, int $pollId) : ExtendedPromiseInterface
    {
        return new Promise(function($resolve, $reject) use ($triggerMessage, $proposalMessage, $proposalName, $pollId) {

            printf("Trying to write a new proposal `%s' into the database…\n", $proposalName);

            $insertedId = DB::table(Database::PROPOSALS)->insertGetId([
                'poll_id' => $pollId,
                'author_id' => $triggerMessage ? $triggerMessage->author->id : null,
                'channel_id' => $proposalMessage->channel->getId(),
                'message_id' => $proposalMessage->id,
                'trigger_message_id' => $triggerMessage ? $triggerMessage->id : null,
                'name' => $proposalName,
            ]);

            if (empty($insertedId)) {
                printf("ERROR inserting a new proposal in the database.\n");
                return $reject("ERROR inserting a new proposal in the database.");
            }

            $result = DB::table(Database::PROPOSALS)
                ->where('id', $insertedId)
                ->first();

            if ($result) {
                dump($result);

                return $resolve($result);
            }

            return $reject("ERROR could not find the proposal we supposedly added.");
        });
    }

    protected function getDbProposalsForPoll(int $pollId) : ExtendedPromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($pollId) {

                $resultsQuery = DB::table(Database::PROPOSALS)
                    ->where('poll_id', $pollId)
                    ->limit(32) // fixme: hard limit to move to ENV and $config
                ;

//                dump($resultsQuery->toSql());

                $results = $resultsQuery->get();

                return $resolve($results);
            }
        );
    }

    protected function addProposal(?Message $triggerMessage, Message $pollMessage, string $proposalName, int $pollId, int $amountOfGrades) : PromiseInterface
    {
        return new Promise(
            function ($resolve) use ($triggerMessage, $pollMessage, $proposalName, $pollId, $amountOfGrades) {

                $messageBody = sprintf(
                    "**%s**\n",
                    $proposalName
                );

                return $pollMessage
                    ->channel->send($messageBody)
                    ->otherwise(function ($error) use ($proposalName) {
                        printf("ERROR failed to send a new message for the proposal `%s'..\n", $proposalName);
                        dump($error);
                    })
//                    ->then(function (Message $proposalMessage) use ($triggerMessage, $pollMessage, $proposalName, $amountOfGrades) {
//                        return resolve($this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName));
//                    })
                    ->then(function (Message $proposalMessage) use ($resolve, $triggerMessage, $pollMessage, $proposalName, $pollId, $amountOfGrades) {

                        $this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName, $pollId)
                            ->otherwise(function ($error) {
                                printf("ERROR when adding a proposal to the database:\n");
                                dump($error);
                            })
                            ->then(function ($dbProposal) {
                                printf("Wrote proposal to database.\n");
                            });
//                        try {
//                        } catch (\Exception $e) {
//                            printf("ERROR: failed to write to database.\n");
//                            dump($e);
//                        }

                        return $proposalMessage->client->addTimer(5, function () use ($resolve, $proposalMessage, $proposalName, $amountOfGrades) {
                            return $this->addGradingReactions($proposalMessage, $amountOfGrades)
                                ->then(
                                    function() use ($resolve, $proposalMessage) {
                                        printf("Done adding reactions for proposal `%s'.\n", $proposalMessage->content);
                                        $resolve($proposalMessage);
                                    },
                                    function ($error) use ($proposalName) {
                                        printf("ERROR adding grade reactions to `%s':\n", $proposalName);
                                        dump($error);
                                    }
                                );
                        });
                    });

            }
        );
    }

    /**
     * Add $gradingSize different reactions to $message
     *
     * @param Message $message
     * @param int $gradingSize
     * @return PromiseInterface
     */
    protected function addGradingReactions(Message $message, int $gradingSize) : PromiseInterface
    {
        $gradesEmotes = $this->gradesEmotes[$gradingSize];
        $promises = [];

        for ($gradeIndex = 0; $gradeIndex < $gradingSize; $gradeIndex++) {
            $gradeEmote = $gradesEmotes[$gradeIndex];
            $reactionAdditionPromise = new Promise(
                function ($resolve) use ($message, $gradeEmote) {
                    // API throttling is a thing, let's cool our horses
                    return $message->client->addTimer(5, function () use ($resolve, $message, $gradeEmote) {
                        $resolve($message->react($gradeEmote));
                    });
                }
            );
            $promises[] = $reactionAdditionPromise;
        }

        return all($promises);
    }

}