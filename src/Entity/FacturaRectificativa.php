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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numeroFactura = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $fecha = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $motivo = null;

    #[ORM\OneToOne(inversedBy: 'facturaRectificativa', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Factura $facturaPadre = null;


    #[ORM\Column(name: 'razon_social', type: Types::TEXT)]
    private ?string $razonSocial = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $direccion = null;

    #[ORM\Column(length: 255)]
    private ?string $cp = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $poblacion = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $provincia = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $pais = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $cif = null;

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

    /**
     * @return string|null
     */
    public function getCif(): ?string
    {
        return $this->cif;
    }

    /**
     * @return string|null
     */
    public function getCp(): ?string
    {
        return $this->cp;
    }

    /**
     * @return string|null
     */
    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    /**
     * @return string|null
     */
    public function getPais(): ?string
    {
        return $this->pais;
    }

    /**
     * @return string|null
     */
    public function getPoblacion(): ?string
    {
        return $this->poblacion;
    }

    /**
     * @return string|null
     */
    public function getProvincia(): ?string
    {
        return $this->provincia;
    }

    /**
     * @return string|null
     */
    public function getRazonSocial(): ?string
    {
        return $this->razonSocial;
    }

    /**
     * @param string|null $cif
     */
    public function setCif(?string $cif): void
    {
        $this->cif = $cif;
    }

    /**
     * @param string|null $direccion
     */
    public function setDireccion(?string $direccion): void
    {
        $this->direccion = $direccion;
    }

    /**
     * @param string|null $cp
     */
    public function setCp(?string $cp): void
    {
        $this->cp = $cp;
    }

    /**
     * @param string|null $pais
     */
    public function setPais(?string $pais): void
    {
        $this->pais = $pais;
    }

    /**
     * @param string|null $poblacion
     */
    public function setPoblacion(?string $poblacion): void
    {
        $this->poblacion = $poblacion;
    }

    /**
     * @param string|null $provincia
     */
    public function setProvincia(?string $provincia): void
    {
        $this->provincia = $provincia;
    }

    /**
     * @param string|null $razonSocial
     */
    public function setRazonSocial(?string $razonSocial): void
    {
        $this->razonSocial = $razonSocial;
    }

    // --- NUEVOS MÉTODOS DE CÁLCULO ---
    /**
     * Calcula la base imponible sumando el total de sus propias líneas.
     */
    public function getBaseImponible(): float
    {
        $subtotal = 0.0;
        foreach ($this->getLineas() as $linea) {
            $subtotal += $linea->getTotal();
        }
        // Redondeamos al final para precisión
        return round($subtotal, 2);
    }

    /**
     * Calcula el importe del IVA basado en su propia base imponible.
     * El tipo de IVA se toma del pedido original.
     */
    public function getImporteIva(): float
    {

//        $ivaPorcentaje = $this->$this->facturaPadre->getPedido()->getIva();
        return round($this->getBaseImponible() * (21 / 100), 2);
    }

    /**
     * Calcula el importe del Recargo de Equivalencia (si aplica).
     */
    public function getImporteRecargoEquivalencia(): float
    {
        if (!$this->facturaPadre || !$this->facturaPadre->getPedido() || !$this->facturaPadre->getPedido()->getContacto()) {
            return 0.0;
        }

        $pedido = $this->facturaPadre->getPedido();
        $contacto = $pedido->getContacto();

        if ($pedido->getRecargoEquivalencia() > 0 && $contacto->isRecargoEquivalencia()) {
            $baseImponible = $this->getBaseImponible(); // Ya es negativa
            $tipoRecargo = 5.2;

            return round($baseImponible * ($tipoRecargo / 100), 2);
        }

        return 0.0;
    }

    /**
     * Calcula el total final sumando su base, su IVA y su Recargo.
     */
    public function getTotal(): float
    {
        $total = $this->getBaseImponible() + $this->getImporteIva() + $this->getImporteRecargoEquivalencia();
        return round($total, 2);
    }
    // --- FIN DE NUEVOS MÉTODOS ---

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

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
        return "Factura Rectificativa".$this->getNumeroFactura();
    }
    // --- FIN NUEVOS MÉTODOS ---
}