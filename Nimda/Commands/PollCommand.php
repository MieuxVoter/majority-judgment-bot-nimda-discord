<?php declare(strict_types=1);

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Models\User;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query;
use Exception;
use Nimda\Core\Command;
use Nimda\Core\Database;
use Nimda\Core\Logger;
use Nimda\Entity\Channel;
use Nimda\Entity\Poll;
use Nimda\Entity\Proposal;
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
    #   _____             __ _
    #  / ____|           / _(_)
    # | |     ___  _ __ | |_ _  __ _
    # | |    / _ \| '_ \|  _| |/ _` |
    # | |___| (_) | | | | | | | (_| |
    #  \_____\___/|_| |_|_| |_|\__, |
    #                           __/ |
    #                          |___/

    // From worst to best, for each grading size. Let's hope 10 grades is enough.
    // Would also love literal grades like "Reject", "Passable", etc.  Gotta do what we can.
    // The numbers are unicode, not the usual ASCII.
    protected array $gradesEmotes = [
        2 => ["👎", "👍"],
        3 => ["👎", "👊", "👍"],
        4 => ["🤬", "😐", "🙂", "😍"],
        5 => ["🤬", "😣", "😐", "🙂", "😍"],
        6 => ["🤬", "😣", "😐", "🙂", "😀", "😍"],
        7 => ["🤬", "😫", "😒", "😐", "🙂", "😀", "😍"],
        8 => ["🤬", "😫", "😒", "😐", "🙂", "😊", "😀", "😍"],
        9 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣"],
        10 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣"],
        // If you add more, remember to clamp $amountOfGrades accordingly below
    ];

    protected function getPollEmoji() : string
    {
        return "⚖️";
    }

    protected function getProposalEmoji() : string
    {
        return "⚖️";
    }

    protected function getErrorEmoji() : string
    {
        return "⛔️";
    }

    // Eventually, move these presets to database, and allow admins to set their own.
    protected array $presets = [
        "/^week$/ui" => [
            'subject' => "Which day of the week?",
            'proposals' => [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday",
            ],
        ],
        "/^week5$/ui" => [
            'subject' => "Which day of the week?",
            'proposals' => [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
            ],
        ],
        "/^semaine$/ui" => [
            'subject' => "Quel jour de la semaine ?",
            'proposals' => [
                "Lundi",
                "Mardi",
                "Mercredi",
                "Jeudi",
                "Vendredi",
                "Samedi",
                "Dimanche",
            ],
        ],
        "/^semaine5$/ui" => [
            'subject' => "Quel jour de la semaine ?",
            'proposals' => [
                "Lundi",
                "Mardi",
                "Mercredi",
                "Jeudi",
                "Vendredi",
            ],
        ],
    ];


    #  _____  _                       _    ____                        _
    # |  __ \(_)                     | |  / __ \                      (_)
    # | |  | |_ ___  ___ ___  _ __ __| | | |  | |_   _  ___ _ __ _   _ _ _ __   __ _
    # | |  | | / __|/ __/ _ \| '__/ _` | | |  | | | | |/ _ \ '__| | | | | '_ \ / _` |
    # | |__| | \__ \ (_| (_) | | | (_| | | |__| | |_| |  __/ |  | |_| | | | | | (_| |
    # |_____/|_|___/\___\___/|_|  \__,_|  \___\_\\__,_|\___|_|   \__, |_|_| |_|\__, |
    #                                                             __/ |         __/ |
    #                                                            |___/         |___/

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
                    Logger::warn("No messages to fetch.");
                    return $resolve($messages);
                }

                Logger::debug(sprintf("Starting to fetch %d messages…", count($messagesIds)));

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
                        Logger::debug(sprintf("Done fetching %d messages.", count($messages)));
                        //dump($messages);  // flood
                        return $resolve($messages);
                    },
                    $reject
                );

            }
        );
    }

    #  _____        _        _
    # |  __ \      | |      | |
    # | |  | | __ _| |_ __ _| |__   __ _ ___  ___
    # | |  | |/ _` | __/ _` | '_ \ / _` / __|/ _ \
    # | |__| | (_| | || (_| | |_) | (_| \__ \  __/
    # |_____/ \__,_|\__\__,_|_.__/ \__,_|___/\___|
    #
    #

    /**
     * @param int $pollId
     * @return Poll|object|null
     */
    protected function findPollById(int $pollId) : ?Poll
    {
        /** @var ?Poll $poll */
//        $poll = Database::repo(Poll::class)->find($pollId); // we want to force a refresh instead
        $qb = Database::repo(Poll::class)->createQueryBuilder('p')
            ->where('p.id = :id')
            ->setParameter('id', $pollId);
        try {
            $poll = $qb->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        } catch (NonUniqueResultException $e) {
            Logger::error("Multiple polls with ID `%s'!", $pollId);
            return null;
        }

        if (empty($poll)) {
            return null;
        }

        return $poll;
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
        $result = null;
        try {
            $result = Database::repo(Poll::class)
                ->createQueryBuilder('p')
                ->select('p.id')
                ->where('p.channelVendorId = :channelVendorId')
                ->setParameter("channelVendorId", $channel->getId())
                ->orderBy('p.id', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
        } catch (NonUniqueResultException $e) {
            return 0;
        }

        if (empty($result)) {
            return 0;
        }

        return (int) $result['id'];
    }

    /**
     * The Promise yields a Poll instance.
     * See Database.php for the complete reference.
     *
     * @param Message $triggerMessage
     * @param Message $pollMessage
     * @param Channel $dbChannel
     * @param $subject
     * @param $amountOfGrades
     * @return ExtendedPromiseInterface
     */
    protected function addPollToDb(Message $triggerMessage, Message $pollMessage, Channel $dbChannel, $subject, $amountOfGrades) : ExtendedPromiseInterface
    {
        return new Promise(function($resolve, $reject) use ($triggerMessage, $pollMessage, $dbChannel, $subject, $amountOfGrades) {

            $poll = new Poll();
            $poll
                ->setChannel($dbChannel)
                ->setSubject($subject)
                ->setAmountOfGrades($amountOfGrades)
                ->setAuthorVendorId($triggerMessage->author->id)
                ->setChannelVendorId($triggerMessage->channel->getId())
                ->setMessageVendorId($pollMessage->id)
                ->setTriggerMessageVendorId($triggerMessage->id)
            ;

            try {
                Database::$entityManager->persist($poll);
                Database::$entityManager->flush();
            } catch (Exception $exception) {
                return $reject($exception);
            }

            assert(!empty($poll), "Poll is empty after initial persist()");

            return $resolve($poll);
        });
    }

    /**
     * sugar overdose, perhaps
     *
     * @param Poll|null $poll
     * @throws ORMException
     */
    protected function removePollFromDb(?Poll $poll)
    {
        if (empty($poll)) {
            return;
        }

        Database::$entityManager->remove($poll);
    }

    /**
     * The Promise yields a Proposal instance.
     *
     * @param Message $triggerMessage
     * @param Message $proposalMessage
     * @param string $proposalName
     * @param Poll $poll
     * @return ExtendedPromiseInterface
     */
    protected function addProposalToDb(?Message $triggerMessage, Message $proposalMessage, string $proposalName, Poll $poll) : ExtendedPromiseInterface
    {
        return new Promise(
            function($resolve, $reject) use ($triggerMessage, $proposalMessage, $proposalName, $poll) {

                $this->log($triggerMessage, "Trying to write a new proposal `%s' into the database…\n", $proposalName);

                $proposal = new Proposal();
                $proposal
                    ->setPoll($poll)
                    ->setName($proposalName)
                    ->setChannelVendorId($proposalMessage->channel->getId())
                    ->setAuthorVendorId($triggerMessage ? $triggerMessage->author->id : null)
                    ->setMessageVendorId($proposalMessage->id)
                    ->setTriggerMessageVendorId($triggerMessage ? $triggerMessage->id : null)
                ;

                try {
                    Database::$entityManager->persist($proposal);
                    Database::$entityManager->flush();
                } catch (Exception $exception) {
                    return $reject($exception);
                }

                return $resolve($proposal);
            }
        );
    }

    protected function getDbProposalsForPoll(Poll $poll) : ExtendedPromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($poll) {
                return $resolve($poll->getProposals()->toArray());
            }
        );
    }

    protected function findDbChannel(?TextChannelInterface $channel) : ?Channel
    {
        if (null === $channel) {
            return null;
        }

        /** @var Channel $dbChannel */
        $dbChannel = Database::repo(Channel::class)->findOneBy([
            'discordId' => $channel->getId(),
        ]);

        return $dbChannel;
    }

    protected function isChannelJoined(?TextChannelInterface $channel) : bool
    {
        if (null === $channel) {
            return false;
        }

        $dbChannel = $this->findDbChannel($channel);

        if (null === $dbChannel) {
            return false;
        }

//        if ($dbChannel->isBanned()) {
//            return false;
//        }

        return true;
    }

    protected function findMatchingPreset(Message $message, string $subject) : ?array
    {
        $presets = $this->presets;  // fetch from database eventually
        foreach ($presets as $regex => $preset) {
            if (1 !== preg_match($regex, $subject)) {
                continue;
            }

            return $preset;
        }

        return null;
    }

    #  _    _ _       _       _                    _
    # | |  | (_)     | |     | |                  | |
    # | |__| |_  __ _| |__   | |     _____   _____| |
    # |  __  | |/ _` | '_ \  | |    / _ \ \ / / _ \ |
    # | |  | | | (_| | | | | | |___|  __/\ V /  __/ |
    # |_|  |_|_|\__, |_| |_| |______\___| \_/ \___|_|
    #            __/ |
    #           |___/

    protected function remindThatJoinIsRequired(Message $triggerMessage)
    {
        return $this->sendToast(
            $triggerMessage->channel,
            $triggerMessage,
            "Are you trying to give me a command?\n".
            "If so, type `!join` to let me in this channel.\n".
            "",
            [],
            10
        );
    }

    /**
     * High-level method to add a proposal.
     * Used by the command !proposal and by presets.
     *
     * @param TextChannelInterface $channel
     * @param Message|null $triggerMessage
     * @param string $proposalName
     * @param Poll $poll
     * @return PromiseInterface
     */
    protected function addProposal(TextChannelInterface $channel, ?Message $triggerMessage, string $proposalName, Poll $poll) : PromiseInterface
    {
        return new Promise(
            function ($resolve, $reject) use ($channel, $triggerMessage, $proposalName, $poll) {

                $proposalEmote = "📜";
                $messageBody = sprintf(
                    "%s `%d`  %s **%s**\n",
                    $this->getPollEmoji(),
                    $poll->getId(),
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
                    ->otherwise(function ($error) use ($proposalName, $triggerMessage) {
                        $this->log(
                            $triggerMessage,
                            "ERROR failed to send a new message for the proposal `%s'..",
                            $proposalName
                        );
                        dump($error);
                    })
                    ->then(function (Message $proposalMessage) use ($resolve, $reject, $triggerMessage, $proposalName, $poll) {

                        $this->addProposalToDb($triggerMessage, $proposalMessage, $proposalName, $poll)
//                            ->otherwise(function ($error) use ($reject) {
//                                printf("ERROR when adding a proposal to the database:\n");
//                                dump($error);
//                                return $reject($error);
//                            })
                            ->done(function (Proposal $dbProposal) use ($triggerMessage) {
                                $this->log($triggerMessage,
                                    "Wrote proposal #%d `%s' to database.",
                                    $dbProposal->getId(), $dbProposal->getName()
                                );
                            }, $reject);

                        return $this
                            ->addGradingReactions($proposalMessage, $poll->getAmountOfGrades())
                            ->then(
                                function() use ($resolve, $triggerMessage, $proposalName, $proposalMessage) {
                                    $this->log(
                                        $triggerMessage,
                                        "Done adding urn reactions for proposal `%s'.",
                                        $proposalName
                                    );
                                    return $resolve($proposalMessage);
                                },
                                function ($error) use ($reject, $triggerMessage, $proposalName) {
                                    $this->log(
                                        $triggerMessage,
                                        "ERROR adding urn reactions to proposal `%s':",
                                        $proposalName
                                    );
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

        $p = resolve();

        for ($gradeIndex = 0; $gradeIndex < $gradingSize; $gradeIndex++) {
            $gradeEmote = $gradesEmotes[$gradeIndex];

            $p = $p->then(
                function () use ($gradeEmote, $message) {
                    return new Promise(
                        function ($resolve) use ($message, $gradeEmote) {
                            // API throttling is a thing, let's cool our horses
                            return $message->client->addTimer(1, function () use ($resolve, $message, $gradeEmote) {
                                $resolve($message->react($gradeEmote));
                            });
                        }
                    );
                }
            );

        }

        return $p;
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
     * @param array $options
     * @param int $duration
     * @return ExtendedPromiseInterface
     */
    protected function sendToast(
        TextChannelInterface $channel,
        ?Message $replyTo,
        string $content,
        array $options,
        int $duration
    ) : ExtendedPromiseInterface {

        // We create a Promise object mainly because we want ExtendedPromiseInterface, not PromiseInterface
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
                    if ($duration >= 0) {
                        return $toast
                            ->delete($duration, "toast")
                            ->otherwise($reject)
                            ->then($resolve);
                    } else {
                        return $resolve($toast);
                    }
                });

        });
    }

    #  _    _ _   _ _
    # | |  | | | (_) |
    # | |  | | |_ _| |___
    # | |  | | __| | / __|
    # | |__| | |_| | \__ \
    #  \____/ \__|_|_|___/
    #

    /**
     * I am bot.  Am I $user ?
     *
     * @param User $user
     * @return bool
     */
    protected function isMe(?User $user) : bool
    {
        return !empty($user) && $user === $user->client->user;
    }

    protected function isMentioningMe(Message $message) : bool
    {
        return in_array($message->client->user, $message->mentions->users->all());
    }

    protected function isMessageActorAdmin(Message $message) : bool
    {
        foreach ($message->member->roles->all() as $role) {
            /** @var Role $role */
            if ($role->permissions->has(Permissions::PERMISSIONS['ADMINISTRATOR'])) {
                return true;
            }
        }

        return false;
    }

    protected function shouldShowDebug() : bool
    {
        return getenv("APP_ENV") !== "prod";
    }

}
