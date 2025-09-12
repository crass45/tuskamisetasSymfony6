<?php

namespace App\Entity;

use App\Repository\ProveedorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProveedorRepository::class)]
#[ORM\Table(name: 'proveedor')]
#[ORM\HasLifecycleCallbacks]
class Proveedor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(name: 'nombre_url', length: 100)]
    private ?string $nombreUrl = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 3)]
    private ?string $descuentoEspecial = '0.000';

    #[ORM\Column(nullable: true)]
    private ?bool $acumulaTotal = false;

    #[ORM\Column]
    private bool $permiteVentaSinStock = false;

    #[ORM\Column]
    private int $diasEnvio = 0;

    #[ORM\Column(name: 'vende_en_pack')]
    private bool $ventaEnPack = false;

    #[ORM\Column]
    private int $compraMinima = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cuentaBancaria = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = null;

    #[ORM\Column(name: 'telefono_movil', length: 45, nullable: true)]
    private ?string $telefonoMovil = null;

    #[ORM\Column(name: 'telefono_otro', length: 45, nullable: true)]
    private ?string $telefonoOtro = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $email = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $web = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $facebook = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $twitter = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $youtube = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pinterest = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitud = 0;

    #[ORM\Column(nullable: true)]
    private ?float $latitud = 0;

    #[ORM\Column(name: 'control_de_stock')]
    private bool $controlDeStock = false;

    #[ORM\ManyToOne(targetEntity: Tarifa::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_tarifa')]
    private ?Tarifa $tarifa = null;

    #[ORM\ManyToOne(targetEntity: Direccion::class, cascade: ['all'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_direccion', onDelete: 'CASCADE')]
    private ?Direccion $direccion = null;

    /**
     * @var Collection<int, Modelo>
     */
    #[ORM\OneToMany(mappedBy: 'proveedor', targetEntity: Modelo::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $modelos;

    /**
     * @var Collection<int, Color>
     */
    #[ORM\OneToMany(mappedBy: 'proveedor', targetEntity: Color::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $colores;

    /**
     * @var Collection<int, Familia>
     */
    #[ORM\OneToMany(mappedBy: 'proveedor', targetEntity: Familia::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $familias;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
        $this->colores = new ArrayCollection();
        $this->familias = new ArrayCollection();
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
        // Esta función es un placeholder para tu antigua clase Utiles::stringURLSafe()
        $text = strtolower($text);
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        return $text;
    }

    public function getIncremento(int $cantidad): float|int
    {
        $valor = 0;
        if (null !== $this->tarifa) {
            foreach ($this->tarifa->getPrecios() as $precio) {
                if ($cantidad >= $precio->getCantidad()) {
                    // Asumimos que getPrecio() devuelve un valor comparable
                    if ($valor === 0 || $valor > (float)$precio->getPrecio()) {
                        $valor = (float)$precio->getPrecio();
                    }
                }
            }
        }
        return $valor;
    }

    // A partir de aquí, todos los Getters y Setters generados

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

    public function getDescuentoEspecial(): ?string
    {
        return $this->descuentoEspecial;
    }

    public function setDescuentoEspecial(string $descuentoEspecial): self
    {
        $this->descuentoEspecial = $descuentoEspecial;
        return $this;
    }

    public function isAcumulaTotal(): ?bool
    {
        return $this->acumulaTotal;
    }

    public function setAcumulaTotal(?bool $acumulaTotal): self
    {
        $this->acumulaTotal = $acumulaTotal;
        return $this;
    }

    public function isPermiteVentaSinStock(): bool
    {
        return $this->permiteVentaSinStock;
    }

    public function setPermiteVentaSinStock(bool $permiteVentaSinStock): self
    {
        $this->permiteVentaSinStock = $permiteVentaSinStock;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return bool|null
     */
    public function getAcumulaTotal(): ?bool
    {
        return $this->acumulaTotal;
    }

    /**
     * @return Collection
     */
    public function getColores(): ArrayCollection|Collection
    {
        return $this->colores;
    }

    /**
     * @return int
     */
    public function getCompraMinima(): int
    {
        return $this->compraMinima;
    }

    /**
     * @return string|null
     */
    public function getCuentaBancaria(): ?string
    {
        return $this->cuentaBancaria;
    }

    /**
     * @return int
     */
    public function getDiasEnvio(): int
    {
        return $this->diasEnvio;
    }

    /**
     * @return Direccion|null
     */
    public function getDireccion(): ?Direccion
    {
        return $this->direccion;
    }

    /**
     * @return string|null
     */
    public function getFacebook(): ?string
    {
        return $this->facebook;
    }

    /**
     * @return Collection
     */
    public function getFamilias(): ArrayCollection|Collection
    {
        return $this->familias;
    }

    /**
     * @return float|int|null
     */
    public function getLatitud(): float|int|null
    {
        return $this->latitud;
    }

    /**
     * @return Tarifa|null
     */
    public function getTarifa(): ?Tarifa
    {
        return $this->tarifa;
    }

    /**
     * @return float|int|null
     */
    public function getLongitud(): float|int|null
    {
        return $this->longitud;
    }

    /**
     * @return Collection
     */
    public function getModelos(): ArrayCollection|Collection
    {
        return $this->modelos;
    }

    /**
     * @return string|null
     */
    public function getObservaciones(): ?string
    {
        return $this->observaciones;
    }

    /**
     * @return string|null
     */
    public function getPinterest(): ?string
    {
        return $this->pinterest;
    }

    /**
     * @return string|null
     */
    public function getTelefonoMovil(): ?string
    {
        return $this->telefonoMovil;
    }

    /**
     * @return string|null
     */
    public function getTelefonoOtro(): ?string
    {
        return $this->telefonoOtro;
    }

    /**
     * @return string|null
     */
    public function getTwitter(): ?string
    {
        return $this->twitter;
    }

    /**
     * @return string|null
     */
    public function getWeb(): ?string
    {
        return $this->web;
    }

    /**
     * @return string|null
     */
    public function getYoutube(): ?string
    {
        return $this->youtube;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @param string|null $observaciones
     */
    public function setObservaciones(?string $observaciones): void
    {
        $this->observaciones = $observaciones;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @param Collection $colores
     */
    public function setColores(ArrayCollection|Collection $colores): void
    {
        $this->colores = $colores;
    }

    /**
     * @param int $compraMinima
     */
    public function setCompraMinima(int $compraMinima): void
    {
        $this->compraMinima = $compraMinima;
    }

    /**
     * @param bool $controlDeStock
     */
    public function setControlDeStock(bool $controlDeStock): void
    {
        $this->controlDeStock = $controlDeStock;
    }

    /**
     * @param string|null $cuentaBancaria
     */
    public function setCuentaBancaria(?string $cuentaBancaria): void
    {
        $this->cuentaBancaria = $cuentaBancaria;
    }

    /**
     * @param int $diasEnvio
     */
    public function setDiasEnvio(int $diasEnvio): void
    {
        $this->diasEnvio = $diasEnvio;
    }

    /**
     * @param Direccion|null $direccion
     */
    public function setDireccion(?Direccion $direccion): void
    {
        $this->direccion = $direccion;
    }

    /**
     * @param string|null $facebook
     */
    public function setFacebook(?string $facebook): void
    {
        $this->facebook = $facebook;
    }

    /**
     * @param Collection $familias
     */
    public function setFamilias(ArrayCollection|Collection $familias): void
    {
        $this->familias = $familias;
    }

    /**
     * @param float|int|null $latitud
     */
    public function setLatitud(float|int|null $latitud): void
    {
        $this->latitud = $latitud;
    }

    /**
     * @param float|int|null $longitud
     */
    public function setLongitud(float|int|null $longitud): void
    {
        $this->longitud = $longitud;
    }

    /**
     * @param Collection $modelos
     */
    public function setModelos(ArrayCollection|Collection $modelos): void
    {
        $this->modelos = $modelos;
    }

    /**
     * @param string|null $nombreUrl
     */
    public function setNombreUrl(?string $nombreUrl): void
    {
        $this->nombreUrl = $nombreUrl;
    }

    /**
     * @param string|null $pinterest
     */
    public function setPinterest(?string $pinterest): void
    {
        $this->pinterest = $pinterest;
    }

    /**
     * @param Tarifa|null $tarifa
     */
    public function setTarifa(?Tarifa $tarifa): void
    {
        $this->tarifa = $tarifa;
    }

    /**
     * @param string|null $telefonoMovil
     */
    public function setTelefonoMovil(?string $telefonoMovil): void
    {
        $this->telefonoMovil = $telefonoMovil;
    }

    /**
     * @param string|null $telefonoOtro
     */
    public function setTelefonoOtro(?string $telefonoOtro): void
    {
        $this->telefonoOtro = $telefonoOtro;
    }

    /**
     * @param string|null $twitter
     */
    public function setTwitter(?string $twitter): void
    {
        $this->twitter = $twitter;
    }

    /**
     * @param bool $ventaEnPack
     */
    public function setVentaEnPack(bool $ventaEnPack): void
    {
        $this->ventaEnPack = $ventaEnPack;
    }

    /**
     * @param string|null $web
     */
    public function setWeb(?string $web): void
    {
        $this->web = $web;
    }

    /**
     * @param string|null $youtube
     */
    public function setYoutube(?string $youtube): void
    {
        $this->youtube = $youtube;
    }

    /**
     * @return bool
     */
    public function isVentaEnPack(): bool
    {
        return $this->ventaEnPack;
    }

    /**
     * @return bool
     */
    public function isControlDeStock(): bool
    {
        return $this->controlDeStock;
    }
}