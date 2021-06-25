<?php declare(strict_types=1);


namespace Nimda\Entity;


use DateTime;
use Doctrine\ORM\Mapping as ORM;


/**
 * A Proposal submitted by an author for a Poll in a Channel.
 *
 * @ORM\Entity
 * @ORM\Table(name="proposals")
 */
class Proposal
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
     * The Poll this Proposal is attached to.
     *
     * @ORM\ManyToOne(
     *     targetEntity=Poll::class,
     *     inversedBy="proposals",
     * )
     */
    protected Poll $poll;

    /**
     * Vendor Identifier of the User that created the poll.
     *
     * @ORM\Column(type="string", nullable="true")
     */
    protected string $authorVendorId;

    /**
     * Vendor Identifier of the User that created the poll.
     *
     * @ORM\Column(type="string")
     */
    protected string $channelVendorId;

    /**
     * Vendor Identifier of the message where the poll is described.
     * This message has been emitted by the bot in response to the trigger message.
     *
     * @ORM\Column(type="string")
     */
    protected string $messageVendorId;

    /**
     * Vendor Identifier of the trigger (ie. command) message, the message with the !poll command.
     * (that message was probably deleted)
     *
     * @ORM\Column(type="string")
     */
    protected string $triggerMessageVendorId;

    /**
     * Name of the proposal.
     *
     * @ORM\Column(type="string")
     */
    protected string $name = "";

    /**
     * Timestamp of the creation date of the poll.
     *
     * @ORM\Column(type="datetimetz")
     */
    protected DateTime $createdAt;

    /**
     * Poll constructor.
     */
    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

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
    public function getAuthorVendorId(): string
    {
        return $this->authorVendorId;
    }

    /**
     * @param string $authorVendorId
     * @return Proposal
     */
    public function setAuthorVendorId(string $authorVendorId): self
    {
        $this->authorVendorId = $authorVendorId;

        return $this;
    }

    /**
     * @return string
     */
    public function getChannelVendorId(): string
    {
        return $this->channelVendorId;
    }

    /**
     * @param string $channelVendorId
     * @return Proposal
     */
    public function setChannelVendorId(string $channelVendorId): self
    {
        $this->channelVendorId = $channelVendorId;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessageVendorId(): string
    {
        return $this->messageVendorId;
    }

    /**
     * @param string $messageVendorId
     * @return Proposal
     */
    public function setMessageVendorId(string $messageVendorId): self
    {
        $this->messageVendorId = $messageVendorId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTriggerMessageVendorId(): string
    {
        return $this->triggerMessageVendorId;
    }

    /**
     * @param string $triggerMessageVendorId
     * @return Proposal
     */
    public function setTriggerMessageVendorId(string $triggerMessageVendorId): self
    {
        $this->triggerMessageVendorId = $triggerMessageVendorId;

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
     * @return Proposal
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     * @return Proposal
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

}
