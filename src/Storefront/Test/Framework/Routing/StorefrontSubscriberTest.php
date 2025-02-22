<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class StorefrontSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testItAddsShopIdParam(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss');

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayHasKey('appShopId', $event->getParameters());
    }

    public function testExpiredToken(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $token = Uuid::randomHex();

        $id = Uuid::randomHex();

        $browser = $this->createCustomSalesChannelBrowser(['id' => $id]);

        $session = $this->getSession();

        $session->start();

        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        $this->getContainer()->get(SalesChannelContextPersister::class)
            ->save($token, [SalesChannelContextService::CURRENCY_ID => Defaults::CURRENCY], $id);

        $this->getContainer()->get(Connection::class)
            ->executeStatement('UPDATE sales_channel_api_context SET updated_at = :expire', ['expire' => '2000-01-01']);

        $browser->request('GET', '/');

        $response = $browser->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent() ?: '');
        static::assertTrue($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotEquals($token, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testItDoesNotAddShopIdParamWhenAppIsInactive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss', false);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayNotHasKey('swagShopId', $event->getParameters());
        static::assertArrayNotHasKey('appShopId', $event->getParameters());
    }

    public function testItDoesNotAddShopIdParamWhenAppUrlChanged(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayNotHasKey('swagShopId', $event->getParameters());
        static::assertArrayNotHasKey('appShopId', $event->getParameters());
    }

    public function testItDoesAddIconPackConfig(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme');

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'SwagTheme');

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            $request,
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
        static::assertEquals([
            'custom-icons' => [
                'path' => 'app/storefront/src/assets/icon-pack/custom-icons',
                'namespace' => 'SwagTheme',
            ],
        ], $event->getParameters()['themeIconConfig']);
    }

    public function testSubscribedEvents(): void
    {
        $featureAll = $_SERVER['FEATURE_ALL'] ?? null;

        if (isset($featureAll)) {
            unset($_SERVER['FEATURE_ALL']);
        }

        $defaultVar = $_SERVER['v6_5_0_0'] ?? null;

        if (Feature::isActive('v6.5.0.0')) {
            static::assertCount(2, (array) StorefrontSubscriber::getSubscribedEvents()[KernelEvents::EXCEPTION]);
        } else {
            static::assertCount(3, (array) StorefrontSubscriber::getSubscribedEvents()[KernelEvents::EXCEPTION]);
        }

        if ($defaultVar !== null) {
            $_SERVER['V6_5_0_0'] = $defaultVar;
        } else {
            unset($_SERVER['V6_5_0_0']);
        }

        if (isset($featureAll)) {
            $_SERVER['FEATURE_ALL'] = $featureAll;
        }
    }
}
