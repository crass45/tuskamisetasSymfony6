<?php

namespace App\Entity;

use App\Repository\EmpresaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Sonata\Media;

#[ORM\Entity(repositoryClass: EmpresaRepository::class)]
#[ORM\Table(name: 'empresa')]
class Empresa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaInicioVacaciones = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaFinVacaciones = null;

    #[ORM\Column(length: 45)]
    private ?string $nombre = '';

    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'logo', onDelete: 'CASCADE')]
    private ?Media $logo = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimoDiasSinImprimir = null;

    #[ORM\Column(nullable: true)]
    private ?int $maximoDiasSinImprimir = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimoDiasConImpresion = null;

    #[ORM\Column(nullable: true)]
    private ?int $maximoDiasConImpresion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(name: 'telefono_movil', length: 45, nullable: true)]
    private ?string $telefonoMovil = null;

    #[ORM\Column(name: 'telefono_otro', length: 45, nullable: true)]
    private ?string $telefonoOtro = null;

    #[ORM\ManyToOne(targetEntity: Direccion::class, cascade: ['all'])]
    #[ORM\JoinColumn(name: 'id_direccion', onDelete: 'CASCADE')]
    private ?Direccion $direccion = null;

    #[ORM\ManyToOne(targetEntity: Direccion::class, cascade: ['all'])]
    #[ORM\JoinColumn(name: 'direccion_envio', onDelete: 'CASCADE')]
    private ?Direccion $direccionEnvio = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $cif = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $facebook = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $twitter = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $youtube = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $pinterest = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitud = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitud = null;

    #[ORM\Column(name: 'descripcion_contacto', type: Types::TEXT, nullable: true)]
    private ?string $descripcionContacto = null;

    #[ORM\Column(name: 'texto_legal', type: Types::TEXT, nullable: true)]
    private ?string $textoLegal = null;

    #[ORM\Column(name: 'texto_privacidad', type: Types::TEXT, nullable: true)]
    private ?string $textoPrivacidad = null;

    #[ORM\Column(name: 'texto_cookies', type: Types::TEXT, nullable: true)]
    private ?string $politicaCookies = null;

    #[ORM\Column(name: 'cuenta_bancaria', type: Types::TEXT, nullable: true)]
    private ?string $cuentaBancaria = null;

    #[ORM\Column(name: 'cuenta_paypal', type: Types::TEXT, nullable: true)]
    private ?string $cuentaPaypal = null;

    #[ORM\Column(name: 'merchant_code', type: Types::TEXT, nullable: true)]
    private ?string $merchantCode = null;

    #[ORM\Column(name: 'merchant_id', type: Types::TEXT, nullable: true)]
    private ?string $merchantId = null;

    #[ORM\Column(name: 'precio_servicio_expres', type: 'decimal', precision: 5, scale: 2)]
    private ?string $precioServicioExpres = '0.00';

    #[ORM\Column(name: 'servicio_expres_activo')]
    private bool $servicioExpresActivo = false;

    #[ORM\Column(name: 'vacaciones_activas')]
    private bool $vacacionesActivas = false;

    #[ORM\Column(name: 'recargo_equivalencia', type: 'decimal', precision: 5, scale: 3)]
    private ?string $recargoEquivalencia = '5.200';

    #[ORM\Column(name: 'iva_superreducido', nullable: true)]
    private ?int $ivaSuperreducido = null;

    #[ORM\Column(name: 'iva_reducido', nullable: true)]
    private ?int $ivaReducido = null;

    #[ORM\Column(name: 'iva_general', nullable: true)]
    private ?int $ivaGeneral = null;

    /** @var Collection<int, EmpresaHasMedia> */
    #[ORM\OneToMany(mappedBy: 'gallery', targetEntity: EmpresaHasMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'LAZY')]
    private Collection $galleryHasMedias;

    public function __construct()
    {
        $this->galleryHasMedias = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Empresa';
    }

    // --- Getters y Setters ---

    public function getId(): ?int { return $this->id; }
    public function getFechaInicioVacaciones(): ?\DateTimeInterface { return $this->fechaInicioVacaciones; }
    public function setFechaInicioVacaciones(?\DateTimeInterface $fechaInicioVacaciones): self { $this->fechaInicioVacaciones = $fechaInicioVacaciones; return $this; }
    public function getFechaFinVacaciones(): ?\DateTimeInterface { return $this->fechaFinVacaciones; }
    public function setFechaFinVacaciones(?\DateTimeInterface $fechaFinVacaciones): self { $this->fechaFinVacaciones = $fechaFinVacaciones; return $this; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): self { $this->nombre = $nombre; return $this; }
    public function getLogo(): ?Media { return $this->logo; }
    public function setLogo(?Media $logo): self { $this->logo = $logo; return $this; }
    public function getMinimoDiasSinImprimir(): ?int { return $this->minimoDiasSinImprimir; }
    public function setMinimoDiasSinImprimir(?int $minimoDiasSinImprimir): self { $this->minimoDiasSinImprimir = $minimoDiasSinImprimir; return $this; }
    public function getMaximoDiasSinImprimir(): ?int { return $this->maximoDiasSinImprimir; }
    public function setMaximoDiasSinImprimir(?int $maximoDiasSinImprimir): self { $this->maximoDiasSinImprimir = $maximoDiasSinImprimir; return $this; }
    public function getMinimoDiasConImpresion(): ?int { return $this->minimoDiasConImpresion; }
    public function setMinimoDiasConImpresion(?int $minimoDiasConImpresion): self { $this->minimoDiasConImpresion = $minimoDiasConImpresion; return $this; }
    public function getMaximoDiasConImpresion(): ?int { return $this->maximoDiasConImpresion; }
    public function setMaximoDiasConImpresion(?int $maximoDiasConImpresion): self { $this->maximoDiasConImpresion = $maximoDiasConImpresion; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): self { $this->descripcion = $descripcion; return $this; }
    public function getTelefonoMovil(): ?string { return $this->telefonoMovil; }
    public function setTelefonoMovil(?string $telefonoMovil): self { $this->telefonoMovil = $telefonoMovil; return $this; }
    public function getTelefonoOtro(): ?string { return $this->telefonoOtro; }
    public function setTelefonoOtro(?string $telefonoOtro): self { $this->telefonoOtro = $telefonoOtro; return $this; }
    public function getDireccion(): ?Direccion { return $this->direccion; }
    public function setDireccion(?Direccion $direccion): self { $this->direccion = $direccion; return $this; }
    public function getDireccionEnvio(): ?Direccion { return $this->direccionEnvio; }
    public function setDireccionEnvio(?Direccion $direccionEnvio): self { $this->direccionEnvio = $direccionEnvio; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getCif(): ?string { return $this->cif; }
    public function setCif(?string $cif): self { $this->cif = $cif; return $this; }
    public function getFacebook(): ?string { return $this->facebook; }
    public function setFacebook(?string $facebook): self { $this->facebook = $facebook; return $this; }
    public function getTwitter(): ?string { return $this->twitter; }
    public function setTwitter(?string $twitter): self { $this->twitter = $twitter; return $this; }
    public function getYoutube(): ?string { return $this->youtube; }
    public function setYoutube(?string $youtube): self { $this->youtube = $youtube; return $this; }
    public function getPinterest(): ?string { return $this->pinterest; }
    public function setPinterest(?string $pinterest): self { $this->pinterest = $pinterest; return $this; }
    public function getLongitud(): ?float { return $this->longitud; }
    public function setLongitud(?float $longitud): self { $this->longitud = $longitud; return $this; }
    public function getLatitud(): ?float { return $this->latitud; }
    public function setLatitud(?float $latitud): self { $this->latitud = $latitud; return $this; }
    public function getDescripcionContacto(): ?string { return $this->descripcionContacto; }
    public function setDescripcionContacto(?string $descripcionContacto): self { $this->descripcionContacto = $descripcionContacto; return $this; }
    public function getTextoLegal(): ?string { return $this->textoLegal; }
    public function setTextoLegal(?string $textoLegal): self { $this->textoLegal = $textoLegal; return $this; }
    public function getTextoPrivacidad(): ?string { return $this->textoPrivacidad; }
    public function setTextoPrivacidad(?string $textoPrivacidad): self { $this->textoPrivacidad = $textoPrivacidad; return $this; }
    public function getPoliticaCookies(): ?string { return $this->politicaCookies; }
    public function setPoliticaCookies(?string $politicaCookies): self { $this->politicaCookies = $politicaCookies; return $this; }
    public function getCuentaBancaria(): ?string { return $this->cuentaBancaria; }
    public function setCuentaBancaria(?string $cuentaBancaria): self { $this->cuentaBancaria = $cuentaBancaria; return $this; }
    public function getCuentaPaypal(): ?string { return $this->cuentaPaypal; }
    public function setCuentaPaypal(?string $cuentaPaypal): self { $this->cuentaPaypal = $cuentaPaypal; return $this; }
    public function getMerchantCode(): ?string { return $this->merchantCode; }
    public function setMerchantCode(?string $merchantCode): self { $this->merchantCode = $merchantCode; return $this; }
    public function getMerchantId(): ?string { return $this->merchantId; }
    public function setMerchantId(?string $merchantId): self { $this->merchantId = $merchantId; return $this; }
    public function getPrecioServicioExpres(): ?string { return $this->precioServicioExpres; }
    public function setPrecioServicioExpres(string $precioServicioExpres): self { $this->precioServicioExpres = $precioServicioExpres; return $this; }
    public function isServicioExpresActivo(): bool { return $this->servicioExpresActivo; }
    public function setServicioExpresActivo(bool $servicioExpresActivo): self { $this->servicioExpresActivo = $servicioExpresActivo; return $this; }
    public function isVacacionesActivas(): bool { return $this->vacacionesActivas; }
    public function setVacacionesActivas(bool $vacacionesActivas): self { $this->vacacionesActivas = $vacacionesActivas; return $this; }
    public function getRecargoEquivalencia(): ?string { return $this->recargoEquivalencia; }
    public function setRecargoEquivalencia(string $recargoEquivalencia): self { $this->recargoEquivalencia = $recargoEquivalencia; return $this; }
    public function getIvaSuperreducido(): ?int { return $this->ivaSuperreducido; }
    public function setIvaSuperreducido(?int $ivaSuperreducido): self { $this->ivaSuperreducido = $ivaSuperreducido; return $this; }
    public function getIvaReducido(): ?int { return $this->ivaReducido; }
    public function setIvaReducido(?int $ivaReducido): self { $this->ivaReducido = $ivaReducido; return $this; }
    public function getIvaGeneral(): ?int { return $this->ivaGeneral; }
    public function setIvaGeneral(?int $ivaGeneral): self { $this->ivaGeneral = $ivaGeneral; return $this; }

    /** @return Collection<int, EmpresaHasMedia> */
    public function getGalleryHasMedias(): Collection { return $this->galleryHasMedias; }
    public function addGalleryHasMedia(EmpresaHasMedia $galleryHasMedia): self { if (!$this->galleryHasMedias->contains($galleryHasMedia)) { $this->galleryHasMedias->add($galleryHasMedia); $galleryHasMedia->setGallery($this); } return $this; }
    public function removeGalleryHasMedia(EmpresaHasMedia $galleryHasMedia): self { if ($this->galleryHasMedias->removeElement($galleryHasMedia)) { if ($galleryHasMedia->getGallery() === $this) { $galleryHasMedia->setGallery(null); } } return $this; }

    /**
     * @param Collection $galleryHasMedias
     */
    public function setGalleryHasMedias(Collection $galleryHasMedias): void
    {
        $this->galleryHasMedias = $galleryHasMedias;
    }
}