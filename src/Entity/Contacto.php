<?php

namespace App\Entity;

use App\Repository\ContactoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Sonata\User; // Asegúrate de que esta ruta es correcta

#[ORM\Entity(repositoryClass: ContactoRepository::class)]
#[ORM\Table(name: 'contacto')]
class Contacto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotNull]
    private ?string $nombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apellidos = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cif = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $ciudad = null;

    #[ORM\Column(name: 'telefono_movil', length: 45, nullable: true)]
    private ?string $telefonoMovil = null;

    #[ORM\Column(name: 'telefono_otro', length: 45, nullable: true)]
    private ?string $telefonoOtro = null;

    #[ORM\Column(name: 'recargo_equivalencia')]
    private bool $recargoEquivalencia = false;

    #[ORM\Column]
    private bool $intracomunitario = false;

    // --- Relaciones ---

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'us', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $usuario = null;

    #[ORM\OneToOne(targetEntity: Direccion::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'direccion_facturacion_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Direccion $direccionFacturacion = null;

    #[ORM\ManyToOne(targetEntity: Tarifa::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'id_tarifa')]
    private ?Tarifa $tarifa = null;

    /** @var Collection<int, Direccion> */
    #[ORM\ManyToMany(targetEntity: Direccion::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'contacto_direcciones_envio')]
    private Collection $direccionesEnvio;

    /** @var Collection<int, Pedido> */
    #[ORM\OneToMany(mappedBy: 'contacto', targetEntity: Pedido::class, cascade: ['persist'])]
    private Collection $pedidos;

    /** @var Collection<int, PedidoTrabajo> */
    #[ORM\OneToMany(mappedBy: 'contacto', targetEntity: PedidoTrabajo::class, cascade: ['persist', 'remove'])]
    private Collection $trabajos;

    public function __construct()
    {
        $this->direccionesEnvio = new ArrayCollection();
        $this->pedidos = new ArrayCollection();
        $this->trabajos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return trim(sprintf('%s %s', $this->nombre, $this->apellidos));
    }

    /**
     * NOTA DE MIGRACIÓN: La lógica original aquí era incorrecta.
     * Se ha corregido para iterar sobre la colección de precios de la tarifa asociada.
     */
    public function getIncremento(int $cantidad): float
    {
        if (!$this->tarifa) {
            return 0.0;
        }

        $valor = 0.0;
        $precioSeleccionado = null;

        foreach ($this->tarifa->getPrecios() as $precio) {
            if ($cantidad >= $precio->getCantidad()) {
                $precioSeleccionado = $precio;
                break; // Asumiendo que los precios están ordenados DESC por cantidad
            }
        }

        if ($precioSeleccionado) {
            $valor = (float) $precioSeleccionado->getPrecio();
        }

        return $valor;
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getApellidos(): ?string { return $this->apellidos; }
    public function setApellidos(?string $apellidos): self { $this->apellidos = $apellidos; return $this; }
    public function getCif(): ?string { return $this->cif; }
    public function setCif(?string $cif): self { $this->cif = $cif; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getCiudad(): ?string { return $this->ciudad; }
    public function setCiudad(?string $ciudad): self { $this->ciudad = $ciudad; return $this; }
    public function getUsuario(): ?User { return $this->usuario; }
    public function setUsuario(?User $usuario): self { $this->usuario = $usuario; return $this; }
    public function getTelefonoMovil(): ?string { return $this->telefonoMovil; }
    public function setTelefonoMovil(?string $telefonoMovil): self { $this->telefonoMovil = $telefonoMovil; return $this; }
    public function getTelefonoOtro(): ?string { return $this->telefonoOtro; }
    public function setTelefonoOtro(?string $telefonoOtro): self { $this->telefonoOtro = $telefonoOtro; return $this; }
    public function getDireccionFacturacion(): ?Direccion { return $this->direccionFacturacion; }
    public function setDireccionFacturacion(?Direccion $direccionFacturacion): self { $this->direccionFacturacion = $direccionFacturacion; return $this; }
    public function getTarifa(): ?Tarifa { return $this->tarifa; }
    public function setTarifa(?Tarifa $tarifa): self { $this->tarifa = $tarifa; return $this; }
    public function isRecargoEquivalencia(): bool { return $this->recargoEquivalencia; }
    public function setRecargoEquivalencia(bool $recargoEquivalencia): self { $this->recargoEquivalencia = $recargoEquivalencia; return $this; }
    public function isIntracomunitario(): bool { return $this->intracomunitario; }
    public function setIntracomunitario(bool $intracomunitario): self { $this->intracomunitario = $intracomunitario; return $this; }

    /** @return Collection<int, Direccion> */
    public function getDireccionesEnvio(): Collection { return $this->direccionesEnvio; }
    public function addDireccionesEnvio(Direccion $direccion): self { if (!$this->direccionesEnvio->contains($direccion)) { $this->direccionesEnvio->add($direccion); } return $this; }
    public function removeDireccionesEnvio(Direccion $direccion): self { $this->direccionesEnvio->removeElement($direccion); return $this; }

    /** @return Collection<int, Pedido> */
    public function getPedidos(): Collection { return $this->pedidos; }
    public function addPedido(Pedido $pedido): self { if (!$this->pedidos->contains($pedido)) { $this->pedidos->add($pedido); $pedido->setContacto($this); } return $this; }
    public function removePedido(Pedido $pedido): self { if ($this->pedidos->removeElement($pedido)) { if ($pedido->getContacto() === $this) { $pedido->setContacto(null); } } return $this; }

    /** @return Collection<int, PedidoTrabajo> */
    public function getTrabajos(): Collection { return $this->trabajos; }
    public function addTrabajo(PedidoTrabajo $trabajo): self { if (!$this->trabajos->contains($trabajo)) { $this->trabajos->add($trabajo); $trabajo->setContacto($this); } return $this; }
    public function removeTrabajo(PedidoTrabajo $trabajo): self { if ($this->trabajos->removeElement($trabajo)) { if ($trabajo->getContacto() === $this) { $trabajo->setContacto(null); } } return $this; }
}