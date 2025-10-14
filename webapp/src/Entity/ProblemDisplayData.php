<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

/**
 * Extra display/configuration data for a problem (UI/UX extensions).
 */
#[ORM\Entity]
#[ORM\Table(
    name: "problem_display_data",
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Display/UX extension data for problems',
    ],
    indexes: [
        new ORM\Index(columns: ["problem_id"], name: "problem_id_idx")
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(columns: ["problem_id"], name: "unique_problem_display_data")
    ]
)]
class ProblemDisplayData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['comment' => 'Primary key'])]
    private int $pdisplayid;

    #[ORM\OneToOne(targetEntity: Problem::class)]
    #[ORM\JoinColumn(name: 'problem_id', referencedColumnName: 'probid', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Problem $problem;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Display/alternate name for the problem'])]
    private ?string $displayName = null;

    #[ORM\Column(type: 'text', nullable: true, options: ['comment' => 'Rich HTML description for the problem'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Main image/banner URL for the problem'])]
    private ?string $imageUrl = null;


    #[ORM\Column(type: 'datetime', options: ['comment' => 'Created at'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['comment' => 'Last updated at'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and setters
    public function getPdisplayid(): int { return $this->pdisplayid; }
    public function getProblem(): Problem { return $this->problem; }
    public function setProblem(Problem $problem): self { $this->problem = $problem; return $this; }
    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $displayName): self { $this->displayName = $displayName; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl; return $this; }
    // attachments and metaData are moved to dedicated problem_attachment tables
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
