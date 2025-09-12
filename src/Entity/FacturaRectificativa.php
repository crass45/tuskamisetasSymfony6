<?php

namespace SS\TiendaBundle\Entity;

use Application\Sonata\ClassificationBundle\Document\Category;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @ORM\Entity
 * @ORM\Table(name="factura_rectificativa")
 */
class FacturaRectificativa
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="fecha", type="date", nullable=false)
     */
    protected $fecha;


    /**
     * @var integer
     *
     * @ORM\Column(name="fiscal_year", type="integer", nullable=false)
     */
    protected  $fiscalYear;


    /**
     * @var integer
     *
     * @ORM\Column(name="numero_factura", type="integer", nullable=false)
     */
    protected $numeroFactura;


    /**
     * @var string
     *
     * @ORM\Column(name="nombre", type="string", nullable=false)
     */
    private $nombre;

    /**
     * @var string
     *
     * @ORM\Column(name="comentarios", type="text", nullable=false)
     */
    private $comentarios="";

    /**
     * @var string
     *
     * @ORM\Column(name="razon_social", type="text", nullable=false)
     */
    private $razonSocial="";

    /**
     * @var string
     *
     * @ORM\Column(name="direccion", type="text", nullable=false)
     */
    private $direccion="";

    /**
     * @var string
     *
     * @ORM\Column(name="cp", type="text", nullable=false)
     */
    private $cp="";

    /**
     * @var string
     *
     * @ORM\Column(name="poblacion", type="text", nullable=false)
     */
    private $poblacion="";

    /**
     * @var string
     *
     * @ORM\Column(name="provincia", type="text", nullable=false)
     */
    private $provincia="";

    /**
     * @var string
     *
     * @ORM\Column(name="pais", type="text", nullable=false)
     */
    private $pais="";

    /**
     * @var string
     *
     * @ORM\Column(name="cif", type="text", nullable=false)
     */
    private $cif="";


    /**
     * @var Pedido
     *
     * @ORM\OneToOne(targetEntity="SS\TiendaBundle\Entity\Pedido", inversedBy="factura", fetch="EAGER")
     *
     * @ORM\JoinColumn(name="pedido", referencedColumnName="id", onDelete="RESTRICT")
     *
     */
    protected $pedido;

    /**
     * @var Factura
     *
     * @ORM\OneToOne(targetEntity="SS\TiendaBundle\Entity\Factura", fetch="EAGER")
     *
     * @ORM\JoinColumn(name="factura", referencedColumnName="id", onDelete="RESTRICT")
     *
     */
    protected $factura;

    function __construct(\DateTime $fecha, Pedido $pedido, $fiscalYear, $numeroFactura,Factura $factura)
    {
        $this->fecha = $fecha;
        $this->pedido = $pedido;
        $this->fiscalYear = $fiscalYear;
        $this->numeroFactura = $numeroFactura;
        $this->nombre = "R" . date("y",$this->fecha->getTimestamp())."/" . sprintf('%05d', $this->numeroFactura);

        $this->razonSocial = $factura->getRazonSocial();// $pedido->getIdUsuario()->getNombre()." ".$pedido->getIdUsuario()->getApellidos();
        $this->direccion = $factura->getDireccion();// $pedido->getIdUsuario()->getDireccion()->getDir();
        $this->cp = $factura->getCp();//$pedido->getIdUsuario()->getDireccion()->getCp();
        $this->poblacion = $factura->getPoblacion();//$pedido->getIdUsuario()->getDireccion()->getPoblacion();
        $this->provincia = $factura->getProvincia();//$pedido->getIdUsuario()->getDireccion()->getProvincia();
        $this->pais = $factura->getPais();//$pedido->getIdUsuario()->getDireccion()->getPais();
        $this->cif = $factura->getCif();
        $this->factura =$factura;
    }

    function __toString()
    {
        return $this->nombre;
    }

    /**
     * @return \DateTime
     */
    public function getFecha()
    {
        return $this->fecha;
    }

    /**
     * @return int
     */
    public function getFiscalYear()
    {
        return $this->fiscalYear;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNombre()
    {
        return $this->nombre;
    }

    /**
     * @return int
     */
    public function getNumeroFactura()
    {
        return $this->numeroFactura;
    }

    /**
     * @return Pedido
     */
    public function getPedido()
    {
        return $this->pedido;
    }

    /**
     * @return string
     */
    public function getComentarios()
    {
        return $this->comentarios;
    }

    /**
     * @param string $comentarios
     */
    public function setComentarios($comentarios)
    {
        $this->comentarios = $comentarios;
    }

    /**
     * @param Pedido $pedido
     */
    public function setPedido($pedido)
    {
        $this->pedido = $pedido;
    }

    /**
     * @param int $numeroFactura
     */
    public function setNumeroFactura($numeroFactura)
    {
        $this->numeroFactura = $numeroFactura;
    }

    /**
     * @param string $nombre
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param \DateTime $fecha
     */
    public function setFecha($fecha)
    {
        $this->fecha = $fecha;
    }

    /**
     * @param int $fiscalYear
     */
    public function setFiscalYear($fiscalYear)
    {
        $this->fiscalYear = $fiscalYear;
    }

    /**
     * @return string
     */
    public function getPais()
    {
        return $this->pais;
    }

    /**
     * @return string
     */
    public function getCif()
    {
        return $this->cif;
    }

    /**
     * @return string
     */
    public function getCp()
    {
        return $this->cp;
    }

    /**
     * @return string
     */
    public function getDireccion()
    {
        return $this->direccion;
    }

    /**
     * @return string
     */
    public function getPoblacion()
    {
        return $this->poblacion;
    }

    /**
     * @return string
     */
    public function getProvincia()
    {
        return $this->provincia;
    }

    /**
     * @return string
     */
    public function getRazonSocial()
    {
        return $this->razonSocial;
    }

    /**
     * @param string $pais
     */
    public function setPais(string $pais)
    {
        $this->pais = $pais;
    }

    /**
     * @param string $cif
     */
    public function setCif(string $cif)
    {
        $this->cif = $cif;
    }

    /**
     * @param string $cp
     */
    public function setCp(string $cp)
    {
        $this->cp = $cp;
    }

    /**
     * @param string $direccion
     */
    public function setDireccion(string $direccion)
    {
        $this->direccion = $direccion;
    }

    /**
     * @param string $poblacion
     */
    public function setPoblacion(string $poblacion)
    {
        $this->poblacion = $poblacion;
    }

    /**
     * @param string $provincia
     */
    public function setProvincia(string $provincia)
    {
        $this->provincia = $provincia;
    }

    /**
     * @param string $razonSocial
     */
    public function setRazonSocial(string $razonSocial)
    {
        $this->razonSocial = $razonSocial;
    }

    /**
     * @param Factura $factura
     */
    public function setFactura(Factura $factura)
    {
        $this->factura = $factura;
    }

    /**
     * @return Factura
     */
    public function getFactura()
    {
        return $this->factura;
    }


}