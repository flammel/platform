<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductCrossSellingEntity>
 *
 * @package inventory
 */
class ProductCrossSellingCollection extends EntityCollection
{
    public function getExpectedClass(): string
    {
        return ProductCrossSellingEntity::class;
    }

    public function getApiAlias(): string
    {
        return 'product_cross_selling_collection';
    }
}
