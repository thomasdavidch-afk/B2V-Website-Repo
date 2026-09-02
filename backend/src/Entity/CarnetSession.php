<?php

namespace App\Entity;

use App\Repository\CarnetSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarnetSessionRepository::class)]
class CarnetSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $nbSessionsTotal = null;

    #[ORM\Column]
    private ?int $nbSessionsRestant = null;

    #[ORM\OneToOne(inversedBy: 'carnetSession', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNbSessionsTotal(): ?int
    {
        return $this->nbSessionsTotal;
    }

    public function setNbSessionsTotal(int $nbSessionsTotal): static
    {
        $this->nbSessionsTotal = $nbSessionsTotal;

        return $this;
    }

    public function getNbSessionsRestant(): ?int
    {
        return $this->nbSessionsRestant;
    }

    public function setNbSessionsRestant(int $nbSessionsRestant): static
    {
        $this->nbSessionsRestant = $nbSessionsRestant;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
