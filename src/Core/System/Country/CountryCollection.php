<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package core
 * @extends EntityCollection<CountryEntity>
 */
class CountryCollection extends EntityCollection
{
    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();

        foreach ($this->getIterator() as $country) {
            if ($country->getStates()) {
                $country->getStates()->sortByPositionAndName();
            }
        }
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryEntity $a, CountryEntity $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getTranslation('name') !== $b->getTranslation('name')) {
                return strnatcasecmp($a->getTranslation('name'), $b->getTranslation('name'));
            }

            return 0;
        });
    }

    public function getApiAlias(): string
    {
        return 'country_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryEntity::class;
    }
}
