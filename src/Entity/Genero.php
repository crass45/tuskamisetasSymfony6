<?php

namespace App\Entity;

use App\Repository\GeneroRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: GeneroRepository::class)]
#[ORM\Table(name: 'gender')]
#[ORM\HasLifecycleCallbacks]
class Genero
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\OneToMany(mappedBy: 'gender', targetEntity: Modelo::class, cascade: ['persist', 'remove'], orphanRemoval: false, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $modelos;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Nuevo Género';
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

    // Opcional, ya que se genera automáticamente
    public function setNombreUrl(string $nombreUrl): self
    {
        $this->nombreUrl = $nombreUrl;
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
            $modelo->setGender($this);
        }
        return $this;
    }

    public function removeModelo(Modelo $modelo): self
    {
        if ($this->modelos->removeElement($modelo)) {
            // set the owning side to null (unless already changed)
            if ($modelo->getGender() === $this) {
                $modelo->setGender(null);
            }
        }
        return $this;
    }
}