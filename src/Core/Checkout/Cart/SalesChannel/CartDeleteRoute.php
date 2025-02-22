<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CartDeleteRoute extends AbstractCartDeleteRoute
{
    private AbstractCartPersister $cartPersister;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(AbstractCartPersister $cartPersister, EventDispatcherInterface $eventDispatcher)
    {
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractCartDeleteRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @Route("/store-api/checkout/cart", name="store-api.checkout.cart.delete", methods={"DELETE"})
     */
    public function delete(SalesChannelContext $context): NoContentResponse
    {
        $this->cartPersister->delete($context->getToken(), $context);

        $cartDeleteEvent = new CartDeletedEvent($context);
        $this->eventDispatcher->dispatch($cartDeleteEvent);

        return new NoContentResponse();
    }
}
