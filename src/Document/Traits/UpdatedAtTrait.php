<?php

declare(strict_types=1);

namespace ODMBundle\Document\Traits;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\ODM\MongoDB\Types\Type;

trait UpdatedAtTrait
{
    #[MongoDB\Field(type: Type::DATE)]
    protected $updatedAt;

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    #[MongoDB\PrePersist]
    public function prePersistUpdateAt(): void
    {
        if (null === $this->getUpdatedAt()) {
            $this->setUpdatedAt(new \DateTime());
        }
    }

    #[MongoDB\PreUpdate]
    public function preUpdateUpdateAt(): void
    {
        $this->setUpdatedAt(new \DateTime());
    }
}
