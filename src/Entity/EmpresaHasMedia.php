<?php

namespace App\Entity;

use App\Repository\EmpresaHasMediaRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Sonata\Media;

#[ORM\Entity(repositoryClass: EmpresaHasMediaRepository::class)]
#[ORM\Table(name: 'media__empresa_media')]
class EmpresaHasMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * NOTA: He renombrado la propiedad de 'gallery' a 'empresa' para que sea más claro.
     */
    #[ORM\ManyToOne(targetEntity: Empresa::class, inversedBy: 'galleryHasMedias')]
    #[ORM\JoinColumn(name: 'gallery_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?Empresa $empresa = null;

    /**
     * NOTA: He eliminado el 'inversedBy' en esta relación. La entidad Media de Sonata
     * no tiene una colección 'galleryHasMedias', por lo que la relación debe ser unidireccional.
     */
    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private ?Media $media = null;

    /**
     * NOTA: Esta propiedad no estaba mapeada a la base de datos. He añadido #[ORM\Column].
     */
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function __toString(): string
    {
        return sprintf('Imagen %s para Empresa %d', $this->media?->getName() ?? 'N/A', $this->empresa?->getId() ?? 0);
    }

    // --- Getters y Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): self
    {
        $this->empresa = $empresa;
        return $this;
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): self
    {
        $this->media = $media;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }
}