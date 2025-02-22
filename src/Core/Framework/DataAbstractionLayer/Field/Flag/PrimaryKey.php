<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * @package core
 */
class PrimaryKey extends Flag
{
    public function parse(): \Generator
    {
        yield 'primary_key' => true;
    }
}
