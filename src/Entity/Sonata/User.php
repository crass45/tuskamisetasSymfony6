<?php
// src/Entity/Sonata/User.php

namespace App\Entity\Sonata;

use App\Entity\Contacto; // <-- Se añade el use
use App\Entity\Group;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sonata\UserBundle\Entity\BaseUser3 as BaseUser;

// Usamos un alias claro para evitar colisión

#[ORM\Entity]
#[ORM\Table(name: 'user__user')]
class User extends BaseUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

        /**
         * @var Collection<int, Group>
         */
    #[ORM\ManyToMany(targetEntity: Group::class)] // <-- Apunta a nuestro nuevo Group
    #[ORM\JoinTable(name: 'user__user_group')]
    protected Collection $groups;


    public function __construct()
    {
//        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // --- RELACIÓN AÑADIDA ---
    // Esta es la relación inversa a la que ya existe en la entidad Contacto.
    // Es necesaria para que el formulario pueda construir la relación.
    #[ORM\OneToOne(mappedBy: 'usuario', targetEntity: Contacto::class, cascade: ['persist', 'remove'])]
    private ?Contacto $contacto = null;

    public function getContacto(): ?Contacto
    {
        return $this->contacto;
    }

    public function setContacto(?Contacto $contacto): self
    {
        $this->contacto = $contacto;
        // Sincronizamos el lado propietario de la relación
        if ($contacto && $contacto->getUsuario() !== $this) {
            $contacto->setUsuario($this);
        }
        return $this;
    }

    /**
     * @return Collection
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }
}