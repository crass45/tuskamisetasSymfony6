<?php

namespace App\Entity;

use App\Repository\PedidoLineaHasTrabajoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoLineaHasTrabajoRepository::class)]
#[ORM\Table(name: 'pedido_linea_pedido_trabajo')]
class PedidoLineaHasTrabajo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PedidoLinea::class, inversedBy: 'personalizaciones', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'pedidoLinea', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?PedidoLinea $pedidoLinea = null;

    #[ORM\ManyToOne(targetEntity: PedidoTrabajo::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'pedidoTrabajo', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?PedidoTrabajo $pedidoTrabajo = null;

    #[ORM\Column]
    private ?int $cantidad = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ubicacion = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = '';

    #[ORM\Column]
    private bool $repeticion = false;

    // --- CAMPOS SNAPSHOT (NUEVOS) ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombreArea = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $urlImagenArea = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $anchoArea = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $altoArea = null;

    // --- GETTERS Y SETTERS ---

    public function getNombreArea(): ?string { return $this->nombreArea; }
    public function setNombreArea(?string $nombreArea): self { $this->nombreArea = $nombreArea; return $this; }

    public function getUrlImagenArea(): ?string { return $this->urlImagenArea; }
    public function setUrlImagenArea(?string $urlImagenArea): self { $this->urlImagenArea = $urlImagenArea; return $this; }

    public function getAnchoArea(): ?string { return $this->anchoArea; }
    public function setAnchoArea(?string $anchoArea): self { $this->anchoArea = $anchoArea; return $this; }

    public function getAltoArea(): ?string { return $this->altoArea; }
    public function setAltoArea(?string $altoArea): self { $this->altoArea = $altoArea; return $this; }

    public function __toString(): string
    {
        $personalizacion = $this->pedidoTrabajo?->getPersonalizacion();
        if (!$personalizacion) {
            return 'Trabajo no definido';
        }

        $cadena = sprintf(
            '%s - %s %s',
            $this->pedidoTrabajo?->getId() ?? '',
            $personalizacion->getCodigo() ?? '',
            $personalizacion->getNombre() ?? ''
        );

        if ($personalizacion->getCodigo() === 'A1') {
            $cadena .= sprintf(' a %d colores', $this->pedidoTrabajo?->getNColores() ?? 0);
        }

        if ($personalizacion->getCodigo() !== 'DB') {
            $cadena .= ' en ' . $this->ubicacion;
        }

        return trim($cadena);
    }

    public function getCadenaTrabajo(): string
    {
        $personalizacion = $this->pedidoTrabajo?->getPersonalizacion();
        if (!$personalizacion) {
            return '';
        }

        $inicio = ($personalizacion->getCodigo() === 'DB') ? '((( ' : '*** ';
        $fin = ($personalizacion->getCodigo() === 'DB') ? ' )))' : ' ***';

        return $inicio . $this->__toString() . $fin;
    }

    // --- Getters y Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPedidoLinea(): ?PedidoLinea
    {
        return $this->pedidoLinea;
    }

    public function setPedidoLinea(?PedidoLinea $pedidoLinea): self
    {
        $this->pedidoLinea = $pedidoLinea;
        return $this;
    }

    public function getPedidoTrabajo(): ?PedidoTrabajo
    {
        return $this->pedidoTrabajo;
    }

    public function setPedidoTrabajo(?PedidoTrabajo $pedidoTrabajo): self
    {
        $this->pedidoTrabajo = $pedidoTrabajo;
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

    public function getUbicacion(): ?string
    {
        return $this->ubicacion;
    }

    public function setUbicacion(?string $ubicacion): self
    {
        $this->ubicacion = $ubicacion;
        return $this;
    }

    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): self
    {
        $this->observaciones = $observaciones;
        return $this;
    }

    public function isRepeticion(): bool
    {
        return $this->repeticion;
    }

    public function setRepeticion(bool $repeticion): self
    {
        $this->repeticion = $repeticion;
        return $this;
    }
}