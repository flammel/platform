<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @package content
 */
abstract class AbstractCategoryUrlGenerator
{
    abstract public function getDecorated(): AbstractCategoryUrlGenerator;

    abstract public function generate(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string;
}
