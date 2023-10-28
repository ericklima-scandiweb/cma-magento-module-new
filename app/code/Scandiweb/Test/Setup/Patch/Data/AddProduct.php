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
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area as MagentoArea;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddProduct implements DataPatchInterface
{
    private const CATEGORY_ID_MEN_TOPS_JACKETS = 14;

    protected array $sourceItems = [];

    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductRepositoryInterface $productRepository,
        private ProductFactory $productFactory,
        private CategoryCollectionFactory $categoryCollectionFactory,
        private CategoryLinkManagementInterface $categoryLinkManagement,
        private CategoryProductLinkInterfaceFactory $categoryProductLinkFactory,
        private TransactionFactory $transactionFactory,
        private AttributeSetRepositoryInterface $attributeSetRepository,
        private SearchCriteriaBuilder $searchCriteriaBuilder,
        private StoreManagerInterface $storeManager,
        private SourceItemsSaveInterface $sourceItemsSaveInterface,
        private SourceItemInterfaceFactory $sourceItemFactory,
        private EavSetup $eavSetup,
        private AppState $appState
    ) {
        $this->appState->setAreaCode(MagentoArea::AREA_ADMINHTML);
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $this->addProduct(
            self::CATEGORY_ID_MEN_TOPS_JACKETS,
            [
                'sku' => 'ERICK-099',
                'name' => 'Erick\'s awesome jacket - 2000\'s style',
                'price' => 107.99
            ]
        );

        $this->moduleDataSetup->endSetup();
    }

    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        return [];
    }

    private function addProduct(int|array $category, array $productData)
    {
        $category = \is_array($category)
            ? (fn(int ...$categoriesIds) => $categoriesIds)(...$category)
            : [$category];

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

        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(10);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItemsSaveInterface->execute([$sourceItem]);

        $this->categoryLinkManagement->assignProductToCategories($product->getSku(), $category);
    }
}
