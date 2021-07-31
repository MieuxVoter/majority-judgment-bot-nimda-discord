<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageEmbed;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use Exception;
use Illuminate\Support\Collection;
use MieuxVoter\MajorityJudgment\MajorityJudgmentDeliberator;
use MieuxVoter\MajorityJudgment\Model\Result\ProposalResult;
use MieuxVoter\MajorityJudgment\Model\Tally\TwoArraysPollTally;
use Nimda\Core\Logger;
use Nimda\Entity\Proposal;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Throwable;
use Wa72\Url\Url;
use function React\Promise\reject;

/**
 * This command collects the tallies and displays the merit profiles for a specific poll.
 *
 * Class ResolvePoll
 * @package Nimda\Core\Commands
 */
final class ResolvePoll extends PollCommand
{

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;
        //$actor = $message->author;

        if ( ! $this->isChannelJoined($channel)) {
            Logger::warn("Trying to use !result on a non-joined channel.");
            return reject();
        }

        $pollId = $args->get('pollId');
        if (empty($pollId)) {
            Logger::debug("Guessing the poll identifier…");
            try {
                $pollId = $this->getLatestPollIdOfChannel($channel);
            } catch (Exception $exception) {
                Logger::error(sprintf("ERROR failed to fetch the latest poll id of channel `%s'.", $channel));
                dump($exception);
                return reject();
            }

            if (0 === $pollId) {
                return reject($this->sendToast(
                    $channel, $message,
                    "There are no polls on this channel to display the results of.\n".
                    "If you'd like me to be able to display the results of polls of other channels,\n".
                    "it can be done ; please open a ticket in our tracker,\n",
                    [],
                    -1
                ));
            }
        } else {
            $pollId = (int) $pollId;
        }

        $message->delete(10, "command");

        $channel->startTyping();

        $poll = null;
        $pollIsValid = true;
        try {
            $poll = $this->findPollById($pollId);
        } catch (Exception $exception) {
            Logger::error("ERROR: findPollById threw:");
            dump($exception);
            $pollIsValid = false;
        }

        if (empty($poll)) {
            $pollIsValid = false;
        } else if ( ! $poll->getChannel()->isChannel($channel)) {
            $pollIsValid = false;
        }

        if ( ! $pollIsValid) {
            Logger::warn(sprintf(
                "%s no poll found with id `%s' in channel `%s'.",
                $message->author->username, $pollId, $channel->getId()
            ));
            $channel->stopTyping();
            return reject($this->sendToast(
                $channel, $message,
                "The poll was not found on this channel.  Try specifying the poll IDentifier with `!result ID`?",
                [],
                10
            ));
        }

