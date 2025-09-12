<?php

namespace SS\TiendaBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * PedidoLinea
 *
 * @ORM\Table(name="pedidos_log")
 * @ORM\Entity
 */
class PedidosLog
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    /**
     * @var \Productos
     *
     * @ORM\ManyToOne(targetEntity="SS\TiendaBundle\Entity\Pedido", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_pedido", referencedColumnName="id")
     * })
     */
    private $pedido;


    /**
     * @var Estado
     *
     * @ORM\ManyToOne(targetEntity="SS\TiendaBundle\Entity\Estado", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_estado", referencedColumnName="id" , nullable= false)
     * })
     */
    private $estado;


    /**
     * @var \SS\TiendaBundle\Entity\Contacto
     *
     * @ORM\ManyToOne(targetEntity="SS\TiendaBundle\Entity\Contacto", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_usuario", referencedColumnName="id", nullable = true)
     * })
     */
    private $idUsuario;

    /**
     * @return Estado
     */
    public function getEstado()
    {
        return $this->estado;
    }

    /**
     * @return Contacto
     */
    public function getIdUsuario()
    {
        return $this->idUsuario;
    }

    /**
     * @return \Productos
     */
    public function getPedido(): \Productos
    {
        return $this->pedido;
    }

    /**
     * @param Estado $estado
     */
    public function setEstado(Estado $estado)
    {
        $this->estado = $estado;
    }

    /**
     * @param Contacto $idUsuario
     */
    public function setIdUsuario(Contacto $idUsuario)
    {
        $this->idUsuario = $idUsuario;
    }

    /**
     * @param \Productos $pedido
     */
    public function setPedido(\Productos $pedido)
    {
        $this->pedido = $pedido;
    }
}
