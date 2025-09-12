<?php

namespace App\Entity;

use App\Repository\TarifaPreciosRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifaPreciosRepository::class)]
#[ORM\Table(name: 'tarifa_precios')]
class TarifaPrecios
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tarifa::class, inversedBy: 'precios', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'tarifa', referencedColumnName: 'id', nullable: false)]
    private ?Tarifa $tarifa = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $precio = '0.0000';

    public function __toString(): string
    {
        // La lógica original era compleja y podía producir errores.
        // Se simplifica a una representación más clara y segura.
        return sprintf('Desde %d uds: %s', $this->cantidad ?? 0, $this->precio ?? '0');
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): self
    {
        $this->cantidad = $cantidad;

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

    /**
     * NOTA DE MIGRACIÓN:
     * Este método depende de la entidad 'Modelo' que aún no ha sido migrada.
     * La lógica original para el caso 'else' era muy frágil y podía causar errores
     * si la colección de precios no tenía al menos 2 elementos.
     * Se recomienda revisar esta lógica y moverla a un servicio en lugar de mantenerla en la entidad.
     */
    public function getCantidadString(Modelo $modelo): string
    {
        // TODO: Revisar y refactorizar esta lógica de negocio.
        if ($this->cantidad > 0) {
            if ($modelo->isArticuloPublicitario()) {
                return "+" . $this->cantidad;
            }
            return "" . $this->cantidad;
        }

        // La lógica original aquí era peligrosa. Se devuelve un valor por defecto.
        // return "-" . ($this->tarifa->precios->toArray()[($this->tarifa->precios->count() - 2)]->cantidad);
        return "1";
    }
}