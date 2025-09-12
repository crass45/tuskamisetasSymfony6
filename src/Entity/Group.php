<?php
// src/Entity/Group.php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: 'app_group')] // Le ponemos un nombre de tabla claro
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON)] // <-- Asegúrate de que pone JSON
    private array $roles = [];

    /**
     * @var Collection<int, Descuento>
     */
    #[ORM\OneToMany(mappedBy: 'grupo', targetEntity: Descuento::class)]
    private Collection $descuentos;

    public function __construct()
    {
        $this->descuentos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? 'Nuevo Grupo';
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function addRole(string $role): self { if (!in_array($role, $this->roles, true)) { $this->roles[] = $role; } return $this; }
    public function removeRole(string $role): self { $key = array_search($role, $this->roles, true); if ($key !== false) { unset($this->roles[$key]); } return $this; }

    /** @return Collection<int, Descuento> */
    public function getDescuentos(): Collection { return $this->descuentos; }
    public function addDescuento(Descuento $descuento): self { if (!$this->descuentos->contains($descuento)) { $this->descuentos->add($descuento); $descuento->setGrupo($this); } return $this; }
    public function removeDescuento(Descuento $descuento): self { if ($this->descuentos->removeElement($descuento)) { if ($descuento->getGrupo() === $this) { $descuento->setGrupo(null); } } return $this; }
}