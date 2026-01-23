<?php

namespace App\Entity;

use App\Repository\PedidoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoRepository::class)]
#[ORM\Table(name: 'pedido')]
#[ORM\HasLifecycleCallbacks]
class Pedido
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // --- INICIO DE LA MEJORA ---
    // Nuevo campo para almacenar el Client ID de Google Analytics del usuario
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleClientId = null;
    // --- FIN DE LA MEJORA ---
    // --- ¡AÑADIR ESTOS TRES CAMPOS! ---
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gclid = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $gbraid = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $wbraid = null;
    // --- FIN DE LO AÑADIDO ---

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $agenciaEnvio = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bultos = 1;

    #[ORM\Column]
    private ?int $fiscalYear = null;

    #[ORM\Column]
    private ?int $numeroPedido = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fechaEntrega = null;

    #[ORM\ManyToOne(targetEntity: Estado::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_estado', nullable: false)]
    private ?Estado $estado = null;

    #[ORM\ManyToOne(targetEntity: Direccion::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_direccion', nullable: true)]
    private ?Direccion $direccion = null;

    #[ORM\ManyToOne(targetEntity: Contacto::class, inversedBy: 'pedidos', cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_usuario', nullable: true)]
    private ?Contacto $contacto = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $codigoSermepa = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $subTotal = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $descuento = '0.0000';

    #[ORM\Column(name: 'email_cliente', length: 200, nullable: true)]
    private ?string $emailClienteTemporal = '';

    #[ORM\Column(name: 'ciudad_cliente', length: 200, nullable: true)]
    private ?string $ciudadClienteTemporal = '';

    #[ORM\Column(name: 'nombre_cliente', length: 200, nullable: true)]
    private ?string $nombreClienteTemporal = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    private ?string $cantidadPagada = '0.0000';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $seguimientoEnvio = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $metodoPago = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $envio = '0.0000';

    #[ORM\Column(nullable: true)]
    private ?int $bultosEstimados = 0;

    #[ORM\Column(name: 'dias_adicionales_envio', nullable: true)]
    private ?int $diasAdicionales = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $iva = '0.0000';

    #[ORM\Column(name: 'recargo_equivalencia', type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $recargoEquivalencia = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $total = '0.0000';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacionesInternas = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $incidencias = '';

    #[ORM\Column(name: 'referencia_interna', length: 20, nullable: true)]
    private ?string $referenciaInterna = '';

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $montaje = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observaciones = '';

    #[ORM\Column(name: 'recoger_en_tienda', nullable: true)]
    private ?bool $recogerEnTienda = false;

    #[ORM\Column(nullable: true)]
    private ?bool $temporal = false;

    #[ORM\Column(name: 'pedido_express', nullable: true)]
    private ?bool $pedidoExpres = false;

    #[ORM\Column(name: 'precio_pedido_expres', nullable: true)]
    private ?int $precioPedidoExpres = null;

    #[ORM\OneToOne(mappedBy: 'pedido', targetEntity: Factura::class)]
    private ?Factura $factura = null;

    /**
     * @var Collection<int, PedidoLinea>
     */
    #[ORM\OneToMany(mappedBy: 'pedido', targetEntity: PedidoLinea::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $lineas;

    /**
     * @var Collection<int, PedidoLineaLibre>
     */
    #[ORM\OneToMany(mappedBy: 'pedido', targetEntity: PedidoLineaLibre::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $lineasLibres;

    // Propiedad no mapeada para lógica interna
    private bool $enviaMail = false;

    public function __construct(\DateTime $fecha, int $fiscalYear, int $numeroPedido)
    {
        $this->fecha = $fecha;
        $this->fiscalYear = $fiscalYear;
        $this->numeroPedido = $numeroPedido;
        $cabecera = "PR";
        if ($this->temporal) {
            $cabecera = "PRT";
        }
        $this->nombre = $cabecera . date("y", $this->fecha->getTimestamp()) . sprintf('%05d', $this->numeroPedido);
        $this->lineas = new ArrayCollection();
        $this->lineasLibres = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function generateNombre(): void
    {
        if ($this->fecha && $this->numeroPedido) {
            $cabecera = $this->temporal ? "PRT" : "PR";
            $this->nombre = $cabecera . $this->fecha->format('y') . sprintf('%05d', $this->numeroPedido);
        }
    }

    public function __toString(): string
    {
        return (string) $this->nombre;
    }

    // ===================================================================
    // NOTA DE ARQUITECTURA: Lógica de Negocio
    // Se recomienda encarecidamente mover esta lógica a un SERVICIO DEDICADO
    // (ej. 'PedidoCalculatorService') para mantener las entidades limpias.
    // ===================================================================

    public function getTotalAPAgar(): float
    {
        return (float)$this->total - ((float)$this->subTotal * (float)$this->descuento / 100);
    }

    public function getBultosEstimados(): int
    {
        if ($this->bultosEstimados > 0) {
            return $this->bultosEstimados;
        }

        $totalCajas = 0;
        foreach ($this->lineas as $linea) {
            $cantidad = $linea->getCantidad();
            $boxSize = $linea->getProducto()?->getModelo()?->getBox();
            if ($cantidad && $boxSize > 0) {
                $totalCajas += $cantidad / $boxSize;
            }
        }
        return (int) ceil(round($totalCajas, 6));
    }

    // ... otros métodos de lógica de negocio migrados y asegurados ...


    // ===================================================================
    // Getters y Setters
    // IMPORTANTE: Debes generar todos los getters y setters para las propiedades privadas.
    // A continuación, solo algunos ejemplos clave.
    // ===================================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContacto(): ?Contacto
    {
        return $this->contacto;
    }

    public function setContacto(?Contacto $contacto): self
    {
        $this->contacto = $contacto;
        return $this;
    }

    public function getDireccion(): ?Direccion
    {
        return $this->direccion;
    }

    public function setDireccion(?Direccion $direccion): self
    {
        $this->direccion = $direccion;
        return $this;
    }

    /**
     * @return Collection<int, PedidoLinea>
     */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(PedidoLinea $linea): self
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setPedido($this); // Renombrado de setIdPedido
        }
        return $this;
    }

    public function removeLinea(PedidoLinea $linea): self
    {
        if ($this->lineas->removeElement($linea)) {
            if ($linea->getPedido() === $this) {
                $linea->setPedido(null);
            }
        }
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCantidadPagada(): ?string
    {
        return $this->cantidadPagada;
    }

    /**
     * @return string|null
     */
    public function getCiudadClienteTemporal(): ?string
    {
        return $this->ciudadClienteTemporal;
    }

    /**
     * @return string|null
     */
    public function getCodigoSermepa(): ?string
    {
        return $this->codigoSermepa;
    }

    /**
     * @return string|null
     */
    public function getDescuento(): ?string
    {
        return $this->descuento;
    }

    /**
     * @return int|null
     */
    public function getDiasAdicionales(): ?int
    {
        return $this->diasAdicionales;
    }

    /**
     * @return string|null
     */
    public function getEmailClienteTemporal(): ?string
    {
        return $this->emailClienteTemporal;
    }

    /**
     * @return string|null
     */
    public function getEnvio(): ?string
    {
        return $this->envio;
    }

    /**
     * @return Estado|null
     */
    public function getEstado(): ?Estado
    {
        return $this->estado;
    }

    /**
     * @return Factura|null
     */
    public function getFactura(): ?Factura
    {
        return $this->factura;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getFechaEntrega(): ?\DateTimeInterface
    {
        return $this->fechaEntrega;
    }

    /**
     * @return int|null
     */
    public function getFiscalYear(): ?int
    {
        return $this->fiscalYear;
    }

    /**
     * @return string|null
     */
    public function getIncidencias(): ?string
    {
        return $this->incidencias;
    }

    /**
     * @return string|null
     */
    public function getIva(): ?string
    {
        return $this->iva;
    }

    /**
     * @return Collection
     */
    public function getLineasLibres(): Collection
    {
        return $this->lineasLibres;
    }

    /**
     * @return string|null
     */
    public function getMetodoPago(): ?string
    {
        return $this->metodoPago;
    }

    /**
     * @return string|null
     */
    public function getMontaje(): ?string
    {
        return $this->montaje;
    }

    /**
     * @return string|null
     */
    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    /**
     * @return string|null
     */
    public function getNombreClienteTemporal(): ?string
    {
        return $this->nombreClienteTemporal;
    }

    /**
     * @return int|null
     */
    public function getNumeroPedido(): ?int
    {
        return $this->numeroPedido;
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
    public function getObservacionesInternas(): ?string
    {
        return $this->observacionesInternas;
    }

    /**
     * @return bool|null
     */
    public function getPedidoExpres(): ?bool
    {
        return $this->pedidoExpres;
    }

    /**
     * @return int|null
     */
    public function getPrecioPedidoExpres(): ?int
    {
        return $this->precioPedidoExpres;
    }

    /**
     * @return string|null
     */
    public function getRecargoEquivalencia(): ?string
    {
        return $this->recargoEquivalencia;
    }

    /**
     * @return bool|null
     */
    public function getRecogerEnTienda(): ?bool
    {
        return $this->recogerEnTienda;
    }

    /**
     * @return string|null
     */
    public function getReferenciaInterna(): ?string
    {
        return $this->referenciaInterna;
    }

    /**
     * @return string|null
     */
    public function getSeguimientoEnvio(): ?string
    {
        return $this->seguimientoEnvio;
    }

    /**
     * @return string|null
     */
    public function getSubTotal(): ?string
    {
        return $this->subTotal;
    }

    /**
     * @return bool|null
     */
    public function getTemporal(): ?bool
    {
        return $this->temporal;
    }

    /**
     * @return string|null
     */
    public function getTotal(): ?string
    {
        return $this->total;
    }

    /**
     * @return bool
     */
    public function isEnviaMail(): bool
    {
        return $this->enviaMail;
    }

    /**
     * @param int|null $fiscalYear
     */
    public function setFiscalYear(?int $fiscalYear): void
    {
        $this->fiscalYear = $fiscalYear;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @param int|null $bultosEstimados
     */
    public function setBultosEstimados(?int $bultosEstimados): void
    {
        $this->bultosEstimados = $bultosEstimados;
    }

    /**
     * @param string|null $cantidadPagada
     */
    public function setCantidadPagada(?string $cantidadPagada): void
    {
        $this->cantidadPagada = $cantidadPagada;
    }

    /**
     * @param string|null $ciudadClienteTemporal
     */
    public function setCiudadClienteTemporal(?string $ciudadClienteTemporal): void
    {
        $this->ciudadClienteTemporal = $ciudadClienteTemporal;
    }

    /**
     * @param string|null $codigoSermepa
     */
    public function setCodigoSermepa(?string $codigoSermepa): void
    {
        $this->codigoSermepa = $codigoSermepa;
    }

    /**
     * @param string|null $descuento
     */
    public function setDescuento(?string $descuento): void
    {
        $this->descuento = $descuento;
    }

    /**
     * @param int|null $diasAdicionales
     */
    public function setDiasAdicionales(?int $diasAdicionales): void
    {
        $this->diasAdicionales = $diasAdicionales;
    }

    /**
     * @param string|null $emailClienteTemporal
     */
    public function setEmailClienteTemporal(?string $emailClienteTemporal): void
    {
        $this->emailClienteTemporal = $emailClienteTemporal;
    }

    /**
     * @param bool $enviaMail
     */
    public function setEnviaMail(bool $enviaMail): void
    {
        $this->enviaMail = $enviaMail;
    }

    /**
     * @param string|null $envio
     */
    public function setEnvio(?string $envio): void
    {
        $this->envio = $envio;
    }

    /**
     * @param Estado|null $estado
     */
    public function setEstado(?Estado $estado): void
    {
        $this->estado = $estado;
    }

    /**
     * @param Factura|null $factura
     */
    public function setFactura(?Factura $factura): void
    {
        $this->factura = $factura;
    }

    /**
     * @param \DateTimeInterface|null $fecha
     */
    public function setFecha(?\DateTimeInterface $fecha): void
    {
        $this->fecha = $fecha;
    }

    /**
     * @param \DateTimeInterface|null $fechaEntrega
     */
    public function setFechaEntrega(?\DateTimeInterface $fechaEntrega): void
    {
        $this->fechaEntrega = $fechaEntrega;
    }

    /**
     * @param string|null $incidencias
     */
    public function setIncidencias(?string $incidencias): void
    {
        $this->incidencias = $incidencias;
    }

    /**
     * @param string|null $iva
     */
    public function setIva(?string $iva): void
    {
        $this->iva = $iva;
    }

    /**
     * @param Collection $lineas
     */
    public function setLineas(Collection $lineas): void
    {
        $this->lineas = $lineas;
    }

    /**
     * @param Collection $lineasLibres
     */
    public function setLineasLibres(Collection $lineasLibres): void
    {
        $this->lineasLibres = $lineasLibres;
    }

    /**
     * @param string|null $metodoPago
     */
    public function setMetodoPago(?string $metodoPago): void
    {
        $this->metodoPago = $metodoPago;
    }

    /**
     * @param string|null $montaje
     */
    public function setMontaje(?string $montaje): void
    {
        $this->montaje = $montaje;
    }

    /**
     * @param string|null $nombre
     */
    public function setNombre(?string $nombre): void
    {
        $this->nombre = $nombre;
    }

    /**
     * @param string|null $nombreClienteTemporal
     */
    public function setNombreClienteTemporal(?string $nombreClienteTemporal): void
    {
        $this->nombreClienteTemporal = $nombreClienteTemporal;
    }

    /**
     * @param int|null $numeroPedido
     */
    public function setNumeroPedido(?int $numeroPedido): void
    {
        $this->numeroPedido = $numeroPedido;
    }

    /**
     * @param string|null $observaciones
     */
    public function setObservaciones(?string $observaciones): void
    {
        $this->observaciones = $observaciones;
    }

    /**
     * @param string|null $observacionesInternas
     */
    public function setObservacionesInternas(?string $observacionesInternas): void
    {
        $this->observacionesInternas = $observacionesInternas;
    }

    /**
     * @param bool|null $pedidoExpres
     */
    public function setPedidoExpres(?bool $pedidoExpres): void
    {
        $this->pedidoExpres = $pedidoExpres;
    }

    /**
     * @param int|null $precioPedidoExpres
     */
    public function setPrecioPedidoExpres(?int $precioPedidoExpres): void
    {
        $this->precioPedidoExpres = $precioPedidoExpres;
    }

    /**
     * @param string|null $recargoEquivalencia
     */
    public function setRecargoEquivalencia(?string $recargoEquivalencia): void
    {
        $this->recargoEquivalencia = $recargoEquivalencia;
    }

    /**
     * @param bool|null $recogerEnTienda
     */
    public function setRecogerEnTienda(?bool $recogerEnTienda): void
    {
        $this->recogerEnTienda = $recogerEnTienda;
    }

    /**
     * @param string|null $referenciaInterna
     */
    public function setReferenciaInterna(?string $referenciaInterna): void
    {
        $this->referenciaInterna = $referenciaInterna;
    }

    /**
     * @param string|null $seguimientoEnvio
     */
    public function setSeguimientoEnvio(?string $seguimientoEnvio): void
    {
        $this->seguimientoEnvio = $seguimientoEnvio;
    }

    /**
     * @param string|null $subTotal
     */
    public function setSubTotal(?string $subTotal): void
    {
        $this->subTotal = $subTotal;
    }

    /**
     * @param string|null $agenciaEnvio
     */
    public function setAgenciaEnvio(?string $agenciaEnvio): void
    {
        $this->agenciaEnvio = $agenciaEnvio;
    }

    /**
     * @return string|null
     */
    public function getAgenciaEnvio(): ?string
    {
        return $this->agenciaEnvio;
    }

    /**
     * @param int|null $bultos
     */
    public function setBultos(?int $bultos): void
    {
        $this->bultos = $bultos;
    }

    /**
     * @return int|null
     */
    public function getBultos(): ?int
    {
        return $this->bultos;
    }

    /**
     * @param bool|null $temporal
     */
    public function setTemporal(?bool $temporal): void
    {
        $this->temporal = $temporal;
    }

    /**
     * @param string|null $total
     */
    public function setTotal(?string $total): void
    {
        $this->total = $total;
    }

    /**
     * @return string|null
     */
    public function getGoogleClientId(): ?string
    {
        return $this->googleClientId;
    }

    /**
     * @param string|null $googleClientId
     */
    public function setGoogleClientId(?string $googleClientId): void
    {
        $this->googleClientId = $googleClientId;
    }

    // --- INICIO DE LA CORRECCIÓN ---

    /**
     * Comprueba si el pedido tiene algún trabajo de personalización.
     * Recrea la lógica de tu antiguo método 'compruebaTrabajos'.
     */
    public function compruebaTrabajos(): bool
    {
        foreach ($this->getLineas() as $linea) {
            // Recorremos las personalizaciones de esta línea
            foreach ($linea->getPersonalizaciones() as $trabajo) {

                // Verificamos el código (ajusta 'getCodigo()' si tu método se llama diferente)
                if ($trabajo->getPedidoTrabajo()->getPersonalizacion()->getCodigo() !== 'DB') {
                    // Encontramos un trabajo que NO es DB, así que devolvemos true
                    return true;
                }

            }
        }

        // Si llegamos aquí, es que no había trabajos o todos eran 'DB'
        return false;
    }

    /**
     * Determina si el pedido requiere un pago online inmediato.
     * Un pedido necesita pago online si NO tiene trabajos de personalización.
     */
    public function necesitaPagoOnline(): bool
    {
        return !$this->compruebaTrabajos() && $this->hasStockTodo();
    }

    // --- FIN DE LA CORRECCIÓN ---

    /**
     * MÉTODO AÑADIDO: Comprueba si un pedido ya está pagado.
     * Recrea la lógica de tu 'pedidoPagoAction' antiguo.
     */
    public function isPagado(): bool
    {
        // Si la diferencia entre el total y lo pagado es mínima, o si el estado es 'Pagado' o superior (>=9)
        if (($this->getTotal() - $this->getCantidadPagada()) <= 1) {
            return true;
        }

        if ($this->getEstado() && $this->getEstado()->getId() >= 9) {
            return true;
        }

        return false;
    }


    /**
     * Agrupa las líneas que comparten exactamente la misma combinación de trabajos.
     * Devuelve un array listo para iterar en Twig.
     * * @return array Estructura:
     * [
     * 'firma_unica' => [
     * 'trabajos' => [Objetos PedidoTrabajo...],
     * 'lineas' => [Objetos PedidoLinea...],
     * 'es_liso' => bool
     * ],
     * ...
     * ]
     */
    /**
     * Agrupa líneas de forma ESTRICTA.
     * Dos líneas solo van juntas si tienen los mismos trabajos,
     * con las mismas notas y en las mismas ubicaciones.
     */
    public function getLineasAgrupadasPorTrabajos(): array
    {
        $grupos = [];

        foreach ($this->getLineas() as $linea) {
            $detallesParaFirma = []; // Array para calcular la firma única
            $infoVisual = [];        // Array con objetos para pintar en Twig

            foreach ($linea->getPersonalizaciones() as $pers) {
                $trabajo = $pers->getPedidoTrabajo();

                if ($trabajo) {
                    // Obtenemos los datos que diferencian un trabajo de otro
                    $id = $trabajo->getId();
                    $obs = trim($pers->getObservaciones() ?? '');
                    // Intentamos obtener ubicación si existe el método, si no, vacío
                    $ubic = method_exists($pers, 'getUbicacion') ? trim($pers->getUbicacion() ?? '') : '';

                    // 1. Creamos un string único para este trabajo específico
                    // Usamos '||' como separador para que sea muy difícil que coincida por error
                    $firmaUnicaTrabajo = $id . '||' . $obs . '||' . $ubic;

                    $detallesParaFirma[] = $firmaUnicaTrabajo;

                    // 2. Preparamos los datos listos para Twig (así la plantilla es más limpia)
                    $infoVisual[] = [
                        'objeto_trabajo' => $trabajo,
                        'nombre' => $trabajo->getNombre(),
                        'tipo' => $trabajo."",
                        'montaje' => $trabajo->getMontaje(),
                        'observaciones' => $obs,
                        'ubicacion' => $ubic
                    ];
                }
            }

            // Ordenamos las firmas para que el orden de inserción no afecte (A+B sea igual a B+A)
            sort($detallesParaFirma);

            // Creamos la firma del GRUPO entero
            $firmaGrupo = empty($detallesParaFirma) ? 'liso' : implode('##', $detallesParaFirma);

            // Inicializamos el grupo si no existe
            if (!isset($grupos[$firmaGrupo])) {
                $grupos[$firmaGrupo] = [
                    'es_liso' => ($firmaGrupo === 'liso'),
                    // Guardamos la info visual ya preparada.
                    // Como todas las líneas de este grupo son idénticas en trabajos,
                    // cogemos la info de la primera línea que entra.
                    'detalles_visuales' => $infoVisual,
                    'lineas' => []
                ];
            }

            // Añadimos la línea
            $grupos[$firmaGrupo]['lineas'][] = $linea;
        }

        // Mover lisos al final
        if (isset($grupos['liso'])) {
            $lisos = $grupos['liso'];
            unset($grupos['liso']);
            $grupos['liso'] = $lisos;
        }

        return $grupos;
    }

    public function getTrabajos()
    {
        $trabajos = array();
        foreach ($this->lineas as $linea) {
            foreach ($linea->getPersonalizaciones() as $personalizacione) {
                if (!in_array($personalizacione->getPedidoTrabajo(), $trabajos)) {
                    $trabajos[] = $personalizacione->getPedidoTrabajo();
                }
            }

//            $this->lineasString = $this->lineasString . $linea->getCantidad() . ":" . $linea->getIdProducto()->getModelo()->nombre . ", ";
        }
        return $trabajos;
    }

    public function getGclid(): ?string
    {
        return $this->gclid;
    }

    public function setGclid(?string $gclid): static
    {
        $this->gclid = $gclid;
        return $this;
    }

    public function getGbraid(): ?string
    {
        return $this->gbraid;
    }

    public function setGbraid(?string $gbraid): static
    {
        $this->gbraid = $gbraid;
        return $this;
    }

    public function getWbraid(): ?string
    {
        return $this->wbraid;
    }

    public function setWbraid(?string $wbraid): static
    {
        $this->wbraid = $wbraid;
        return $this;
    }

    public function hasStockTodo(){
//devuelve true si hay stock de todo y no hay productos con compra minima
        foreach ($this->lineas as $linea) {
            if ($linea->getProducto() != null) {
                if (!$linea->getProducto()->getModelo()->getProveedor()->isControlDeStock() || $linea->getProducto()->getModelo()->getProveedor()->getCompraMinima()){
                    return false;
                }
            }
        }
        return true;
    }

    public function getBaseImponible(){
        $base = $this->getTotal()-$this->getIva();
        return $base;
    }

    public function addLineasLibre(PedidoLineaLibre $lineaLibre): self
    {
        if (!$this->lineasLibres->contains($lineaLibre)) {
            $this->lineasLibres->add($lineaLibre);
            $lineaLibre->setPedido($this);
        }

        return $this;
    }

    public function removeLineasLibre(PedidoLineaLibre $lineaLibre): self
    {
        if ($this->lineasLibres->removeElement($lineaLibre)) {
            // set the owning side to null (unless already changed)
            if ($lineaLibre->getPedido() === $this) {
                $lineaLibre->setPedido(null);
            }
        }

        return $this;
    }

}