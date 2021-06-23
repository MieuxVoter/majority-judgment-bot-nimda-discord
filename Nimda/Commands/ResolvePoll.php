<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageEmbed;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use Illuminate\Support\Collection;
use MieuxVoter\MajorityJudgment\MajorityJudgmentDeliberator;
use MieuxVoter\MajorityJudgment\Model\Result\ProposalResult;
use MieuxVoter\MajorityJudgment\Model\Tally\TwoArraysPollTally;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

/**
 * This command collects the tallies and displays the merit profiles for a specific poll.
 *
 * Class ResolvePoll
 * @package Nimda\Core\Commands
 */
class ResolvePoll extends PollCommand
{

    /**
     * @inheritDoc
     * @throws \Throwable
     */
    public function trigger(Message $message, Collection $args = null): PromiseInterface
    {
        $channel = $message->channel;
        $actor = $message->author;

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

        $deleteMessagePromise = $message->delete(10);

        $pollIsValid = true;

        try {
            $poll = $this->findPollById($pollId);
        } catch (\Exception $exception) {
            sprintf("ERROR findPollById threw:\n");
            dump($exception);
            $pollIsValid = false;
        }

        if (empty($poll)) {
            $pollIsValid = false;
        }

        if ( ! $pollIsValid) {
            printf(
                "%s no poll found with id `%s' in channel `%s'.\n",
                $message->author->username, $pollId, $channel->getId()
            );
            return reject($this->sendToast(
                $channel, $message,
                "The poll was not found on this channel.  Try specifying its identifier with `!result ID`?",
                [],
                10
            ));
        }

//        printf("");

        $dbProposalsPromise = $this->getDbProposalsForPoll($pollId);
        $commandPromise = $dbProposalsPromise
            // Error is caught by bottom handler.  Do we need this?
//            ->otherwise(
//                function ($error) {
//                    printf("ERROR when calling getDbProposalsForPoll:\n");
//                    dump($error);
//                }
//            )
            ->then(
                function (Collection $dbProposals) use ($channel) {
                    printf("Found %d proposals in the database.\n", count($dbProposals));

                    return new Promise(
                        function ($resolve, $reject) use ($channel, $dbProposals) {

                            $proposalsMessagesIds = array_map(function ($dbProposal) {
                                return $dbProposal->messageId;
                            }, $dbProposals->all());

                            $this
                                ->getMessages($channel, $proposalsMessagesIds)
//                                ->otherwise(function ($error) {
//                                    printf("ERROR while fetching the messages of proposals\n:");
//                                    dump($error);
//                                })
                                ->done(
                                    function (array $messages) use ($resolve, $dbProposals) {
                                        // Filter out messages that probably were deleted and we failed to fetch
                                        $validProposals = array_filter($dbProposals->all(), function($key) use ($messages) {
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
                function ($things) use ($channel, $poll) {
                    /** @var Message[] $proposalsMessages */
                    [$proposalsObjects, $proposalsMessages] = $things;

                    $amountOfProposals = count($proposalsMessages);

                    printf("Got %d messages.\n", count($proposalsMessages));

                    $amountOfParticipants = 0;
                    $pollTally = [];
                    foreach ($proposalsMessages as $proposalsMessage) {
                        /** @var MessageReaction[] $reactions */
//                        $reactions = $proposalsMessage->reactions;
                        $reactions = array_filter($proposalsMessage->reactions->all(), function (MessageReaction $reaction) use ($poll) {
                            return in_array($reaction->emoji, $this->gradesEmotes[$poll->amountOfGrades]);
                        });
                        $proposalTally = [];
                        $amountOfJudgesOfProposal = 0;
                        foreach ($reactions as $reaction) {
                            // fixme: curate to ensure 1 user == 1 reaction
                            // This is tricky, since fetching all the users of all the reactions
                            // is going to take a long time (sequential, paginated requests).
                            // It's also not going to scale well, compared to this naive usage of `count`.
                            // We should probably offer both, this one first and then edit it with curated results?

                            $amountOfJudgesOfGrade = $reaction->count - 1;  // minus the bootstrap reaction of the bot
                            $amountOfJudgesOfProposal += $amountOfJudgesOfGrade;

                            $proposalTally[] = $amountOfJudgesOfGrade;
                        }
                        // Same: getting the amount of judges from the max() is incorrect
                        $amountOfParticipants = max($amountOfParticipants, $amountOfJudgesOfProposal);

                        $pollTally[] = $proposalTally;
                    }

                    $deliberator = new MajorityJudgmentDeliberator();
                    $result = $deliberator->deliberate(new TwoArraysPollTally(
                        $amountOfParticipants,
                        $proposalsObjects,
                        $pollTally
                    ));

                    $leaderboard = $result->getProposalResults();

                    $tallyString = join('_', array_map(function (ProposalResult $proposalResult){
                        return join('-', $proposalResult->getTally());
                    }, $leaderboard));

                    $messageBody = sprintf(
                        ""
                    );

                    $imgUrl = sprintf("https://oas.mieuxvoter.fr/%s.png", $tallyString);

                    $description = "";
                    foreach ($leaderboard as $proposalResult) {
                        $description .= sprintf(
                            "`%d` ➡️ %s \n",
                            $proposalResult->getRank(),
                            $proposalResult->getProposal()->name
                        );
                    }

                    $me = new MessageEmbed([
                        'title' => sprintf(
                            "⚖️ `%d` — %s",
                            $poll->id,
                            $poll->subject
                        ),
                        'description' => $description,
                        'image' => [
                            'url' => $imgUrl,
                            'width' => 810,
                            'height' => 500,
                        ],
                    ]);

                    return $channel->send($messageBody, [
                            'embed' => $me,
//                            'files' => [
//                                ($imgUrl),
//                            ],
                        ])
                        ->otherwise(
                            function($error) {
                                printf("ERROR sending the result:\n");
                                dump($error);
                            }
                        );

                }
            );

        $commandPromise->then(
            null,
            function ($error) {
                printf("ERROR with the !result command:\n");
                dump($error);
            }
        );

        return $commandPromise;
    }

}