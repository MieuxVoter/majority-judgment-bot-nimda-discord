<?php


namespace Nimda\Entity;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;


/**
 * All the channels this bot has been invited to !join
 *
 * @ORM\Entity
 * @ORM\Table(name="channels")
 */
class Channel
{
    /**
     * Internal identifier.  Should probably not be public.
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    protected int $id;

    /**
     * All the polls created on this channel.
     *
     * @ORM\OneToMany(
     *     targetEntity=Poll::class,
     *     mappedBy="channel",
     * )
     */
    protected ?PersistentCollection $polls = null;

    /**
     * Name of the channel (if we can read it).
     *
     * @ORM\Column(type="string")
     */
    protected string $name = "";

    /**
     * Identifier of the channel Discord uses
     *
     * @ORM\Column(type="string")
     */
    protected string $discordId;

    /**
     * Identifier of the guild/community the channel belongs to.
     *
     * @ORM\Column(type="string")
     */
    protected string $guildId;

    /**
     * Name of the guild/community the channel belongs to.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected string $guildName = "";

    /**
     * Identifier of the User that added the bot to the channel.
     * When the bot leaves the channel, this entity should be deleted.
     * (unless it was banned for abuse since abuse information may be stored in here)?
     * Perhaps not, we'll make another Entity probably.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected string $joinerId = "";

    /**
     * Username of the User that asked the bot to !join the channel.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected string $joinerUsername = "";

    /**
     * @ORM\Column(type="string")
     */
    protected string $pollCreationRoles = "";

    /**
     * @ORM\Column(type="string")
     */
    protected string $proposalCreationRoles = "";

    /**
     * @ORM\Column(type="string")
     */
    protected string $voteViaReactionRoles = "";

    /**
     * @ORM\Column(type="integer")
     */
    protected int $usage = 0;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return PersistentCollection|null
     */
    public function getPolls(): ?PersistentCollection
    {
        return $this->polls;
    }

    /**
     * @param Poll $poll
     * @return Channel
     */
    public function addPoll(Poll $poll) : self
    {
        if ( ! $this->polls->contains($poll)) {
            $this->polls->add($poll);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Channel
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getDiscordId(): string
    {
        return $this->discordId;
    }

    /**
     * @param string $discordId
     * @return Channel
     */
    public function setDiscordId(string $discordId): self
    {
        $this->discordId = $discordId;

        return $this;
    }

    /**
     * @return string
     */
    public function getGuildId(): string
    {
        return $this->guildId;
    }

    /**
     * @param string $guildId
     * @return Channel
     */
    public function setGuildId(string $guildId): self
    {
        $this->guildId = $guildId;

        return $this;
    }

    /**
     * @return string
     */
    public function getGuildName(): string
    {
        return $this->guildName;
    }

    /**
     * @param string $guildName
     * @return Channel
     */
    public function setGuildName(string $guildName): self
    {
        $this->guildName = $guildName;

        return $this;
    }

    /**
     * @return string
     */
    public function getJoinerId(): string
    {
        return $this->joinerId;
    }

    /**
     * @param string $joinerId
     * @return Channel
     */
    public function setJoinerId(string $joinerId): self
    {
        $this->joinerId = $joinerId;

        return $this;
    }

    /**
     * @return string
     */
    public function getJoinerUsername(): string
    {
        return $this->joinerUsername;
    }

    /**
     * @param string $joinerUsername
     * @return Channel
     */
    public function setJoinerUsername(string $joinerUsername): self
    {
        $this->joinerUsername = $joinerUsername;

        return $this;
    }

    /**
     * @return string
     */
    public function getPollCreationRoles(): string
    {
        return $this->pollCreationRoles;
    }

    /**
     * @param string $pollCreationRoles
     * @return Channel
     */
    public function setPollCreationRoles(string $pollCreationRoles): self
    {
        $this->pollCreationRoles = $pollCreationRoles;

        return $this;
    }

    /**
     * @return string
     */
    public function getProposalCreationRoles(): string
    {
        return $this->proposalCreationRoles;
    }

    /**
     * @param string $proposalCreationRoles
     * @return Channel
     */
    public function setProposalCreationRoles(string $proposalCreationRoles): self
    {
        $this->proposalCreationRoles = $proposalCreationRoles;

        return $this;
    }

    /**
     * @return string
     */
    public function getVoteViaReactionRoles(): string
    {
        return $this->voteViaReactionRoles;
    }

    /**
     * @param string $voteViaReactionRoles
     * @return Channel
     */
    public function setVoteViaReactionRoles(string $voteViaReactionRoles): self
    {
        $this->voteViaReactionRoles = $voteViaReactionRoles;

        return $this;
    }

    /**
     * @return int
     */
    public function getUsage(): int
    {
        return $this->usage;
    }

    /**
     * @param int $usage
     * @return Channel
     */
    public function setUsage(int $usage): self
    {
        $this->usage = $usage;

        return $this;
    }


}