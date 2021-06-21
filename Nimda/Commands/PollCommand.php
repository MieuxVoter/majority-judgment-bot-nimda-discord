<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
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

    /**
     * Fetch, in sequence, all the messages of $channel with the ids $messagesIds.
     * This makes sequential requests to Discord.
     * The returned Promise in an array of Message.
     *
     * @param TextChannelInterface $channel
     * @param array $messagesIds
     * @return ExtendedPromiseInterface
     */
    protected function getMessages(TextChannelInterface $channel, array $messagesIds) : ExtendedPromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($channel, $messagesIds) {

                $messages = [];

                if (empty($messagesIds)) {
                    printf("No messages to fetch.\n");
                    return $resolve($messages);
                }

                printf("Starting to fetch %d messages…\n", count($messagesIds));

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

                return $p->then(function (?Message $message) use (&$messages) {
                    if (null !== $message) {
                        $messages[] = $message;
                    }
                    return $messages;
                })
                ->done(
                    function (array $messages) use ($resolve) {
                        printf("Done fetching %d messages.\n", count($messages));
                        //dump($messages);  // flood
                        return $resolve($messages);
                    },
                    $reject
                );

            }
        );
    }

    /**
     * @param int $pollId
     * @return object|null
     */
    protected function findPollById(int $pollId)
    {
        $result = DB::table(Database::POLLS)->find($pollId);

        if (empty($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Note that the found poll may have a deleted poll message, and therefore may not be usable.
     * This is because we don't listen for deletion events (yet) and do not update the database.
     * And even when we will, you should not blindly trust this poll to have a message_id that exists,
     * since the bot may not be online 100% of the time and may skip a deletion event for any reason.
     *
     * @param ChannelInterface $channel
     * @return int The database identifier of the found poll, or zero.
     */
    protected function getLatestPollIdOfChannel(ChannelInterface $channel) : int
    {
        $result = DB::table(Database::POLLS)
            ->select('id')
            ->where('channelId', $channel->getId())
            ->orderByDesc('id')
            ->first();

        if (empty($result)) {
            return 0;
        }

        return (int) $result->id;
    }

    /**
     * The Promise yields an object, not an array.
     * Use $dbPoll->id for example, not $dbPöll['id']
     * It holds the table columns as properties:
     * +"id": "1"
     * +"authorId": "238596624908025856"
     * +"channelId": "855665583869919233"
     * +"createdAt": null
     * +"updatedAt": null // not used right now
     * See Database.php for the complete reference.
     *
     * @param Message $triggerMessage
     * @param Message $pollMessage
     * @param $subject
     * @param $amountOfGrades
     * @return ExtendedPromiseInterface
     */
    protected function addPollToDb(Message $triggerMessage, Message $pollMessage, $subject, $amountOfGrades) : ExtendedPromiseInterface
    {
        return new Promise(function($resolve, $reject) use ($triggerMessage, $pollMessage, $subject, $amountOfGrades) {

            $insertedId = DB::table(Database::POLLS)->insertGetId([
                'authorId' => $triggerMessage->author->id,
                'channelId' => $triggerMessage->channel->getId(),
                'messageId' => $pollMessage->id,
                'triggerMessageId' => $triggerMessage->id,
                'subject' => $subject,
                'amountOfGrades' => $amountOfGrades,
                'createdAt' => new \DateTime(),
            ]);

            $result = DB::table(Database::POLLS)->find($insertedId);

            if ($result) {
//                dump($result);
                return $resolve($result);
            }

            return $reject();
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
                'pollId' => $pollId,
                'authorId' => $triggerMessage ? $triggerMessage->author->id : null,
                'channelId' => $proposalMessage->channel->getId(),
                'messageId' => $proposalMessage->id,
                'triggerMessageId' => $triggerMessage ? $triggerMessage->id : null,
                'name' => $proposalName,
            ]);

            if (empty($insertedId)) {
                printf("ERROR inserting a new proposal in the database.\n");
                return $reject("ERROR inserting a new proposal in the database.");
            }

            $result = DB::table(Database::PROPOSALS)->find($insertedId);

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
                    ->where('pollId', '=', $pollId)
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
            function ($resolve, $reject) use ($triggerMessage, $pollMessage, $proposalName, $pollId, $amountOfGrades) {

                $emote = "📜";
                $messageBody = sprintf(
                    "%s **%s**\n",
                    $emote,
                    $proposalName
                );

                $options = [
                    'embed' => [
                        'title' => $messageBody,
                    ]
                ];

                return $pollMessage
                    ->channel->send('', $options)
                    ->otherwise(function ($error) use ($proposalName) {
                        printf("ERROR failed to send a new message for the proposal `%s'..\n", $proposalName);
                        dump($error);
                    })
//                    ->then(function (Message $proposalMessage) use ($triggerMessage, $pollMessage, $proposalName, $amountOfGrades) {
//                        return resolve($this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName));
//                    })
                    ->then(function (Message $proposalMessage) use ($resolve, $reject, $triggerMessage, $pollMessage, $proposalName, $pollId, $amountOfGrades) {

                        $this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName, $pollId)
                            ->otherwise(function ($error) {
                                printf("ERROR when adding a proposal to the database:\n");
                                dump($error);
                            })
                            ->done(function ($dbProposal) {
                                printf("Wrote proposal to database.\n");
                            }, $reject);

//                        return $proposalMessage->client->addTimer(5, function () use ($resolve, $proposalMessage, $proposalName, $amountOfGrades) {
                        return $this
                            ->addGradingReactions($proposalMessage, $amountOfGrades)
                            ->then(
                                function() use ($resolve, $proposalName, $proposalMessage) {
                                    printf("Done adding urn reactions for proposal `%s'.\n", $proposalName);
                                    return $resolve($proposalMessage);
                                },
                                function ($error) use ($reject, $proposalName) {
                                    printf("ERROR adding urn reactions to proposal `%s':\n", $proposalName);
                                    dump($error);
                                    return $reject($error);
                                }
                            );
//                        });
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