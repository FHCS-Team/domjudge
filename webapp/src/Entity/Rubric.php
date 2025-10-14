<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity]
#[ORM\Table(
    name: "rubric",
    options: [
        'collation' => 'utf8mb4_unicode_ci',
        'charset' => 'utf8mb4',
        'comment' => 'Rubrics for grading submissions',
    ]
)]
class Rubric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer", options: ['unsigned' => true, 'comment' => 'Unique ID'])]
    #[Serializer\Expose]
    private ?int $rubricid = null;

    #[ORM\Column(type: "string", length: 255, options: ['comment' => 'Rubric name'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    private string $name;

    #[ORM\Column(type: "string", length: 32, options: ['comment' => 'Rubric type (manual, automated, etc)'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[Serializer\Expose]
    private string $type;

    #[ORM\Column(type: "float", options: ['comment' => 'Rubric weight'])]
    #[Assert\NotNull]
    #[Serializer\Expose]
    private float $weight;

    #[ORM\Column(type: "float", nullable: true, options: ['comment' => 'Threshold for passing (nullable)'])]
    #[Serializer\Expose]
    private ?float $threshold = null;

    #[ORM\Column(type: "text", nullable: true, options: ['comment' => 'Rubric description'])]
    #[Serializer\Expose]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Problem::class)]
    #[ORM\JoinColumn(name: "probid", referencedColumnName: "probid", nullable: false, onDelete: "CASCADE")]
    private ?Problem $problem = null;

    // Getters and setters
    public function getRubricid(): ?int
    {
        return $this->rubricid;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getWeight(): float
    {
        return $this->weight;
    }
    public function setWeight(float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }
    public function setThreshold(?float $threshold): self
    {
        $this->threshold = $threshold;
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

    public function getProblem(): ?Problem
    {
    return $this->problem;
    }

    public function setProblem(Problem $problem): self
    {
    $this->problem = $problem;
    return $this;
    }
}
