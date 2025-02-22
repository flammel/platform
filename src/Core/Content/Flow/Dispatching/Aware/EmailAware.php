<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
interface EmailAware extends FlowEventAware
{
    public const EMAIL = 'email';

    public function getEmail(): string;
}
