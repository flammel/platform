<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresProductParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectParamMissingException('productId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidProductParam(): void
    {
        $request = new Request([], [], ['productId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createSalesChannelContextWithNavigation();

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestProduct(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ProductPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ProductPage::class, $page);
    }

    public function testItDoesLoadACloseProductWithHideCloseEnabled(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();

        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $product = $this->getRandomProduct($context, 1, true);

        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ProductPage::class, $page);
        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDoesFailWithACloseProductWithHideCloseEnabledWhenOutOfStock(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();

        // enable hideCloseoutProductsWhenOutOfStock filter
        $this->getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutProductsWhenOutOfStock', true);

        $product = $this->getRandomProduct($context, 0, true);

        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $this->expectException(ProductNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItLoadsPageWithProductCategoryAsActiveNavigation(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $seoCategoryName = 'Fancy Category';

        $catRepository = $this->getContainer()->get('category.repository');

        $seoCategoryId = Uuid::randomHex();

        $catRepository->create([[
            'id' => $seoCategoryId,
            'name' => $seoCategoryName,
            'active' => true,
            'parentId' => $context->getSalesChannel()->getNavigationCategoryId(),
        ]], Context::createDefaultContext());

        $product = $this->getRandomProduct($context, 1, false, [
            'categories' => [
                ['id' => $seoCategoryId],
            ],
        ]);

        $this->updateProductStream($product->getId(), Uuid::randomHex());

        $request = new Request([], [], ['productId' => $product->getId()]);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertNotNull($page->getHeader());
        static::assertNotNull($page->getHeader()->getNavigation());
        static::assertNotNull($page->getHeader()->getNavigation()->getActive());
        static::assertEquals($seoCategoryName, $page->getHeader()->getNavigation()->getActive()->getName());
        static::assertEquals($seoCategoryId, $page->getHeader()->getNavigation()->getActive()->getId());
    }

    public function testItDoesLoadACmsProductDetailPage(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $cmsPageId = Uuid::randomHex();
        $productCmsPageData = [
            'cmsPage' => [
                'id' => $cmsPageId,
                'type' => 'product_detail',
                'sections' => [],
            ],
            'cover' => [
                'id' => Uuid::randomHex(),
                'position' => 0,
                'media' => [
                    'fileName' => 'cover-1',
                ],
            ],
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 0,
                    'media' => [
                        'fileName' => 'media-1',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 2,
                    'media' => [
                        'fileName' => 'media-2',
                    ],
                ],
            ],
        ];

        $product = $context->getContext()->scope(Context::SYSTEM_SCOPE, function () use ($context, $productCmsPageData): ProductEntity {
            return $this->getRandomProduct($context, 10, false, $productCmsPageData);
        });

        static::assertEquals($cmsPageId, $product->getCmsPageId());
        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(ProductPage::class, $page);
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertEquals($cmsPageId, $page->getCmsPage()->getId());

        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(ProductPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testSlotOverwrite(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $cmsPageId = Uuid::randomHex();
        $firstSlotId = Uuid::randomHex();
        $secondSlotId = Uuid::randomHex();
        $productCmsPageData = [
            'cmsPage' => [
                'id' => $cmsPageId,
                'type' => 'product_detail',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $firstSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => $secondSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'slotConfig' => [
                $firstSlotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'overwrittenByProduct',
                    ],
                ],
                $secondSlotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'overwrittenByProduct',
                    ],
                ],
            ],
        ];

        $product = $this->getRandomProduct($context, 10, false, $productCmsPageData);
        $request = new Request([], [], ['productId' => $product->getId()]);

        /** @var ProductPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(ProductPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);
        $cmsPage = $page->getCmsPage();
        $fieldConfigCollection = new FieldConfigCollection([new FieldConfig('content', 'static', 'overwrittenByProduct')]);

        static::assertNotNull($cmsPage);
        static::assertIsIterable($cmsPage->getSections());
        static::assertNotNull($cmsPage->getSections()->first());
        static::assertIsIterable($cmsPage->getSections()->first()->getBlocks());

        $blocks = $cmsPage->getSections()->first()->getBlocks();
        static::assertInstanceOf(CmsBlockCollection::class, $blocks);
        static::assertIsIterable($blocks->getSlots());
        static::assertNotNull($blocks->getSlots()->get($firstSlotId));
        static::assertNotNull($blocks->getSlots()->get($secondSlotId));

        static::assertEquals(
            $productCmsPageData['slotConfig'][$firstSlotId],
            $blocks->getSlots()->get($firstSlotId)->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $blocks->getSlots()->get($firstSlotId)->getFieldConfig()
        );

        static::assertEquals(
            $productCmsPageData['slotConfig'][$secondSlotId],
            $blocks->getSlots()->get($secondSlotId)->getConfig()
        );
    }

    /**
     * @return ProductPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(ProductPageLoader::class);
    }

    private function createReviews(ProductEntity $product, SalesChannelContext $context): void
    {
        $reviews = [];
        for ($i = 1; $i <= 5; ++$i) {
            $reviews[] = [
                'languageId' => $context->getContext()->getLanguageId(),
                'salesChannelId' => $context->getSalesChannel()->getId(),
                'productId' => $product->getId(),
                'title' => 'Test',
                'content' => 'test',
                'points' => $i,
                'status' => true,
            ];
        }

        $reviews[] = [
            'languageId' => $context->getContext()->getLanguageId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'productId' => $product->getId(),
            'title' => 'Test',
            'content' => 'test',
            'points' => 5,
            'status' => true,
        ];

        if ($context->getCustomer()) {
            $reviews[] = [
                'customerId' => $context->getCustomer()->getId(),
                'languageId' => $context->getContext()->getLanguageId(),
                'salesChannelId' => $context->getSalesChannel()->getId(),
                'productId' => $product->getId(),
                'title' => 'Customer test',
                'content' => 'Customer test',
                'points' => 4,
                'status' => false,
            ];
        }

        $this->getContainer()->get('product_review.repository')
            ->create($reviews, Context::createDefaultContext());
    }

    private function updateProductStream(string $productId, string $streamId): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE `product` SET `stream_ids` = :streamIds WHERE `id` = :id',
            [
                'streamIds' => json_encode([$streamId]),
                'id' => Uuid::fromHexToBytes($productId),
            ]
        );
    }
}
