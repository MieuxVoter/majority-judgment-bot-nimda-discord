<?php

namespace Nimda\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageEmbed;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use Illuminate\Support\Collection;
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

        $channel = $message->channel;

        $deleteMessagePromise = $message->delete();

        $dbProposalsPromise = $this->getDbProposalsForPoll($pollId);
        $commandPromise = $dbProposalsPromise
            ->otherwise(
                function ($error) {
                    printf("ERROR when calling getDbProposalsForPoll:\n");
                    dump($error);
                }
            )
            ->then(
                function (Collection $dbProposals) use ($channel) {
                    printf("Found proposals!\n");
                    dump($dbProposals);

                    return new Promise(
                        function ($resolve, $reject) use ($channel, $dbProposals) {

                            $proposalsMessagesIds = array_map(function ($dbProposal) {
                                return $dbProposal->messageId;
                            }, $dbProposals->all());

                            $this
                                ->getMessages($channel, $proposalsMessagesIds)
                                ->otherwise(function ($error) {
                                    printf("ERROR while fetching the messages of proposals\n:");
                                    dump($error);
                                })
                                ->then(
                                    function (array $messages) use ($resolve, $dbProposals) {
                                        //printf("Got the messages!\n");
                                        $resolve([$dbProposals, $messages]);
                                    },
                                    $reject
                                );
                        }
                    );
                }
            )
            ->then(
                function ($things) use ($channel) {
                    /** @var Message[] $proposalsMessages */
                    [$dbProposals, $proposalsMessages] = $things;

                    $amountOfProposals = count($proposalsMessages);

                    printf("Got %d messages.\n", count($proposalsMessages));

                    $pollTally = [];
                    foreach ($proposalsMessages as $proposalsMessage) {
                        /** @var MessageReaction[] $reactions */
                        $reactions = $proposalsMessage->reactions;
                        $proposalTally = [];
                        foreach ($reactions as $reaction) {
                            // fixme: limit to grades amount
                            // fixme: curate to ensure 1 user == 1 reaction
                            $proposalTally[] = $reaction->count - 1;  // minus the bootstrap reaction of the bot
                        }
                        $pollTally[] = $proposalTally;
                    }

                    $tallyString = join('_', array_map(function ($proposalTally){
                        return join('-', $proposalTally);
                    }, $pollTally));


                    $messageBody = sprintf(
                        ""
                    );

                    $imgUrl = sprintf("https://oas.mieuxvoter.fr/%s.png", $tallyString);

                    $description = "";
                    for ($i = 0; $i < $amountOfProposals; $i++) {
                        $description .= sprintf("%s \n", $dbProposals[$i]->name);
                    }

                    $me = new MessageEmbed([
//                        'title' => "What's a good poll subject?",
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