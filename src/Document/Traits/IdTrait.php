<?php

declare(strict_types=1);

namespace ODMBundle\Document\Traits;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\ODM\MongoDB\Types\Type;
use ODMBundle\Document\Generator\RamseyUuidGenerator;

trait IdTrait
{
    #[MongoDB\Id(
        type: Type::STRING,
        options: ['class' => RamseyUuidGenerator::class],
        strategy: 'CUSTOM',
    )]
    protected $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }
}
