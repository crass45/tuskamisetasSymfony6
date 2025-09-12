<?php

namespace App\Entity;

use App\Repository\TarifaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifaRepository::class)]
#[ORM\Table(name: 'tarifa')]
class Tarifa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = '';

    /**
     * @var Collection<int, TarifaPrecios>
     */
    #[ORM\OneToMany(mappedBy: 'tarifa', targetEntity: TarifaPrecios::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['cantidad' => 'DESC'])]
    private Collection $precios;

    public function __construct()
    {
        $this->precios = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * @return Collection<int, TarifaPrecios>
     */
    public function getPrecios(): Collection
    {
        return $this->precios;
    }

    public function addPrecio(TarifaPrecios $precio): self
    {
        if (!$this->precios->contains($precio)) {
            $this->precios->add($precio);
            $precio->setTarifa($this);
        }

        return $this;
    }

    public function removePrecio(TarifaPrecios $precio): self
    {
        if ($this->precios->removeElement($precio)) {
            // set the owning side to null (unless already changed)
            if ($precio->getTarifa() === $this) {
                $precio->setTarifa(null);
            }
        }

        return $this;
    }
}