<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\HTTP\Endpoints\Channel;
use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\User;
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
        6 => ["🤬", "😣", "😐", "🙂", "😀", "😍"],
        7 => ["🤬", "😫", "😒", "😐", "🙂", "😀", "😍"],
        8 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣"],
        9 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣"],
        10 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣"],
        // If you add more, remember to clamp $amountOfGrades accordingly below
    ];

    protected function getPollEmoji()
    {
        return "⚖️";
    }

    protected function getProposalEmoji()
    {
        return "⚖️";
    }

    protected function getErrorEmoji()
    {
        return "⛔️";
    }

    /**
     * Fetch, in sequence, all the messages of $channel with the ids $messagesIds.
     * If a message is not found, null is added.
     * This makes sequential requests to Discord.
     * The returned Promise in an array of Message|null.
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
                        },
                        function ($error) use (&$messages, $channel, $messagesId) {
                            $messages[] = null;

                            return $channel->fetchMessage($messagesId);
                        }
                    );
                }

                return $p->then(
                    function (?Message $message) use (&$messages) {
                        if (null !== $message) {
                            $messages[] = $message;
                        }
                        return $messages;
                    },
                    function ($error) use (&$messages) {
                        $messages[] = null;

                        return $messages;
                    }
                )
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

    protected function removePoll(int $pollId)
    {
        DB::table(Database::POLLS)->delete($pollId);
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
                'createdAt' => new \DateTime(),
            ]);

            if (empty($insertedId)) {
                printf("ERROR inserting a new proposal in the database.\n");
                return $reject("ERROR inserting a new proposal in the database.");
            }

            $result = DB::table(Database::PROPOSALS)->find($insertedId);

            if ($result) {
                //dump($result);

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

    /**
     * High-level method to add a proposal.
     * Used by the command !proposal and by presets.
     *
     * @param TextChannelInterface $channel
     * @param Message|null $triggerMessage
     * @param string $proposalName
     * @param int $pollId
     * @param int $amountOfGrades
     * @return PromiseInterface
     */
    protected function addProposal(TextChannelInterface $channel, ?Message $triggerMessage, string $proposalName, int $pollId, int $amountOfGrades) : PromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($channel, $triggerMessage, $proposalName, $pollId, $amountOfGrades) {

                $pollEmote = "⚖️";
                $proposalEmote = "📜";
                $messageBody = sprintf(
                    "%s `%d`  %s **%s**\n",
                    $pollEmote,
                    $pollId,
                    $proposalEmote,
                    $proposalName
                );

                $options = [
                    'embed' => [
                        'title' => $messageBody,
                    ]
                ];

                return $channel
                    ->send('', $options)
                    ->otherwise(function ($error) use ($proposalName) {
                        printf("ERROR failed to send a new message for the proposal `%s'..\n", $proposalName);
                        dump($error);
                    })
                    ->then(function (Message $proposalMessage) use ($resolve, $reject, $triggerMessage, $proposalName, $pollId, $amountOfGrades) {

                        $this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName, $pollId)
                            ->otherwise(function ($error) {
                                printf("ERROR when adding a proposal to the database:\n");
                                dump($error);
                            })
                            ->done(function ($dbProposal) {
                                printf("Wrote proposal to database.\n");
                            }, $reject);

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


    /**
     * A Toast is a message that will delete itself after a fashion.
     * Useful for documentation hints, error reports, etc.
     *
     * This method could go in a BaseCommand class or … a Service?  a Trait?
     *
     * @param TextChannelInterface $channel
     * @param Message|null $replyTo
     * @param string $content
     * @return ExtendedPromiseInterface
     */
    protected function sendToast(TextChannelInterface $channel, ?Message $replyTo, string $content, array $options, int $duration) : ExtendedPromiseInterface
    {
        // We create a Promise object because we want ExtendedPromiseInterface, not PromiseInterface
        // Perhaps there is another way…?
        return new Promise(function ($resolve, $reject) use ($channel, $replyTo, $content, $options, $duration) {

            if (empty($replyTo)) {
                $toastSent = $channel->send($content, $options);
            } else {
                $toastSent = $replyTo->reply($content, $options);
            }

            return $toastSent
                ->otherwise($reject)
                ->then(function (Message $toast) use ($resolve, $reject, $duration) {
                    return $toast
                        ->delete($duration, "toast")
                        ->otherwise($reject)
                        ->then($resolve);
                });

        });
    }

    /**
     * I am bot.  Am I $user ?
     *
     * @param User $user
     * @return bool
     */
    protected function isMe(?User $user)
    {
        return !empty($user) && $user === $user->client->user;
    }

}
