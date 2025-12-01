<?php

namespace App\Entity;

use App\Repository\ColorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ColorRepository::class)]
#[ORM\Table(name: 'color')]
#[ORM\HasLifecycleCallbacks]
class Color
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $id = null;

    #[ORM\Column(name: 'titulo', length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[ORM\Column(name: 'codigoRGB', length: 100)]
    private string $codigoRGB = 'FFFFFF';

    #[ORM\Column(name: 'RGB127', length: 100)]
    private string $rgbUnificado = '#FFFFFF';

    #[ORM\Column(name: 'nombre_unificado', length: 100)]
    private ?string $nombreUnificado = '#FFFFFF';

    #[ORM\Column(name: 'codigoColor', length: 100, nullable: true)]
    private ?string $codigoColor = null;

    // --- Relaciones ---

    #[ORM\ManyToOne(inversedBy: 'colores')]
    #[ORM\JoinColumn(name: 'proveedor', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Proveedor $proveedor = null;

    #[ORM\ManyToOne(inversedBy: 'colores')]
    #[ORM\JoinColumn(name: 'fabricante', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Fabricante $fabricante = null;

    /** @var Collection<int, Producto> */
    #[ORM\OneToMany(mappedBy: 'color', targetEntity: Producto::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'LAZY')]
    private Collection $productos;

    public function __construct()
    {
        $this->productos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateNombreUrl(): void
    {
        if (null !== $this->getNombre()) {
            $this->nombreUrl = $this->slugify($this->getNombre());
        }
    }

    private function slugify(string $text): string
    {
        $slugger = new AsciiSlugger();
        return $slugger->slug($text)->lower()->toString();
    }

    // --- Getters y Setters ---

    public function getId(): ?string { return $this->id; }
    public function setId(string $id): self { $this->id = $id; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getNombreUrl(): ?string { return $this->nombreUrl; }
    public function setNombreUrl(string $nombreUrl): self { $this->nombreUrl = $nombreUrl; return $this; }
    public function getCodigoRGB(): string { return $this->codigoRGB; }
    public function setCodigoRGB(string $codigoRGB): self { $this->codigoRGB = $codigoRGB; return $this; }
    public function getRgbUnificado(): string { return $this->rgbUnificado; }
    public function setRgbUnificado(string $rgbUnificado): self { $this->rgbUnificado = $rgbUnificado; return $this; }
    public function getNombreUnificado(): ?string { return $this->nombreUnificado; }
    public function setNombreUnificado(string $nombreUnificado): self { $this->nombreUnificado = $nombreUnificado; return $this; }
    public function getCodigoColor(): ?string { return $this->codigoColor; }
    public function setCodigoColor(?string $codigoColor): self { $this->codigoColor = $codigoColor; return $this; }
    public function getProveedor(): ?Proveedor { return $this->proveedor; }
    public function setProveedor(?Proveedor $proveedor): self { $this->proveedor = $proveedor; return $this; }
    public function getFabricante(): ?Fabricante { return $this->fabricante; }
    public function setFabricante(?Fabricante $fabricante): self { $this->fabricante = $fabricante; return $this; }

    /** @return Collection<int, Producto> */
    public function getProductos(): Collection { return $this->productos; }
    public function addProducto(Producto $producto): self { if (!$this->productos->contains($producto)) { $this->productos->add($producto); $producto->setColor($this); } return $this; }
    public function removeProducto(Producto $producto): self { if ($this->productos->removeElement($producto)) { if ($producto->getColor() === $this) { $producto->setColor(null); } } return $this; }

    /**
     * Comprueba si el color es una tonalidad de blanco o natural.
     * La comprobación es insensible a mayúsculas/minúsculas y busca sinónimos.
     *
     * @return bool
     */
    public function isBlanco(): bool
    {
        // Lista de sinónimos para "blanco" en minúsculas.
        // Puedes añadir más si los necesitas (ej. 'off-white', 'crudo', etc.).
        $sinonimosBlanco = [
            'blanco',
            'white',
            'natural',
        ];

        // Convertimos el nombre del color a minúsculas para una comparación segura.
        $nombreColor = mb_strtolower($this->getNombre());

        // Recorremos la lista de sinónimos.
        foreach ($sinonimosBlanco as $sinonimo) {
            // str_contains() comprueba si el nombre del color contiene el sinónimo.
            if (str_contains($nombreColor, $sinonimo)) {
                return true; // Si encuentra una coincidencia, es blanco.
            }
        }

        return false; // Si termina el bucle sin encontrar coincidencias, no es blanco.
    }
}