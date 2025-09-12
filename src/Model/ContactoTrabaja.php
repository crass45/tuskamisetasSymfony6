<?php
// src/Model/ContactoTrabaja.php

namespace App\Model;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modelo para el formulario de 'Trabaja con Nosotros'. No es una entidad de Doctrine.
 */
class ContactoTrabaja
{
    private ?bool $operario = false;
    private ?bool $comercial = false;
    private ?bool $design = false;

    #[Assert\NotBlank(message: "El nombre es obligatorio.")]
    private ?string $nombre = null;

    #[Assert\NotBlank(message: "El email es obligatorio.")]
    #[Assert\Email(message: "El e-mail '{{ value }}' no es una dirección de correo electrónico válida.")]
    private ?string $email = null;

    #[Assert\NotBlank(message: "El teléfono es obligatorio.")]
    private ?string $telefono = null;

    #[Assert\NotBlank(message: "La provincia es obligatoria.")]
    private ?string $provincia = null;

    #[Assert\NotBlank(message: "El país es obligatorio.")]
    private ?string $pais = null;

    /**
     * Para la subida de ficheros, se usa el tipo 'File' y el validador 'File'.
     * Puedes ajustar las opciones (mimeTypes, maxSize) según tus necesidades.
     */
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['application/pdf', 'application/x-pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        mimeTypesMessage: 'Por favor, sube un CV en formato PDF o Word.'
    )]
    private ?File $cv = null;

    #[Assert\NotBlank(message: "La edad es obligatoria.")]
    #[Assert\Range(
        min: 16,
        max: 99,
        notInRangeMessage: 'Debes introducir una edad válida.',
    )]
    private ?int $edad = null;

    #[Assert\NotBlank(message: "El campo experiencia es obligatorio.")]
    private ?string $experiencia = null;

    #[Assert\NotBlank(message: "El campo de empresas es obligatorio.")]
    private ?string $empresas = null;

    private ?string $observaciones = null;

    // --- Getters y Setters ---

    public function isOperario(): ?bool { return $this->operario; }
    public function setOperario(?bool $operario): self { $this->operario = $operario; return $this; }
    public function isComercial(): ?bool { return $this->comercial; }
    public function setComercial(?bool $comercial): self { $this->comercial = $comercial; return $this; }
    public function isDesign(): ?bool { return $this->design; }
    public function setDesign(?bool $design): self { $this->design = $design; return $this; }
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
    public function getCv(): ?File { return $this->cv; }
    public function setCv(?File $cv): self { $this->cv = $cv; return $this; }
    public function getEdad(): ?int { return $this->edad; }
    public function setEdad(?int $edad): self { $this->edad = $edad; return $this; }
    public function getExperiencia(): ?string { return $this->experiencia; }
    public function setExperiencia(?string $experiencia): self { $this->experiencia = $experiencia; return $this; }
    public function getEmpresas(): ?string { return $this->empresas; }
    public function setEmpresas(?string $empresas): self { $this->empresas = $empresas; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): self { $this->observaciones = $observaciones; return $this; }
}