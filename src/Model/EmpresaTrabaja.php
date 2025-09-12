<?php
// src/Model/EmpresaTrabaja.php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modelo para el formulario 'Trabaja con Nosotros'. No es una entidad de Doctrine.
 */
class EmpresaTrabaja
{
    private ?bool $serigrafia = false;
    private ?bool $tampografia = false;
    private ?bool $bordado = false;
    private ?bool $dgt = false;
    private ?bool $laser = false;
    private ?bool $otro = false;

    #[Assert\NotBlank(message: "El nombre es obligatorio.")]
    private ?string $nombre = null;

    #[Assert\NotBlank(message: "El email es obligatorio.")]
    #[Assert\Email(message: "La dirección '{{ value }}' no es un email válido.")]
    private ?string $email = null;

    #[Assert\NotBlank(message: "El teléfono es obligatorio.")]
    private ?string $telefono = null;

    #[Assert\NotBlank(message: "La provincia es obligatoria.")]
    private ?string $provincia = null;

    #[Assert\NotBlank(message: "El país es obligatorio.")]
    private ?string $pais = null;

    #[Assert\NotBlank(message: "El campo experiencia es obligatorio.")]
    private ?string $experiencia = null;

    private ?string $observaciones = null;

    // --- Getters y Setters ---

    public function isSerigrafia(): ?bool { return $this->serigrafia; }
    public function setSerigrafia(?bool $serigrafia): self { $this->serigrafia = $serigrafia; return $this; }
    public function isTampografia(): ?bool { return $this->tampografia; }
    public function setTampografia(?bool $tampografia): self { $this->tampografia = $tampografia; return $this; }
    public function isBordado(): ?bool { return $this->bordado; }
    public function setBordado(?bool $bordado): self { $this->bordado = $bordado; return $this; }
    public function isDgt(): ?bool { return $this->dgt; }
    public function setDgt(?bool $dgt): self { $this->dgt = $dgt; return $this; }
    public function isLaser(): ?bool { return $this->laser; }
    public function setLaser(?bool $laser): self { $this->laser = $laser; return $this; }
    public function isOtro(): ?bool { return $this->otro; }
    public function setOtro(?bool $otro): self { $this->otro = $otro; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): self { $this->telefono = $telefono; return $this; }
    public function getProvincia(): ?string { return $this->provincia; }
    public function setProvincia(?string $provincia): self { $this->provincia = $provincia; return $this; }
    public function getPais(): ?string { return $this->pais; }
    public function setPais(?string $pais): self { $this->pais = $pais; return $this; }
    public function getExperiencia(): ?string { return $this->experiencia; }
    public function setExperiencia(?string $experiencia): self { $this->experiencia = $experiencia; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): self { $this->observaciones = $observaciones; return $this; }
}