<?php

namespace Nimda\Core\Timers;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\ClientUser;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Nimda\Core\Timer;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

class SetPresence extends Timer
{
    /**
     * @inheritDoc
     */
    public function trigger(Client $client): PromiseInterface
    {
        if (empty($client->user)) {
            print("WARNING: User not available in Client, skipping SetPresence…\n");
            return reject();
        }
        return $client->user->setPresence($this->config['presence'])->then(function (ClientUser $clientUser) {
            if($this->config['avatar'] === '') {
                return reject(new InvalidArgumentException('Avatar not set'));
            }
            return $clientUser->client->user->setAvatar($this->config['avatar']);
        });
    }
}