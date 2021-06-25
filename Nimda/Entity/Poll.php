<?php


namespace Nimda\Entity;


use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;


/**
 * A Poll created in a channel by an author.
 *
 * @ORM\Entity
 * @ORM\Table(name="polls")
 */
class Poll
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
     * The proposals (aka. candidates) for this poll.
     *
     * @ORM\OneToMany(
     *     targetEntity=Proposal::class,
     *     mappedBy="poll",
     * )
     */
    protected PersistentCollection $proposals;

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
     * Subject of the poll.
     *
     * @ORM\Column(type="string")
     */
    protected string $subject = "";

    /**
     * Amount of grades used by the poll.
     *
     * @ORM\Column(type="integer")
     */
    protected int $amountOfGrades = 5;

    /**
     * Emotes used by the poll in proposals' reactions,
     * as buttons to vote.
     * From "worst" to "best", in a single string of $amountOfGrades characters.
     * If none is provided, we'll use defaults in PollCommand.
     * If less than $amountOfGrades characters are provided, we'll… ignore or crash, probably.
     *
     * @ORM\Column(type="string")
     */
    protected string $gradesEmojis = "";

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
        //$this->proposals = new PersistentCollection();
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
     * @return PersistentCollection<Proposal>
     */
    public function getProposals(): PersistentCollection
    {
        return $this->proposals;
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
     * @return Poll
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
     * @return Poll
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
     * @return Poll
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
     * @return Poll
     */
    public function setTriggerMessageVendorId(string $triggerMessageVendorId): self
    {
        $this->triggerMessageVendorId = $triggerMessageVendorId;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return Poll
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return int
     */
    public function getAmountOfGrades(): int
    {
        return $this->amountOfGrades;
    }

    /**
     * @param int $amountOfGrades
     * @return Poll
     */
    public function setAmountOfGrades(int $amountOfGrades): self
    {
        $this->amountOfGrades = $amountOfGrades;

        return $this;
    }

    /**
     * @return string
     */
    public function getGradesEmojis(): string
    {
        return $this->gradesEmojis;
    }

    /**
     * @param string $gradesEmojis
     * @return Poll
     */
    public function setGradesEmojis(string $gradesEmojis): self
    {
        $this->gradesEmojis = $gradesEmojis;

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
     * @return Poll
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

}