        $dbProposalsPromise = $this->getDbProposalsForPoll($poll);
        $commandPromise = $dbProposalsPromise
            ->then(
                function ($dbProposals) use ($channel, $message) {
                    Logger::debug(sprintf("Found %d proposals in the database.", count($dbProposals)));

                    return new Promise(
                        function ($resolve, $reject) use ($channel, $dbProposals) {

                            $proposalsMessagesIds = array_map(function (Proposal $dbProposal) {
                                return $dbProposal->getMessageVendorId();
                            }, $dbProposals);

                            $this
                                ->getMessages($channel, $proposalsMessagesIds)
                                ->done(
                                    function (array $messages) use ($resolve, $dbProposals) {
                                        // Filter out messages that probably were deleted and we failed to fetch
                                        $validProposals = array_filter($dbProposals, function($key) use ($messages) {
                                            return ! empty($messages[$key]);
                                        }, ARRAY_FILTER_USE_KEY);
                                        $validMessages = array_filter($messages, function($message) {
                                            return ! empty($message);
                                        });
                                        $resolve([
                                            array_values($validProposals),
                                            array_values($validMessages),
                                        ]);
                                    },
                                    $reject
                                );
                        }
                    );
                }
            )
            ->then(
                function ($things) use ($channel, $message, $poll) {
                    /** @var Message[] $proposalsMessages */
                    [$proposalsObjects, $proposalsMessages] = $things;

//                    $amountOfProposals = count($proposalsMessages);

                    Logger::debug(sprintf("Got %d messages.", count($proposalsMessages)));

                    $amountOfProposals = count($proposalsObjects);
                    $amountOfParticipants = 0;
                    $pollTally = [];
                    $gradesEmotes = $this->gradesEmotes[$poll->getAmountOfGrades()];
                    foreach ($proposalsMessages as $proposalsMessage) {
                        /** @var MessageReaction[] $reactions */
                        $reactions = array_filter(
                            $proposalsMessage->reactions->all(),
                            function (MessageReaction $reaction) use ($poll, $gradesEmotes) {
                                return in_array($reaction->emoji, $gradesEmotes);
                            }
                        );
                        // Some reactions may have been deleted by admins, we'll set their tally to 0 below
                        $reactionsByEmoji = [];
                        foreach ($reactions as $reaction) {
                            $reactionsByEmoji[(string) $reaction->emoji] = $reaction;
                        }

                        $proposalTally = [];
                        $amountOfJudgesOfProposal = 0;
                        foreach ($gradesEmotes as $gradeEmote) {
                            if ( ! isset($reactionsByEmoji[$gradeEmote])) {
                                $proposalTally[] = 0;
                                continue;
                            }

                            $reaction = $reactionsByEmoji[$gradeEmote];

                            // fixme: curate to ensure 1 user == 1 reaction
                            // This is tricky, since fetching all the users of all the reactions
                            // is going to take a long time (sequential, paginated requests).
                            // It's also not going to scale well, compared to this naive usage of `->count`.
                            // We should probably offer both, this one first and then edit it with curated results?
                            // Or make another command, or a command parameter?

                            $amountOfJudgesOfGrade = $reaction->count - 1;  // minus the bootstrap reaction of the bot
                            $amountOfJudgesOfProposal += $amountOfJudgesOfGrade;

                            $proposalTally[] = $amountOfJudgesOfGrade;
                        }
                        // Same: getting the amount of judges from the max() is incorrect but fast
                        $amountOfParticipants = max($amountOfParticipants, $amountOfJudgesOfProposal);

                        $pollTally[] = $proposalTally;
                    }

                    // This deliberator is set to use the worst grade as the default grade.
                    $deliberator = new MajorityJudgmentDeliberator();
                    $result = $deliberator->deliberate(new TwoArraysPollTally(
                        $amountOfParticipants,
                        $proposalsObjects,
                        $pollTally
                    ));

                    $leaderboard = $result->getProposalResults();

                    // Cheat and use the OpenAPI to render the merit profile
                    $tallyString = join('_', array_map(function (ProposalResult $proposalResult){
                        return join('-', $proposalResult->getTally());
                    }, $leaderboard));

                    // We could also use Miprem directly here @roipoussiere
                    $imgUrl = Url::parse(sprintf("https://oas.mieuxvoter.fr/%s.png", $tallyString))
                        ->setQueryParameter("w", 800)
                        ->setQueryParameter("h", floor(max(494, $amountOfProposals * 40 + 40)))
                    ;
                    $imgUrl = $imgUrl->setQueryParameter('proposals', array_map(function(ProposalResult $proposalResult) {
                        return $proposalResult->getProposal()->getName();
                    }, $leaderboard));

                    $description = "";
                    if (0 === count($leaderboard)) {
                        $description = "⚒ This poll has no proposals attached to it.\n" .
                            "You may add proposals to this poll with the following command:\n" .
                            sprintf("`!proposal %d My amazing proposal`", $poll->getId());
                    }

                    foreach ($leaderboard as $proposalResult) {
                        switch ($proposalResult->getRank()) {
                            case 1:
                                $description .= "🏆 ";  // victory cup
                                break;
                            default:
                                $description .= "🏅 ";  // participation award :)
                        }
                        $description .= sprintf(
                            "**`%d`** ➡️ %s \n",
                            $proposalResult->getRank(),
                            $proposalResult->getProposal()->getName()
                        );
                    }

                    $embed = new MessageEmbed([
                        'title' => sprintf(
                            "%s `#%d` — %s",
                            $this->getPollEmoji(),
                            $poll->getId(),
                            $poll->getSubject()
                        ),
                        'description' => $description,
                        'image' => [
                            'url' => (string) $imgUrl,
                            'width' => 810,
                            'height' => 500,
                        ],
                    ]);

                    $messageBody = sprintf(
                        "" // perhaps we could add extra metadata in here, such as the amount of judges, etc.
                    );

                    return $channel
                        ->send($messageBody, [
                            'embed' => $embed,
//                            'files' => [
//                                ($imgUrl),
//                            ],
                        ])
                        ->otherwise(
                            function($error) use ($message) {
                                Logger::error("ERROR sending the result:");
                                dump($error);
                                //return $error;
                            }
                        );

                }
            );

        $commandPromise->then(
            function ($thing) use ($channel) {
                $channel->stopTyping();
                return $thing;
            },
            // This ought to be refactored through all commands
            function ($error) use ($channel, $message) {
                Logger::error("ERROR with the !result command:");
                dump($error);
                $insecureButHandy = "";
                if ($this->shouldShowDebug() && ($error instanceof Throwable)) {
                    $insecureButHandy = $error->getMessage();
                }
                $channel->stopTyping();
                return ($this->sendToast(
                    $channel, $message,
                    "😱 Ooooops!  An error occurred!  _Please contact the 🤖 bot admin._ \n ".
                    "You may also report an issue.\n".
                    "https://github.com/MieuxVoter/majority-judgment-bot-nimda-discord/issues\n".
                    "\n".
                    (
                        ($insecureButHandy) ? (
                            "Please provide the following data:\n".
                            sprintf("```\n%s\n```\n", $insecureButHandy).
                            ""
                        ) : "_Tip_: run the bot with `APP_ENV=dev` to see more information about the error here.\n"
                    ).
                    "",
                    [
                        // Trying to bypass the unwarranted Notice
                        // PHP Notice:  Undefined index: url in vendor/laravel-discord/yasmin/src/Models/MessageEmbed.php on line 173
//                        'embed' => [
//                            'url' => "https://github.com/MieuxVoter/majority-judgment-bot-nimda-discord/issues",
//                        ],
                    ], 300
                ));
            }
        );

        return $commandPromise;
    }

}