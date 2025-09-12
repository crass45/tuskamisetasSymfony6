<?php

namespace App\Entity;

use App\Repository\ProvinciaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProvinciaRepository::class)]
#[ORM\Table(name: 'provincias')]
class Provincia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $iso = null;

    #[ORM\Column(length: 80)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'exento_iva')]
    private bool $exentoIva = false;

    /**
     * @var Collection<int, ZonaEnvio>
     */
    #[ORM\ManyToMany(targetEntity: ZonaEnvio::class, mappedBy: 'provincias')]
    private Collection $zonasEnvio;

    #[ORM\ManyToOne(targetEntity: Pais::class, inversedBy: 'provincias', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'pais', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Pais $pais = null;

    public function __construct()
    {
        $this->zonasEnvio = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIso(): ?string
    {
        return $this->iso;
    }

    public function setIso(string $iso): self
    {
        $this->iso = $iso;

        return $this;
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

    public function isExentoIva(): bool
    {
        return $this->exentoIva;
    }

    public function setExentoIva(bool $exentoIva): self
    {
        $this->exentoIva = $exentoIva;

        return $this;
    }

    public function getPais(): ?Pais
    {
        return $this->pais;
    }

    public function setPais(?Pais $pais): self
    {
        $this->pais = $pais;

        return $this;
    }

    /**
     * @return Collection<int, ZonaEnvio>
     */
    public function getZonasEnvio(): Collection
    {
        return $this->zonasEnvio;
    }

    public function addZonaEnvio(ZonaEnvio $zonaEnvio): self
    {
        if (!$this->zonasEnvio->contains($zonaEnvio)) {
            $this->zonasEnvio->add($zonaEnvio);
            // Si la relación es la propietaria en ZonaEnvio, habría que añadirlo también allí
            // $zonaEnvio->addProvincia($this);
        }

        return $this;
    }



    public function removeZonaEnvio(ZonaEnvio $zonaEnvio): self
    {
        if ($this->zonasEnvio->removeElement($zonaEnvio)) {
            // Si la relación es la propietaria en ZonaEnvio, habría que quitarlo también de allí
            // $zonaEnvio->removeProvincia($this);
        }

        return $this;
    }
}