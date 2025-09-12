<?php

namespace App\Entity;

use App\Repository\ZonaEnvioPrecioCantidadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZonaEnvioPrecioCantidadRepository::class)]
#[ORM\Table(name: 'zona_envio_precios')]
class ZonaEnvioPrecioCantidad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ZonaEnvio::class, inversedBy: 'precios', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'zona_envio', referencedColumnName: 'id', nullable: false)]
    private ?ZonaEnvio $zonaEnvio = null;

    #[ORM\Column]
    private ?int $bultos = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio = '0.0000';

    public function __toString(): string
    {
        // Corregido: Usaba una propiedad 'cantidad' que no existía. Debería ser 'bultos'.
        return sprintf("Desde %d bultos: %s", $this->bultos, $this->precio);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZonaEnvio(): ?ZonaEnvio
    {
        return $this->zonaEnvio;
    }

    public function setZonaEnvio(?ZonaEnvio $zonaEnvio): self
    {
        $this->zonaEnvio = $zonaEnvio;

        return $this;
    }

    public function getBultos(): ?int
    {
        return $this->bultos;
    }

    public function setBultos(int $bultos): self
    {
        $this->bultos = $bultos;

        return $this;
    }

    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(string $precio): self
    {
        $this->precio = $precio;

        return $this;
    }
}