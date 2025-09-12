<?php

namespace App\Entity;

use App\Repository\ModeloHasTecnicasEstampadoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeloHasTecnicasEstampadoRepository::class)]
#[ORM\Table(name: 'modelo_tecnicas_estampado')]
class ModeloHasTecnicasEstampado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Personalizacion::class, inversedBy: 'modelos')]
    #[ORM\JoinColumn(name: 'personalizacion_id', referencedColumnName: 'codigo', nullable: false)]
    private ?Personalizacion $personalizacion = null;

    #[ORM\ManyToOne(targetEntity: Modelo::class, inversedBy: 'tecnicas')]
    #[ORM\JoinColumn(name: 'modelo_id', referencedColumnName: 'id', nullable: false)]
    private ?Modelo $modelo = null;

    #[ORM\Column]
    private int $maxcolores = 0;

    /**
     * @var Collection<int, AreasTecnicasEstampado>
     */
    #[ORM\OneToMany(mappedBy: 'tecnica', targetEntity: AreasTecnicasEstampado::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'DESC'])]
    private Collection $areas;

    public function __construct()
    {
        // Corregido: La propiedad se llama 'areas', no 'tecnicas'.
        $this->areas = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->personalizacion?->getNombre() ?? 'Técnica no definida';
    }

    /**
     * Método proxy para obtener el nombre de la personalización de forma segura.
     */
    public function getNombre(): ?string
    {
        return $this->personalizacion?->getNombre();
    }

    // --- Getters y Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonalizacion(): ?Personalizacion
    {
        return $this->personalizacion;
    }

    public function setPersonalizacion(?Personalizacion $personalizacion): self
    {
        $this->personalizacion = $personalizacion;
        return $this;
    }

    public function getModelo(): ?Modelo
    {
        return $this->modelo;
    }

    public function setModelo(?Modelo $modelo): self
    {
        $this->modelo = $modelo;
        return $this;
    }

    public function getMaxcolores(): int
    {
        return $this->maxcolores;
    }

    public function setMaxcolores(int $maxcolores): self
    {
        $this->maxcolores = $maxcolores;
        return $this;
    }

    /**
     * @return Collection<int, AreasTecnicasEstampado>
     */
    public function getAreas(): Collection
    {
        return $this->areas;
    }

    public function addArea(AreasTecnicasEstampado $area): self
    {
        if (!$this->areas->contains($area)) {
            $this->areas->add($area);
            $area->setTecnica($this);
        }
        return $this;
    }

    public function removeArea(AreasTecnicasEstampado $area): self
    {
        if ($this->areas->removeElement($area)) {
            // set the owning side to null (unless already changed)
            if ($area->getTecnica() === $this) {
                $area->setTecnica(null);
            }
        }
        return $this;
    }
}