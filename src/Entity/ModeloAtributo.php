<?php

namespace App\Entity;

use App\Repository\ModeloAtributoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeloAtributoRepository::class)]
#[ORM\Table(name: 'modelo_atributo')]
#[ORM\HasLifecycleCallbacks]
class ModeloAtributo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[ORM\Column(length: 100)]
    private ?string $valor = null;

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\ManyToMany(targetEntity: Modelo::class, mappedBy: 'atributos')]
    private Collection $modelos;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre."(".$this->valor.")" ?? '';
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
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?? '';
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }

    // --- Getters y Setters ---

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

    public function getNombreUrl(): ?string
    {
        return $this->nombreUrl;
    }

    // El setter para nombreUrl es opcional, ya que se genera automáticamente.
    // Se puede mantener si necesitas establecerlo manualmente en alguna ocasión.
    public function setNombreUrl(string $nombreUrl): self
    {
        $this->nombreUrl = $nombreUrl;
        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(string $valor): self
    {
        $this->valor = $valor;
        return $this;
    }

    /**
     * @return Collection<int, Modelo>
     */
    public function getModelos(): Collection
    {
        return $this->modelos;
    }

    public function addModelo(Modelo $modelo): self
    {
        if (!$this->modelos->contains($modelo)) {
            $this->modelos->add($modelo);
            $modelo->addAtributo($this); // Sincroniza el lado propietario
        }
        return $this;
    }

    public function removeModelo(Modelo $modelo): self
    {
        if ($this->modelos->removeElement($modelo)) {
            $modelo->removeAtributo($this); // Sincroniza el lado propietario
        }
        return $this;
    }
}