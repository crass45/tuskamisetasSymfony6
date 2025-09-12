<?php

namespace App\Entity;

use App\Repository\GastosEnvioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GastosEnvioRepository::class)]
#[ORM\Table(name: 'gastos_envio')]
class GastosEnvio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'codigo_postal', length: 10)]
    private ?string $codigoPostal = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $precio = '0.00';

    #[ORM\Column(name: 'precio_reducido', type: 'decimal', precision: 5, scale: 2)]
    private ?string $precioReducido = '0.00';

    public function __toString(): string
    {
        return sprintf('CP %s: %s €', $this->codigoPostal ?? 'N/A', $this->precio ?? '0.00');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigoPostal(): ?string
    {
        return $this->codigoPostal;
    }

    public function setCodigoPostal(string $codigoPostal): self
    {
        $this->codigoPostal = $codigoPostal;
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

    public function getPrecioReducido(): ?string
    {
        return $this->precioReducido;
    }

    public function setPrecioReducido(string $precioReducido): self
    {
        $this->precioReducido = $precioReducido;
        return $this;
    }
}