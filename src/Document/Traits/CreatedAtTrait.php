<?php

declare(strict_types=1);

namespace ODMBundle\Document\Traits;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\ODM\MongoDB\Types\Type;

trait CreatedAtTrait
{
    #[MongoDB\Field(type: Type::DATE)]
    protected $createdAt;

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    #[MongoDB\PrePersist]
    public function prePersistCreatedAt(): void
    {
        if (null === $this->getCreatedAt()) {
            $this->setCreatedAt(new \DateTime());
        }
    }
}
