<?php


namespace Nimda\Entity;


use Doctrine\ORM\Mapping as ORM;


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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
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
     */
    public function setDiscordId(string $discordId): void
    {
        $this->discordId = $discordId;
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
     */
    public function setGuildId(string $guildId): void
    {
        $this->guildId = $guildId;
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
     */
    public function setGuildName(string $guildName): void
    {
        $this->guildName = $guildName;
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
     */
    public function setJoinerId(string $joinerId): void
    {
        $this->joinerId = $joinerId;
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
     */
    public function setJoinerUsername(string $joinerUsername): void
    {
        $this->joinerUsername = $joinerUsername;
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
     */
    public function setPollCreationRoles(string $pollCreationRoles): void
    {
        $this->pollCreationRoles = $pollCreationRoles;
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
     */
    public function setProposalCreationRoles(string $proposalCreationRoles): void
    {
        $this->proposalCreationRoles = $proposalCreationRoles;
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
     */
    public function setVoteViaReactionRoles(string $voteViaReactionRoles): void
    {
        $this->voteViaReactionRoles = $voteViaReactionRoles;
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
     */
    public function setUsage(int $usage): void
    {
        $this->usage = $usage;
    }


}