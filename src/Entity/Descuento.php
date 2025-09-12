<?php

namespace App\Entity;

use App\Repository\DescuentoRepository;
use Doctrine\ORM\Mapping as ORM;

// NOTA: Asegúrate de que esta ruta apunta a tu entidad de Grupo de Sonata User.
// Puede que necesites ajustarla según tu configuración.

#[ORM\Entity(repositoryClass: DescuentoRepository::class)]
#[ORM\Table(name: 'descuento')]
class Descuento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * NOTA: 'inversedBy' asume que en tu entidad 'Group' tienes una colección llamada 'descuentos'.
     */
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'descuentos')]
    #[ORM\JoinColumn(name: 'grupo', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Group $grupo = null;

    #[ORM\ManyToOne(targetEntity: Tarifa::class)]
    #[ORM\JoinColumn(name: 'tarifa_anterior', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Tarifa $tarifaAnterior = null;

    #[ORM\ManyToOne(targetEntity: Tarifa::class)]
    #[ORM\JoinColumn(name: 'tarifa', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Tarifa $tarifa = null;

    #[ORM\Column(nullable: true)]
    private ?int $descuento = null;

    public function __toString(): string
    {
        if ($this->tarifa) {
            return sprintf('Regla para %s: %s', $this->grupo?->getName() ?? 'N/A', $this->tarifa->getNombre() ?? 'N/A');
        }
        if ($this->descuento) {
            return sprintf('Regla para %s: %d%% Dto.', $this->grupo?->getName() ?? 'N/A', $this->descuento);
        }
        return sprintf('Regla de Descuento #%d', $this->id ?? 0);
    }

    // --- Getters y Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGrupo(): ?Group
    {
        return $this->grupo;
    }

    public function setGrupo(?Group $grupo): self
    {
        $this->grupo = $grupo;
        return $this;
    }

    public function getTarifaAnterior(): ?Tarifa
    {
        return $this->tarifaAnterior;
    }

    public function setTarifaAnterior(?Tarifa $tarifaAnterior): self
    {
        $this->tarifaAnterior = $tarifaAnterior;
        return $this;
    }

    public function getTarifa(): ?Tarifa
    {
        return $this->tarifa;
    }

    public function setTarifa(?Tarifa $tarifa): self
    {
        $this->tarifa = $tarifa;
        return $this;
    }

    public function getDescuento(): ?int
    {
        return $this->descuento;
    }

    public function setDescuento(?int $descuento): self
    {
        $this->descuento = $descuento;
        return $this;
    }
}