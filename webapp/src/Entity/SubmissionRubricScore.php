<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(
    name: 'submission_rubric_score',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Manual rubric scores for hackathon submissions',
    ],
    indexes: [
        new ORM\Index(columns: ['submitid'], name: 'submitid'),
        new ORM\Index(columns: ['rubricid'], name: 'rubricid'),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'unique_submission_rubric', columns: ['submitid', 'rubricid'])
    ]
)]
#[Serializer\ExclusionPolicy('all')]
class SubmissionRubricScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'scoreid', type: 'integer', options: ['unsigned' => true, 'comment' => 'Unique ID'])]
    #[Serializer\Expose]
    private ?int $scoreid = null;

    #[ORM\ManyToOne(targetEntity: Submission::class)]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    #[Serializer\Expose]
    private ?Submission $submission = null;

    #[ORM\ManyToOne(targetEntity: Rubric::class)]
    #[ORM\JoinColumn(name: 'rubricid', referencedColumnName: 'rubricid', onDelete: 'CASCADE')]
    #[Serializer\Expose]
    private ?Rubric $rubric = null;

    #[ORM\Column(type: 'float', options: ['comment' => 'Score given for this rubric (0.0 to 1.0)'])]
    #[Assert\Range(min: 0.0, max: 1.0)]
    #[Serializer\Expose]
    private float $score = 0.0;

    #[ORM\Column(name: 'judge_name', type: 'string', length: 255, options: ['comment' => 'Name of the judge who scored this'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private string $judgeName = '';

    #[ORM\Column(name: 'judged_at', type: 'datetime', options: ['comment' => 'When this score was given'])]
    #[Serializer\Expose]
    private ?\DateTimeInterface $judgedAt = null;

    #[ORM\Column(type: 'text', nullable: true, options: ['comment' => 'Optional comments/feedback from the judge'])]
    #[Serializer\Expose]
    private ?string $comments = null;

    public function __construct()
    {
        $this->judgedAt = new \DateTime();
    }

    public function getScoreid(): ?int
    {
        return $this->scoreid;
    }

    public function getSubmission(): ?Submission
    {
        return $this->submission;
    }

    public function setSubmission(?Submission $submission): self
    {
        $this->submission = $submission;
        return $this;
    }

    public function getRubric(): ?Rubric
    {
        return $this->rubric;
    }

    public function setRubric(?Rubric $rubric): self
    {
        $this->rubric = $rubric;
        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = max(0.0, min(1.0, $score)); // Clamp between 0 and 1
        return $this;
    }

    public function getJudgeName(): string
    {
        return $this->judgeName;
    }

    public function setJudgeName(string $judgeName): self
    {
        $this->judgeName = $judgeName;
        return $this;
    }

    public function getJudgedAt(): ?\DateTimeInterface
    {
        return $this->judgedAt;
    }

    public function setJudgedAt(\DateTimeInterface $judgedAt): self
    {
        $this->judgedAt = $judgedAt;
        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }
}