<?php

namespace App\Entity;

use App\Repository\EstadoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstadoRepository::class)]
#[ORM\Table(name: 'pedido_estado')]
class Estado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'codigoRGB', length: 7)]
    private string $codigoRGB = '#FFFFFF';

    #[ORM\Column(name: 'envia_correo')]
    private int $enviaCorreo = 0;

    public function __toString(): string
    {
        return $this->nombre ?? 'Estado sin nombre';
    }

    /**
     * Devuelve un nombre simplificado para el cliente en ciertos estados.
     */
    public function getNombreParaCliente(): string
    {
        if (in_array($this->id, [5, 7, 8], true)) {
            return "En Proceso";
        }

        return $this->nombre ?? 'Estado desconocido';
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

    public function getCodigoRGB(): string
    {
        return $this->codigoRGB;
    }

    public function setCodigoRGB(string $codigoRGB): self
    {
        $this->codigoRGB = $codigoRGB;
        return $this;
    }

    /**
     * @return int
     */
    public function getEnviaCorreo(): int
    {
        return $this->enviaCorreo;
    }

    public function setEnviaCorreo(bool $enviaCorreo): self
    {
        $this->enviaCorreo = $enviaCorreo;
        return $this;
    }
}