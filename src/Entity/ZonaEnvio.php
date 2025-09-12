<?php

namespace App\Entity;

use App\Repository\ZonaEnvioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZonaEnvioRepository::class)]
#[ORM\Table(name: 'zonas_envio')]
class ZonaEnvio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $nombre = '';

    #[ORM\Column(name: 'incremento_tiempo_pedido')]
    private int $incrementoTiempoPedido = 0;

    #[ORM\Column(name: 'envio_gratis')]
    private int $envioGratis = 0;

    /**
     * @var Collection<int, ZonaEnvioPrecioCantidad>
     */
    #[ORM\OneToMany(mappedBy: 'zonaEnvio', targetEntity: ZonaEnvioPrecioCantidad::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['bultos' => 'DESC'])]
    private Collection $precios;

    /**
     * @var Collection<int, Provincia>
     */
    #[ORM\ManyToMany(targetEntity: Provincia::class, inversedBy: 'zonasEnvio')]
    #[ORM\JoinTable(name: 'zonas_provincias')]
    private Collection $provincias;

    public function __construct()
    {
        $this->precios = new ArrayCollection();
        $this->provincias = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getIncrementoTiempoPedido(): int
    {
        return $this->incrementoTiempoPedido;
    }

    public function setIncrementoTiempoPedido(int $incrementoTiempoPedido): self
    {
        $this->incrementoTiempoPedido = $incrementoTiempoPedido;

        return $this;
    }

    public function getEnvioGratis(): int
    {
        return $this->envioGratis;
    }

    public function setEnvioGratis(int $envioGratis): self
    {
        $this->envioGratis = $envioGratis;

        return $this;
    }

    /**
     * @return Collection<int, ZonaEnvioPrecioCantidad>
     */
    public function getPrecios(): Collection
    {
        return $this->precios;
    }

    public function addPrecio(ZonaEnvioPrecioCantidad $precio): self
    {
        if (!$this->precios->contains($precio)) {
            $this->precios->add($precio);
            $precio->setZonaEnvio($this);
        }

        return $this;
    }

    public function removePrecio(ZonaEnvioPrecioCantidad $precio): self
    {
        if ($this->precios->removeElement($precio)) {
            // set the owning side to null (unless already changed)
            if ($precio->getZonaEnvio() === $this) {
                $precio->setZonaEnvio(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Provincia>
     */
    public function getProvincias(): Collection
    {
        return $this->provincias;
    }

    public function addProvincia(Provincia $provincia): self
    {
        if (!$this->provincias->contains($provincia)) {
            $this->provincias->add($provincia);
        }

        return $this;
    }

    public function removeProvincia(Provincia $provincia): self
    {
        $this->provincias->removeElement($provincia);

        return $this;
    }

    /**
     * Devuelve el precio por bulto según la cantidad.
     */
    public function getPrecio(int $numeroBultos = 1): float|int
    {
        if ($this->precios->isEmpty()) {
            return 0; // O lanzar una excepción si no tener precios es un error
        }

        // La colección ya está ordenada por bultos DESC gracias a #[ORM\OrderBy]
        foreach ($this->precios as $precio) {
            if ($numeroBultos >= $precio->getBultos()) {
                return $precio->getPrecio();
            }
        }

        // Si no se encuentra un rango, se puede usar el precio del primer elemento (el más bajo) como base
        // o definir una lógica de negocio específica.
        $precioBase = $this->precios->last() ? $this->precios->last()->getPrecio() : 0;

        return $precioBase * $numeroBultos;
    }
}