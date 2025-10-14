<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(
    name: 'submission_deliverable',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Deliverables for each submission',
    ],
    indexes: [new ORM\Index(columns: ['submitid'], name: 'submitid')]
)]
#[Serializer\ExclusionPolicy('all')]
class SubmissionDeliverable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'deliverableid', type: 'integer', options: ['unsigned' => true, 'comment' => 'Unique ID'])]
    #[Serializer\Expose]
    private ?int $deliverableid = null;

    #[ORM\ManyToOne(targetEntity: Submission::class)]
    #[ORM\JoinColumn(name: 'submitid', referencedColumnName: 'submitid', onDelete: 'CASCADE')]
    #[Serializer\Expose]
    private ?Submission $submission = null;

    #[ORM\Column(type: 'string', length: 64, options: ['comment' => 'Deliverable type (e.g. web app, CLI app)'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Serializer\Expose]
    private string $type = '';

    #[ORM\Column(name: 'file_type', type: 'string', length: 32, options: ['comment' => 'File type (e.g. zip, tar.gz, etc)'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[Serializer\Expose]
    private string $fileType = '';

    #[ORM\Column(type: 'string', length: 255, options: ['comment' => 'URL to the deliverable file'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private string $url = '';

    public function getDeliverableid(): ?int
    {
        return $this->deliverableid;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): self
    {
        $this->fileType = $fileType;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }
}