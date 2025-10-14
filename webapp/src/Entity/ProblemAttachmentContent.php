<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Stores contents of problem attachments',
])]
class ProblemAttachmentContent
{
    /**
     * We use a ManyToOne instead of a OneToOne here, because otherwise the
     * reverse of this relation will always be loaded. See the commit message of commit
     * 9e421f96691ec67ed62767fe465a6d8751edd884 for a more elaborate explanation.
     */

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'content')]
    #[ORM\JoinColumn(name: 'attachmentid', referencedColumnName: 'attachmentid', onDelete: 'CASCADE')]
    private ProblemAttachment $attachment;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'Attachment content'])]
    private string $content;

    #[ORM\Column(type: 'string', length: 32, nullable: true, options: ['comment' => 'Attachment content type (pre, post, etc)'])]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Attachment content URL'])]
    private ?string $url = null;

    #[ORM\Column(type: 'text', nullable: true, options: ['comment' => 'Attachment content description'])]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['comment' => 'Flexible meta data for attachment content'])]
    private $meta_data = null;

    #[ORM\Column(name: 'is_executable', type: 'boolean', options: ['comment' => 'Whether this file gets an executable bit.', 'default' => 0])]
    #[Serializer\Exclude]
    private bool $isExecutable = false;
    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getMetaData()
    {
        return $this->meta_data;
    }
    public function setMetaData($meta_data): self
    {
        $this->meta_data = $meta_data;
        return $this;
    }

    public function getAttachment(): ProblemAttachment
    {
        return $this->attachment;
    }

    public function setAttachment(ProblemAttachment $attachment): self
    {
        $this->attachment = $attachment;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setIsExecutable(bool $isExecutable): ProblemAttachmentContent
    {
        $this->isExecutable = $isExecutable;
        return $this;
    }

    public function isExecutable(): bool
    {
        return $this->isExecutable;
    }
}
