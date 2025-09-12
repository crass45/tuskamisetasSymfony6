<?php

namespace App\Entity;

use App\Repository\PromocionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromocionRepository::class)]
#[ORM\Table(name: 'promocion')]
class Promocion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'fecha_inicio', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaInicio = null;

    #[ORM\Column(name: 'fecha_fin', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fechaFin = null;

    #[ORM\Column(length: 45)]
    private ?string $codigo = null;

    #[ORM\Column(nullable: true)]
    private ?int $cantidad = null;

    #[ORM\Column(nullable: true)]
    private ?int $porcentaje = null;

    #[ORM\Column(name: 'gastosEnvio', nullable: true)]
    private ?bool $gastosEnvio = null;

    public function __toString(): string
    {
        return $this->codigo ?? 'Nueva Promoción';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaInicio(): ?\DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function setFechaInicio(\DateTimeImmutable $fechaInicio): self
    {
        $this->fechaInicio = $fechaInicio;

        return $this;
    }

    public function getFechaFin(): ?\DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function setFechaFin(\DateTimeImmutable $fechaFin): self
    {
        $this->fechaFin = $fechaFin;

        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): self
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(?int $cantidad): self
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getPorcentaje(): ?int
    {
        return $this->porcentaje;
    }

    public function setPorcentaje(?int $porcentaje): self
    {
        $this->porcentaje = $porcentaje;

        return $this;
    }

    public function isGastosEnvio(): ?bool
    {
        return $this->gastosEnvio;
    }

    public function setGastosEnvio(?bool $gastosEnvio): self
    {
        $this->gastosEnvio = $gastosEnvio;

        return $this;
    }
}