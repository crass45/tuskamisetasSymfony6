<?php

namespace App\Entity;

use App\Repository\AreasTecnicasEstampadoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AreasTecnicasEstampadoRepository::class)]
#[ORM\Table(name: 'areas_tecnicas_estampado')]
class AreasTecnicasEstampado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * NOTA DE CORRECCIÓN: La relación original apuntaba a 'Personalizacion',
     * pero el 'inversedBy' ("areas") está en 'ModeloHasTecnicasEstampado'.
     * Se ha corregido el 'targetEntity' para que apunte a la clase correcta.
     */
    #[ORM\ManyToOne(targetEntity: ModeloHasTecnicasEstampado::class, inversedBy: 'areas')]
    #[ORM\JoinColumn(name: 'area_tecnica_id', referencedColumnName: 'id', nullable: false)]
    private ?ModeloHasTecnicasEstampado $tecnica = null;

    #[ORM\Column(name: 'areawidth', type: 'decimal', precision: 10, scale: 4)]
    private ?string $areawidth = '0.0000';

    // NOTA: El nombre de la columna parece tener una errata ('hight' en lugar de 'height'). Se ha conservado el original.
    #[ORM\Column(name: 'areahight', type: 'decimal', precision: 10, scale: 4)]
    private ?string $areahight = '0.0000';

    #[ORM\Column(length: 45)]
    private ?string $areaname = '';

    #[ORM\Column(length: 200)]
    private ?string $areaimg = '';

    #[ORM\Column]
    private int $maxcolores = 0;

    public function __toString(): string
    {
        return $this->areaname ?? 'Nueva Área';
    }

    // --- Getters y Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTecnica(): ?ModeloHasTecnicasEstampado
    {
        return $this->tecnica;
    }

    public function setTecnica(?ModeloHasTecnicasEstampado $tecnica): self
    {
        $this->tecnica = $tecnica;
        return $this;
    }

    public function getAreawidth(): ?string
    {
        return $this->areawidth;
    }

    public function setAreawidth(string $areawidth): self
    {
        $this->areawidth = $areawidth;
        return $this;
    }

    public function getAreahight(): ?string
    {
        return $this->areahight;
    }

    public function setAreahight(string $areahight): self
    {
        $this->areahight = $areahight;
        return $this;
    }

    public function getAreaname(): ?string
    {
        return $this->areaname;
    }

    public function setAreaname(string $areaname): self
    {
        $this->areaname = $areaname;
        return $this;
    }

    public function getAreaimg(): ?string
    {
        return $this->areaimg;
    }

    public function setAreaimg(string $areaimg): self
    {
        $this->areaimg = $areaimg;
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
}