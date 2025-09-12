<?php

namespace App\Entity;

use App\Repository\OfertaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Sonata\Media; // Asegúrate de que esta ruta es correcta

#[ORM\Entity(repositoryClass: OfertaRepository::class)]
#[ORM\Table(name: 'oferta')]
#[ORM\HasLifecycleCallbacks]
class Oferta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(name: 'titulo_seo', length: 100, nullable: true)]
    private ?string $tituloSEO = '';

    #[ORM\Column(name: 'descripcion_seo', type: Types::TEXT)]
    private ?string $descripcionSEO = '';

    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'imagen', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Media $imagen = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo = false;

    #[ORM\Column(nullable: true)]
    private ?int $cantidadMinima = null;

    #[ORM\Column(name: 'incrementoCantidadMinima', nullable: true)]
    private ?int $incrementoCantidadMinima = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 3)]
    private ?string $precio = '0.000';

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\ManyToMany(targetEntity: Modelo::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'oferta_modelo')]
    #[ORM\JoinColumn(name: 'oferta_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'modelo_id', referencedColumnName: 'id')]
    private Collection $modelos;

    /**
     * @var Collection<int, ModeloHasTecnicasEstampado>
     */
    // NOTA: Esta relación sigue pareciendo incorrecta ('mappedBy: "modelo"'). Se ha migrado tal cual, pero debería ser revisada en el futuro.
    #[ORM\OneToMany(mappedBy: 'modelo', targetEntity: ModeloHasTecnicasEstampado::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['personalizacion' => 'ASC'])]
    private Collection $tecnicas;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
        $this->tecnicas = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Nueva Oferta';
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateNombreUrl(): void
    {
        if ($this->nombre) {
            $this->nombreUrl = $this->slugify('Oferta ' . $this->nombre);
        }
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text ?: 'n-a';
    }

    public function getProductos(): Collection
    {
        $listaProductos = new ArrayCollection();
        $listaColoresNombres = [];

        foreach ($this->modelos as $modelo) {
            // Asume que getColoresProductos() devuelve una colección de 'Producto'
            foreach ($modelo->getColoresProductos() as $producto) {
                $colorNombre = $producto->getColor()?->getNombre();
                if ($colorNombre && !in_array($colorNombre, $listaColoresNombres, true)) {
                    $listaColoresNombres[] = $colorNombre;
                    $listaProductos->add($producto);
                }
            }
        }
        return $listaProductos;
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): self { $this->descripcion = $descripcion; return $this; }
    public function getTituloSEO(): ?string { return $this->tituloSEO; }
    public function setTituloSEO(?string $tituloSEO): self { $this->tituloSEO = $tituloSEO; return $this; }
    public function getDescripcionSEO(): ?string { return $this->descripcionSEO; }
    public function setDescripcionSEO(string $descripcionSEO): self { $this->descripcionSEO = $descripcionSEO; return $this; }
    public function getNombreUrl(): ?string { return $this->nombreUrl; }
    public function setNombreUrl(string $nombreUrl): self { $this->nombreUrl = $nombreUrl; return $this; }
    public function getImagen(): ?Media { return $this->imagen; }
    public function setImagen(?Media $imagen): self { $this->imagen = $imagen; return $this; }
    public function isActivo(): bool { return $this->activo; }
    public function setActivo(bool $activo): self { $this->activo = $activo; return $this; }
    public function getCantidadMinima(): ?int { return $this->cantidadMinima; }
    public function setCantidadMinima(?int $cantidadMinima): self { $this->cantidadMinima = $cantidadMinima; return $this; }
    public function getIncrementoCantidadMinima(): ?int { return $this->incrementoCantidadMinima; }
    public function setIncrementoCantidadMinima(?int $incrementoCantidadMinima): self { $this->incrementoCantidadMinima = $incrementoCantidadMinima; return $this; }
    public function getPrecio(): ?string { return $this->precio; }
    public function setPrecio(string $precio): self { $this->precio = $precio; return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelos(): Collection { return $this->modelos; }
    public function addModelo(Modelo $modelo): self
    {
        if (!$this->modelos->contains($modelo)) {
            $this->modelos->add($modelo);
        }
        return $this;
    }
    public function removeModelo(Modelo $modelo): self
    {
        $this->modelos->removeElement($modelo);
        return $this;
    }

    /** @return Collection<int, ModeloHasTecnicasEstampado> */
    public function getTecnicas(): Collection { return $this->tecnicas; }
    // NOTA: El método original 'addTecnica' esperaba un objeto 'Personalizacion', lo cual es un error de tipo.
    // Lo he corregido para que espere el tipo correcto 'ModeloHasTecnicasEstampado'. Deberás revisar su uso.
    public function addTecnica(ModeloHasTecnicasEstampado $tecnica): self
    {
        if (!$this->tecnicas->contains($tecnica)) {
            $this->tecnicas->add($tecnica);
        }
        return $this;
    }
    public function removeTecnica(ModeloHasTecnicasEstampado $tecnica): self
    {
        $this->tecnicas->removeElement($tecnica);
        return $this;
    }
}