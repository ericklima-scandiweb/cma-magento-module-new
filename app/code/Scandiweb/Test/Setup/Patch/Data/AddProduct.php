<?php
declare(strict_types=1);
/**
 * @category Scandiweb
 * @package Scandiweb\Test
 * @author Erick Lima <erick.lima@scandiweb.com>
 * @copyright Copyright (c) 2023 Scandiweb, Ltd (http://scandiweb.com)
 */

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area as MagentoArea;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddProduct implements DataPatchInterface
{
    private const CATEGORY_ID_MEN_TOPS_JACKETS = 14;

    /** @var SourceItemInterface[] */
    protected array $sourceItems = [];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactory $productFactory
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param CategoryProductLinkInterfaceFactory $categoryProductLinkFactory
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param EavSetup $eavSetup
     * @param AppState $appState
     */
    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductRepositoryInterface $productRepository,
        private ProductFactory $productFactory,
        private CategoryLinkManagementInterface $categoryLinkManagement,
        private CategoryProductLinkInterfaceFactory $categoryProductLinkFactory,
        private AttributeSetRepositoryInterface $attributeSetRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private StoreManagerInterface $storeManager,
        private SourceItemsSaveInterface $sourceItemsSaveInterface,
        private SourceItemInterfaceFactory $sourceItemFactory,
        private EavSetup $eavSetup,
        private AppState $appState
    ) {
    }

    public function apply(): void
    {
        $this->appState->emulateAreaCode(
            MagentoArea::AREA_ADMINHTML,
            \Closure::fromCallable([$this, 'addProduct']),
            [
                self::CATEGORY_ID_MEN_TOPS_JACKETS,
                [
                    'sku' => 'ERICK-105',
                    'name' => 'Erick\'s awesome jacket - 2010\'s style',
                    'price' => 109.99
                ]
            ]
        );
    }

    /**
     * @inheritDoc
     * 
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     * 
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Adds a products to the catalog
     *
     * @param int|int[] $category
     * @param array<string, mixed> $productData
     */
    protected function addProduct(int|array $category, array $productData): void
    {
        /**
         * Uses variadic argument type-hinting language feature to type-hint array elements types.
         * A TypeError will be thrown if one of the elements of the array is not an int.
         * This technique makes the param $category to behave like int|int[]
         * */
        $category = \is_array($category)
            ? (fn(int ...$categoriesIds) => $categoriesIds)(...$category)
            : [$category];

        /** @var \Magento\Catalog\Model\Product */
        $product = $this->productFactory->create();

        if ($product->getIdBySku($productData['sku'])) {
            return;
        }

        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');

        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1])
            ->setWebsiteIds($websiteIDs)
            ->setAttributeSetId($attributeSetId);

        foreach ($productData as $k => $v) {
            $product->setData($k, $v);
        }

        $product = $this->productRepository->save($product);

        /** @var SourceItemInterface */
        $sourceItem = $this->sourceItemFactory->create();

        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(10);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItemsSaveInterface->execute([$sourceItem]);

        $this->categoryLinkManagement->assignProductToCategories($product->getSku(), $category);
    }
}
