<?php
// src/Model/PresupuestoTrabajo.php

namespace App\Model;

use App\Entity\PedidoLineaHasTrabajo;
use App\Entity\Personalizacion;

/**
 * Descripción de PresupuestoTrabajo
 *
 * Representa un trabajo dentro de un presupuesto. No es una entidad de Doctrine.
 * Se utiliza para ser almacenado en la sesión.
 */
class PresupuestoTrabajo
{
    private ?string $id = null;
    private ?Personalizacion $trabajo = null;
    private ?int $cantidad = null;
    private string $ubicacion = '';
    private string $urlImage = '';
    private string $observaciones = '';
    private string $identificadorTrabajo;

    public function __construct()
    {
        $this->identificadorTrabajo = uniqid('trabajo_', true);
    }

    // --- MÉTODOS DE SERIALIZACIÓN MODERNOS PARA PHP 8+ ---

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'cantidad' => $this->cantidad,
            'trabajo' => $this->trabajo,
            'ubicacion' => $this->ubicacion,
            'urlImage' => $this->urlImage,
            'identificadorTrabajo' => $this->identificadorTrabajo,
            'observaciones' => $this->observaciones,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->cantidad = $data['cantidad'] ?? null;
        $this->trabajo = $data['trabajo'] ?? null;
        $this->ubicacion = $data['ubicacion'] ?? '';
        $this->urlImage = $data['urlImage'] ?? '';
        $this->identificadorTrabajo = $data['identificadorTrabajo'] ?? uniqid('trabajo_', true);
        $this->observaciones = $data['observaciones'] ?? '';
    }

    // --- GETTERS Y SETTERS ---

    public function __toString(): string
    {
        if (!$this->trabajo) {
            return '';
        }

        $cadena = $this->trabajo->getNombre() . " ";
        if ($this->trabajo->getCodigo() === "A1") {
            $cadena .= $this->cantidad . " colores ";
        }
        if ($this->ubicacion !== '') {
            $cadena .= " en " . $this->ubicacion;
        }
        $cadena .= " " . $this->observaciones;

        return trim($cadena);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $idProducto): self
    {
        $this->id = $idProducto;
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

    public function addCantidad(int $suma): self
    {
        $this->cantidad += $suma;
        return $this;
    }

    public function getTrabajo(): ?Personalizacion
    {
        return $this->trabajo;
    }

    public function setTrabajo(Personalizacion $personalizacion): self
    {
        $this->setId($personalizacion->getCodigo()); // Asumiendo que Personalizacion tendrá getCodigo()
        $this->trabajo = $personalizacion;
        return $this;
    }

    public function getUbicacion(): string
    {
        return $this->ubicacion;
    }

    public function setUbicacion(string $ubicacion): self
    {
        $this->ubicacion = $ubicacion;
        return $this;
    }

    public function getUrlImage(): string
    {
        return $this->urlImage;
    }

    public function setUrlImage(string $urlImage): self
    {
        $this->urlImage = $urlImage;
        return $this;
    }

    public function getObservaciones(): string
    {
        return $this->observaciones;
    }

    public function setObservaciones(?string $observaciones): self
    {
        if($observaciones != null)
        $this->observaciones = $observaciones;
        return $this;
    }

    public function getIdentificadorTrabajo(): string
    {
        return $this->identificadorTrabajo;
    }

    public function setIdentificadorTrabajo(string $identif): self
    {
        $this->identificadorTrabajo = $identif;
        return $this;
    }

    /**
     * NUEVO MÉTODO: Rellena este objeto con los datos de una personalización de un pedido existente.
     */
    public function fromPedidoLineaHasTrabajo(PedidoLineaHasTrabajo $personalizacion): void
    {
        $pedidoTrabajo = $personalizacion->getPedidoTrabajo();
        if ($pedidoTrabajo) {
            $this->setId($pedidoTrabajo->getId());
            $this->setCantidad($pedidoTrabajo->getNColores());
            //Estudiar esto y ver si poner ID o poner el codigo
            $this->setIdentificadorTrabajo($pedidoTrabajo->getCodigo());
            $this->setUrlImage($pedidoTrabajo->getUrlImagen());
            $this->setTrabajo($pedidoTrabajo->getPersonalizacion());
        }
        $this->setObservaciones($personalizacion->getObservaciones());
        $this->setUbicacion($personalizacion->getUbicacion());
    }

    public function __clone()
    {
        // No hay objetos anidados que clonar aquí, pero lo dejamos por coherencia.
    }
}