<?php

namespace App\Entity;

use App\Repository\FacturaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturaRepository::class)]
#[ORM\Table(name: 'factura')]
class Factura
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $fecha = null;

    #[ORM\Column]
    private ?int $fiscalYear = null;

    #[ORM\Column]
    private ?int $numeroFactura = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $comentarios = '';

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

    #[ORM\OneToOne(inversedBy: 'factura', targetEntity: Pedido::class)]
    #[ORM\JoinColumn(name: 'pedido', referencedColumnName: 'id', onDelete: 'RESTRICT')]
    private ?Pedido $pedido = null;

    /**
     * El constructor de una entidad debe ser simple y sin argumentos.
     */
    public function __construct()
    {
        $this->fecha = new \DateTimeImmutable();
    }

    /**
     * NOTA DE ARQUITECTURA: Patrón Factory Estático.
     * Toda la lógica del antiguo constructor se ha movido aquí.
     * Este método se encarga de crear una nueva instancia de Factura y llenarla con
     * los datos de un Pedido. Es una forma mucho más limpia y desacoplada.
     * * A largo plazo, esta lógica podría moverse a su propio servicio (ej. FacturaFactory).
     */
    public static function createFromPedido(Pedido $pedido, int $fiscalYear, int $numeroFactura): self
    {
        $factura = new self();
        $factura->setFecha(new \DateTimeImmutable());
        $factura->setPedido($pedido);
        $factura->setFiscalYear($fiscalYear);
        $factura->setNumeroFactura($numeroFactura);

        // Generar el nombre de la factura
        $nombreFactura = "FW" . $factura->getFecha()->format('y') . "/" . sprintf('%05d', $numeroFactura);
        $factura->setNombre($nombreFactura);

        // Copiar datos del cliente y dirección para preservar el estado en el momento de la facturación
        $contacto = $pedido->getContacto();
        if ($contacto) {
            $factura->setRazonSocial($contacto->getNombre() . " " . $contacto->getApellidos());
            $factura->setCif($contacto->getCif() ?? '-');

            $direccionFacturacion = $contacto->getDireccion();
            if ($direccionFacturacion) {
                $factura->setDireccion($direccionFacturacion->getDir());
                $factura->setCp($direccionFacturacion->getCp());
                $factura->setPoblacion($direccionFacturacion->getPoblacion());
                $factura->setProvincia($direccionFacturacion->getProvincia()?->getNombre());
                $factura->setPais($direccionFacturacion->getPais()?->getNombre());
            }
        }

        return $factura;
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Nueva Factura';
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getFecha(): ?\DateTimeImmutable { return $this->fecha; }
    public function setFecha(\DateTimeImmutable $fecha): self { $this->fecha = $fecha; return $this; }
    public function getFiscalYear(): ?int { return $this->fiscalYear; }
    public function setFiscalYear(int $fiscalYear): self { $this->fiscalYear = $fiscalYear; return $this; }
    public function getNumeroFactura(): ?int { return $this->numeroFactura; }
    public function setNumeroFactura(int $numeroFactura): self { $this->numeroFactura = $numeroFactura; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getComentarios(): string { return $this->comentarios; }
    public function setComentarios(string $comentarios): self { $this->comentarios = $comentarios; return $this; }
    public function getRazonSocial(): ?string { return $this->razonSocial; }
    public function setRazonSocial(string $razonSocial): self { $this->razonSocial = $razonSocial; return $this; }
    public function getDireccion(): ?string { return $this->direccion; }
    public function setDireccion(string $direccion): self { $this->direccion = $direccion; return $this; }
    public function getCp(): ?string { return $this->cp; }
    public function setCp(string $cp): self { $this->cp = $cp; return $this; }
    public function getPoblacion(): ?string { return $this->poblacion; }
    public function setPoblacion(string $poblacion): self { $this->poblacion = $poblacion; return $this; }
    public function getProvincia(): ?string { return $this->provincia; }
    public function setProvincia(string $provincia): self { $this->provincia = $provincia; return $this; }
    public function getPais(): ?string { return $this->pais; }
    public function setPais(string $pais): self { $this->pais = $pais; return $this; }
    public function getCif(): ?string { return $this->cif; }
    public function setCif(string $cif): self { $this->cif = $cif; return $this; }
    public function getPedido(): ?Pedido { return $this->pedido; }
    public function setPedido(?Pedido $pedido): self { $this->pedido = $pedido; return $this; }
}