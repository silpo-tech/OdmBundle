<?php

declare(strict_types=1);

namespace ODMBundle\Repository\Traits;

use MongoDB\Collection;

trait MongoDBCollectionTrait
{
    /**
     * @param string|null $collection MongoDB collection name (`show collections`)
     * @param string|null $db         MongoDB database name (`show databases`)
     */
    public function getMongoDBCollection(?string $collection = null, ?string $db = null): Collection
    {
        return $this->getDocumentManager()->getClient()->selectCollection(
            $db ?? $this->getDocumentManager()->getConfiguration()->getDefaultDB(),
            $collection ?? $this->getClassMetadata()->getCollection(),
        );
    }
}
