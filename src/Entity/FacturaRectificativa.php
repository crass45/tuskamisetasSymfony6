<?php

namespace App\Entity;

use App\Repository\FacturaRectificativaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturaRectificativaRepository::class)]
class FacturaRectificativa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $numeroFactura = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $fecha = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $motivo = null;

    #[ORM\OneToOne(inversedBy: 'facturaRectificativa', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Factura $facturaPadre = null;

    /**
     * @var Collection<int, FacturaRectificativaLinea>
     */
    #[ORM\OneToMany(mappedBy: 'facturaRectificativa', targetEntity: FacturaRectificativaLinea::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lineas;

    // --- NUEVOS CAMPOS VERIFACTU ---
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verifactuHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $verifactuQr = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verifactuEnviadoAt = null;

    // --- FIN NUEVOS CAMPOS ---

    public function __construct()
    {
        $this->fecha = new \DateTimeImmutable();
        $this->lineas = new ArrayCollection();
    }

    // ... (Getters y Setters para id, numeroFactura, fecha, motivo, facturaPadre) ...

    /**
     * @return Collection<int, FacturaRectificativaLinea>
     */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(FacturaRectificativaLinea $linea): self
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas[] = $linea;
            $linea->setFacturaRectificativa($this);
        }
        return $this;
    }

    public function removeLinea(FacturaRectificativaLinea $linea): self
    {
        if ($this->lineas->removeElement($linea)) {
            // set the owning side to null (unless already changed)
            if ($linea->getFacturaRectificativa() === $this) {
                $linea->setFacturaRectificativa(null);
            }
        }
        return $this;
    }

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroFactura(): ?string
    {
        return $this->numeroFactura;
    }

    public function setNumeroFactura(string $numeroFactura): static
    {
        $this->numeroFactura = $numeroFactura;

        return $this;
    }

    public function getFecha(): ?\DateTimeImmutable
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeImmutable $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getMotivo(): ?string
    {
        return $this->motivo;
    }

    public function setMotivo(string $motivo): static
    {
        $this->motivo = $motivo;

        return $this;
    }

    public function getFacturaPadre(): ?Factura
    {
        return $this->facturaPadre;
    }

    public function setFacturaPadre(Factura $facturaPadre): static
    {
        $this->facturaPadre = $facturaPadre;

        return $this;
    }

    /**
     * @param string|null $verifactuHash
     */
    public function setVerifactuHash(?string $verifactuHash): void
    {
        $this->verifactuHash = $verifactuHash;
    }

    /**
     * @param string|null $verifactuQr
     */
    public function setVerifactuQr(?string $verifactuQr): void
    {
        $this->verifactuQr = $verifactuQr;
    }

    /**
     * @return string|null
     */
    public function getVerifactuHash(): ?string
    {
        return $this->verifactuHash;
    }

    /**
     * @return string|null
     */
    public function getVerifactuQr(): ?string
    {
        return $this->verifactuQr;
    }

    // --- NUEVOS MÉTODOS DE CÁLCULO ---
    public function getBaseImponible(): float
    {
        $subtotal = 0.0;
        foreach ($this->getLineas() as $linea) {
            $subtotal += $linea->getTotal();
        }
        return $subtotal;
    }

    public function getImporteIva(): float
    {
        $pedidoOriginal = $this->getFacturaPadre()->getPedido();
        if ($pedidoOriginal->getIva() > 0) {
            return $pedidoOriginal->getIva()*-1;
        }
        return 0.0;
    }

    public function getTotal(): float
    {
        return $this->getBaseImponible() + $this->getImporteIva();
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getVerifactuEnviadoAt(): ?\DateTimeImmutable
    {
        return $this->verifactuEnviadoAt;
    }

    /**
     * @param \DateTimeImmutable|null $verifactuEnviadoAt
     */
    public function setVerifactuEnviadoAt(?\DateTimeImmutable $verifactuEnviadoAt): void
    {
        $this->verifactuEnviadoAt = $verifactuEnviadoAt;
    }
    // --- FIN NUEVOS MÉTODOS ---
}