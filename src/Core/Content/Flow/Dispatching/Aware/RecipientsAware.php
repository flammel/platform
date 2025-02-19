<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
interface RecipientsAware extends FlowEventAware
{
    public const RECIPIENTS = 'recipients';

    /**
     * @return array<string, mixed>
     */
    public function getRecipients(): array;
}
