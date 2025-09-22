<?php

namespace App\Entity;

use App\Repository\DireccionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DireccionRepository::class)]
#[ORM\Table(name: 'direccion')]
class Direccion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?bool $predeterminada = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nombre = '';

    #[ORM\Column(name: 'dir', length: 250, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $dir = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cp = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $poblacion = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $pais = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $provincia = null;

    #[ORM\Column(name: 'telefono_movil', length: 45, nullable: true)]
    private ?string $telefonoMovil = null;

    #[ORM\Column(name: 'telefono_otro', length: 45, nullable: true)]
    private ?string $telefonoOtro = null;

    #[ORM\Column(nullable: true)]
    private ?bool $facturacion = false;

    // --- Relaciones ---

    #[ORM\ManyToOne(inversedBy: 'direccionesEnvio')]
    private ?Contacto $idContacto = null;

    #[ORM\ManyToOne(targetEntity: Pais::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'paisBD', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Pais $paisBD = null;

    #[ORM\ManyToOne(targetEntity: Provincia::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'provinciaBD', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Provincia $provinciaBD = null;

    public function __toString(): string
    {
        return sprintf('%s, %s', $this->getDir() ?? '', $this->getCp() ?? '');
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getIdContacto(): ?Contacto
    {
        return $this->idContacto;
    }

    public function setIdContacto(?Contacto $idContacto): self
    {
        $this->idContacto = $idContacto;
        return $this;
    }
    public function isPredeterminada(): ?bool { return $this->predeterminada; }
    public function setPredeterminada(?bool $predeterminada): self { $this->predeterminada = $predeterminada; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(?string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getDir(): ?string { return $this->dir; }
    public function setDir(?string $dir): self { $this->dir = $dir; return $this; }
    public function getCp(): ?string { return $this->cp; }
    public function setCp(?string $cp): self { $this->cp = $cp; return $this; }
    public function getPoblacion(): ?string { return $this->poblacion; }
    public function setPoblacion(?string $poblacion): self { $this->poblacion = $poblacion; return $this; }
    public function getPais(): ?string { return $this->pais; }
    public function setPais(string $pais): self { $this->pais = $pais; return $this; }
    public function getProvincia(): ?string { return $this->provincia; }
    public function setProvincia(?string $provincia): self { $this->provincia = $provincia; return $this; }
    public function getTelefonoMovil(): ?string { return $this->telefonoMovil; }
    public function setTelefonoMovil(?string $telefonoMovil): self { $this->telefonoMovil = $telefonoMovil; return $this; }
    public function getTelefonoOtro(): ?string { return $this->telefonoOtro; }
    public function setTelefonoOtro(?string $telefonoOtro): self { $this->telefonoOtro = $telefonoOtro; return $this; }
    public function isFacturacion(): ?bool { return $this->facturacion; }
    public function setFacturacion(?bool $facturacion): self { $this->facturacion = $facturacion; return $this; }

    // --- Getters y Setters con Lógica (Denormalización) ---

    public function getPaisBD(): ?Pais
    {
        return $this->paisBD;
    }

    public function setPaisBD(?Pais $paisBD): self
    {
        $this->paisBD = $paisBD;

        // Actualiza el campo de texto simple cuando se establece la relación
        if ($paisBD) {
            $this->setPais($paisBD->getNombre());
        }

        return $this;
    }

    public function getProvinciaBD(): ?Provincia
    {
        return $this->provinciaBD;
    }

    public function setProvinciaBD(?Provincia $provinciaBD): self
    {
        $this->provinciaBD = $provinciaBD;

        // Actualiza el campo de texto simple cuando se establece la relación
        if ($provinciaBD) {
            $this->setProvincia($provinciaBD->getNombre());
        }

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatePais(): void
    {
        if (null !== $this->getPais()) {
            $this->pais = $this->paisBD->getNombre();
        }
    }
}