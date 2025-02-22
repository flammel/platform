<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package checkout
 *
 * @extends Collection<PriceDefinitionInterface>
 */
class PriceDefinitionCollection extends Collection
{
    public function get($key): ?PriceDefinitionInterface
    {
        $key = (int) $key;

        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_definition_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return PriceDefinitionInterface::class;
    }
}
