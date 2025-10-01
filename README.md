# ODM Bundle #

[![CI](https://github.com/silpo-tech/OdmBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/silpo-tech/OdmBundle/actions)
[![codecov](https://codecov.io/gh/silpo-tech/OdmBundle/graph/badge.svg)](https://codecov.io/gh/silpo-tech/OdmBundle)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## About ##

The ODM Bundle contains common used ODM classes (e.g. Validators)

## Installation ##

Require the bundle and its dependencies with composer:

```bash
$ composer require silpo-tech/odm-bundle
```

Register the bundle:

```php
// project/config/bundles.php

return [
    ODMBundle\ODMBundle::class => ['all' => true],
];
```


## Examples ##

### FilterValueResolver ###

Add converter annotation to argument for your dto
```php
     public function __invoke(#[OdmFilterMapper] ExternalCategoryFilterDto $dto)
```

```php
class ExternalCategoryFilterDto
{
    public $externalId;
    public $title;
    public $mappings;

    /**
     * @Assert\Type("array")
     * @Assert\Choice(multiple=true, choices={"title", "-title", "createdAt", "-createdAt"})
     *
     * @var array
     */
    public $sort = ['title'];
}
```

### AbstractBuilderAwareFilter ###
Extend your filter class with AbstractBuilderAwareFilter and implement filtration methods.
Sorting implementation is defined in AbstractBuilderAwareFilter and will be called if $dto->sort is not empty
```php
class ExternalCategoryFilter extends AbstractBuilderAwareFilter
{
    protected function aggregationTitle(MatchStage $match, $value): void
    {
        $match->field('title')->equals(new Regex(sprintf('^.*%s.*$', $value), 'i'));
    }

    protected function aggregationExternalId(MatchStage $match, string $value): void
    {
        $match->field('externalId')->equals($value);
    }
    
    protected function aggregationCreatedAt(MatchStage $match, array $value): void
    {
        $this->aggregationFilterDate($match, $value, 'createdAt', true);
    }

    protected function aggregationMappings(MatchStage $match, bool $value): void
    {
        $field = $match->lookup('mappings')->match()->field('mappings');
        $value ? $field->not($match->expr()->size(0)) : $field->size(0);
    }
    
    protected function queryTitle(QueryBuilder $qb, $value): void
    {
        $qb->field('title')->equals(new Regex(sprintf('^.*%s.*$', $value), 'i'));
    }

    protected function queryExternalId(QueryBuilder $qb, string $value): void
    {
        $qb->field('externalId')->equals($value);
    }
    
    protected function queryCreatedAt(QueryBuilder $qb, array $value): void
    {
        $this->queryFilterDate($qb, $value, 'createdAt', true);
    }
}
```

Call filtration from repository
```php
        //$qb = $this->createQueryBuilder();
        $qb = $this->createAggregationBuilder()->hydrate(ExternalCategory::class);

        (new ExternalCategoryFilter())->filter($qb, $filterDto);
```

### Paginator ###
```php
        //$qb = $this->createQueryBuilder();
        $qb = $this->createAggregationBuilder()->hydrate(ExternalCategory::class);

        return (new BuilderAwarePaginator())->paginate($qb, $paginationDto);
```

```php
    public function listAction(OffsetPaginator $paginationDto): Response
    {
        $result = $this->repository->paginate($paginationDto);

        return $this->createPaginatedCollectionResponse(
            $result->total,
            $this->mapper->convertCollection($result->items, ResponseExternalCategoryDto::class),
            $paginationDto
        );
    }
```

### Validator ###
```php
/**
 * @Assert\GroupSequence({"ExternalCategoryMappingDto", "ODM"})
 * @OdmExists(
 *     documentClass="App\Document\ExternalCategory",
 *     fields={"externalCategoryId":"_id"},
 *     errorPath="externalCategory"
 * )
 * @OdmNotExists(
 *     documentClass="App\Document\ExternalCategoryMapping",
 *     fields={
 *      "categoryId":"categoryId",
 *      "externalCategoryId":"externalCategory",
 *      "externalBrandId":"externalBrand"
 *     },
 *     ignoreNull=false,
 *     errorPath="externalCategoryMapping"
 * )
 *
 */
class ExternalCategoryMappingDto {
    /**
     * @ValidDateRange(format="Y-m-d", allowEqual=true),
     *
     * @var array with keys ['from', 'to']
     */
    public $createdAt;
}
```
