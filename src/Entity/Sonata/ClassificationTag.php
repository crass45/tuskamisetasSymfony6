<?php
// src/Entity/SonataClassificationTag.php

namespace App\Entity\Sonata;


use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sonata\ClassificationBundle\Entity\BaseTag;

#[ORM\Entity]
#[ORM\Table(name: 'classification__tag')]
class ClassificationTag extends BaseTag
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}