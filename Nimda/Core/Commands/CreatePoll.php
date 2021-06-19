<?php

namespace Nimda\Core\Commands;

use CharlotteDunois\Yasmin\Models\Message;
use Illuminate\Support\Collection;
use Nimda\Core\Command;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Promise\all;

class CreatePoll extends Command
{
    // From worst to best, for each grading size. Let's hope 10 grades is enough.
    // Would also love literal grades like "Reject", "Passable", etc.  Gotta do what we can.
    // The numbers are unicode, not the usual ASCII.
    protected $gradesEmotes = [
        2 => ["👍", "👎"],
        3 => ["👍", "👊", "👎"],
        4 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣"],
        5 => ["🤬", "😣", "😐", "🙂", "😍"],
        6 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣"],
        7 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣"],
        8 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣"],
        9 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣"],
        10 => ["0️⃣", "1️⃣", "2️⃣", "3️⃣", "4️⃣", "5️⃣", "6️⃣", "7️⃣", "8️⃣", "9️⃣"],
        // If you add more, remember to clamp $amountOfGrades accordingly below
    ];

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

        printf("Poll creation by '%s' with the following subject: %s", $message->author, $subject);

        $messageBody = sprintf(
            "%s\n_(using %d grades)_",
            $subject,
            $amountOfGrades
        );

        return $message
//            ->reply($messageBody)
            ->channel->send($messageBody)
            ->then(function (Message $replyMessage) use ($args, $message, $amountOfGrades) {

                return all([
                    $this->addProposal($message, "Proposal A", $amountOfGrades),
                    $this->addProposal($message, "Proposal B", $amountOfGrades),
                    $this->addProposal($message, "Proposal C", $amountOfGrades),
                ])->then(function() use ($message) {
                    return $message->delete();
                });
            });
    }

    protected function addProposal(Message $pollMessage, string $proposalName, int $amountOfGrades) : PromiseInterface
    {
        $messageBody = sprintf(
            "**%s**\n",
            $proposalName
        );
        return $pollMessage
//            ->reply($messageBody)
            ->channel->send($messageBody)
            ->then(function (Message $proposalMessage) use ($amountOfGrades, $pollMessage) {
                return $this->addGradingReactions($proposalMessage, $amountOfGrades)
                    ->then(function() use ($pollMessage) {
                        //print("Done adding reactions.\n");
                    });
            });
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
                    return $message->client->addTimer(2, function () use ($resolve, $message, $gradeEmote) {
                        $resolve($message->react($gradeEmote));
                    });
                }
            );
            $promises[] = $reactionAdditionPromise;
        }

        return all($promises);
    }

}