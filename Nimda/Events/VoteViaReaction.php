<?php


namespace Nimda\Events;


use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use CharlotteDunois\Yasmin\Models\User;
use Nimda\Configuration\Discord;
use Nimda\Core\Event;


/**
 * Yes.  Discord has Buttons.
 * This is not about Buttons.
 * This is about Reactions.
 * Not sure Yasmin has the API for Buttons, nor that it will ever have one.
 *
 * Let's read reactions, and enforce a toggle.
 * Note that it is a weak toggle and reactions will need to be curated before tally.
 *
 * Class VoteViaReaction
 * @package Nimda\Events
 */
class VoteViaReaction extends Event
{
    function messageReactionAdd(MessageReaction $reaction, User $user)
    {
        parent::messageReactionAdd($reaction, $user);

        if ( ! $this->shouldCareAboutReaction($reaction, $user)) {
            return;
        }

        /** @var Message $message */
        $message = $reaction->message;

        printf(
            "%s by %s (%s) on `%s'.\n",
            $reaction->emoji, $user->username, $user->id, $message->content
        );

        foreach ($message->reactions as $otherReaction) {
            /** @var MessageReaction $otherReaction */

            // Do not trust this value, the toggle constraint has not happened yet.
            //dump($otherReaction->count);

            if ($reaction === $otherReaction) {
                continue;
            }

            // Ok, so this is mostly 0, and to (only!) 1 after we added a reaction.  Smells like cache.
            //print($reactionIndex."# users: ".$otherReaction->users->count()."\n");
            // Let's fetch fresh data with a (bunch of) request(s) instead
            // …
            // Well, no. fetchUsers() require pagination and would require caching to scale.
            // Let's go for EAFP and try to remove the user even if he's not in the reaction.

            $otherReaction->remove($user)
                ->otherwise(
                    function ($error) {
                        print("\nERROR while removing user from reaction:\n");
                        dump($error);
                    }
                )
                ->then(
                    function ($thing) {
                        // This is called even if the user is not in the reaction.
                        //print("Removed iser from reaction.\n");
                    }
                )
            ;
        }
    }

    /**
     * Ideally other people should be able to set up proposals as well.
     * Perhaps we will create all the proposals, responding to commands (and delete commands?)
     *
     * @param MessageReaction $reaction
     * @param User $user
     * @return bool
     */
    protected function shouldCareAboutReaction(MessageReaction $reaction, User $user)
    {
        /** @var Message $message */
        $message = $reaction->message;

        if ($this->isMe($user)) {
            return false;  // let's not care about our own reactions
        }

        if ( ! $this->isMe($message->author)) {
            return false;  // only care about reactions on our own messages
        }

        // todo(weak): only care about grade reactions (requires storing their ids upon creation)

        return true;
    }

    protected function isMe(User $user)
    {
        return $user === $user->client->user;
    }

}