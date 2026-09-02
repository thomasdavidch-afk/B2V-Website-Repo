<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $idHelloAsso = null;

    #[ORM\Column(length: 100)]
    private ?string $typeTransaction = null;

    #[ORM\Column]
    private ?int $nbSessions = null;

    #[ORM\Column(nullable: true)]
    private ?float $montant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateTransaction = null;

    #[ORM\Column(length: 50)]
    private ?string $origin = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdHelloAsso(): ?string
    {
        return $this->idHelloAsso;
    }

    public function setIdHelloAsso(?string $idHelloAsso): static
    {
        $this->idHelloAsso = $idHelloAsso;

        return $this;
    }

    public function getTypeTransaction(): ?string
    {
        return $this->typeTransaction;
    }

    public function setTypeTransaction(string $typeTransaction): static
    {
        $this->typeTransaction = $typeTransaction;

        return $this;
    }

    public function getNbSessions(): ?int
    {
        return $this->nbSessions;
    }

    public function setNbSessions(int $nbSessions): static
    {
        $this->nbSessions = $nbSessions;

        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(?float $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDateTransaction(): ?\DateTimeImmutable
    {
        return $this->dateTransaction;
    }

    public function setDateTransaction(\DateTimeImmutable $dateTransaction): static
    {
        $this->dateTransaction = $dateTransaction;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
