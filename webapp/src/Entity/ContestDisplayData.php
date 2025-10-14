<?php declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(
    name: 'contest_display_data',
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Display data for contests (extension table)',
    ],
    indexes: [new ORM\Index(columns: ['contest_id'], name: 'contest_id')],
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'contest_id_unique', columns: ['contest_id'])]
)]
#[Serializer\ExclusionPolicy('all')]
class ContestDisplayData
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Contest::class)]
    #[ORM\JoinColumn(name: 'contest_id', referencedColumnName: 'cid', onDelete: 'CASCADE')]
    #[Serializer\Expose]
    private ?Contest $contest = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Display title for the contest'])]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Display subtitle for the contest'])]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private ?string $subtitle = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['comment' => 'Banner image URL or path'])]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private ?string $bannerUrl = null;

    #[ORM\Column(type: 'text', nullable: true, options: ['comment' => 'Long description or HTML for contest display'])]
    #[Serializer\Expose]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['comment' => 'Flexible meta data for contest display'])]
    private ?array $metaData = null;

    public function getMetaData(): ?array
    {
        return $this->metaData;
    }
    public function setMetaData(?array $metaData): self
    {
        $this->metaData = $metaData;
        return $this;
    }

    // Phase support removed in this deployment; allowPhase column was dropped from the database.

    public function getContest(): ?Contest
    {
        return $this->contest;
    }
    public function setContest(?Contest $contest): self
    {
        $this->contest = $contest;
        return $this;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }
    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }
    public function getBannerUrl(): ?string
    {
        return $this->bannerUrl;
    }
    public function setBannerUrl(?string $bannerUrl): self
    {
        $this->bannerUrl = $bannerUrl;
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
}
