<?php

namespace App\Entity;

use App\Repository\ParametroRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametroRepository::class)]
#[ORM\Table(name: 'parametros')]
class Parametro
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'gastos_envio')]
    private ?int $gastosEnvio = null;

    #[ORM\Column(nullable: true)]
    private ?int $iva = null;

    public function __toString(): string
    {
        return sprintf('Parámetros de Configuración (ID: %d)', $this->id ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGastosEnvio(): ?int
    {
        return $this->gastosEnvio;
    }

    public function setGastosEnvio(int $gastosEnvio): self
    {
        $this->gastosEnvio = $gastosEnvio;
        return $this;
    }

    public function getIva(): ?int
    {
        return $this->iva;
    }

    public function setIva(?int $iva): self
    {
        $this->iva = $iva;
        return $this;
    }
}