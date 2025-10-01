<?php

declare(strict_types=1);

namespace ODMBundle\Document\Generator;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Id\IdGenerator;
use Ramsey\Uuid\Uuid;

class RamseyUuidGenerator implements IdGenerator
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws \Exception
     */
    public function generate(DocumentManager $dm, object $document): string
    {
        return Uuid::uuid4()->toString();
    }
}
