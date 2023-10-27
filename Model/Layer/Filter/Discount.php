<?php
/**
 * MageINIC
 * Copyright (C) 2023 MageINIC <support@mageinic.com>
 *
 * NOTICE OF LICENSE
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see https://opensource.org/licenses/gpl-3.0.html.
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category MageINIC
 * @package MageINIC_DiscountFilter
 * @copyright Copyright (c) 2023 MageINIC (https://www.mageinic.com/)
 * @license https://opensource.org/licenses/gpl-3.0.html GNU General Public License,version 3 (GPL-3.0)
 * @author MageINIC <support@mageinic.com>
 */

namespace MageINIC\DiscountFilter\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\DataProvider\Price;
use Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder;
use Magento\Catalog\Model\Layer\Filter\ItemFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

class Discount extends AbstractFilter
{
    /**
     * @var Price
     */
    private Price $dataProvider;

    /**
     * @param ItemFactory $filterItemFactory
     * @param StoreManagerInterface $storeManager
     * @param Layer $layer
     * @param DataBuilder $itemDataBuilder
     * @param PriceFactory $dataProviderFactory
     * @param array $data
     * @throws LocalizedException
     */
    public function __construct(
        ItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        DataBuilder $itemDataBuilder,
        PriceFactory $dataProviderFactory,
        array $data = []
    ) {
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
        $this->_requestVar   = 'state';
        $this->dataProvider = $dataProviderFactory->create(['layer' => $this->getLayer()]);
    }

    /**
     * Apply filter to collection
     *
     * @param RequestInterface $request
     * @return $this|Discount
     */
    public function apply(RequestInterface $request)
    {
        $filter = $request->getParam($this->getRequestVar());
        if (!$filter || is_array($filter)) {
            return $this;
        }
        $filter = explode('-', $filter);
        list($from, $to) = $filter;
        $todayDate = date('m/d/y');
        $currentCategory = $this->getLayer()->getCurrentCategory();
        $collection = $currentCategory->getProductCollection();
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('special_price', ['notnull' => true]);
        $collection->addAttributeToFilter('special_from_date', ['or'=> [
            0 => ['date' => true, 'to' => $todayDate],
            1 => ['is' => new \Zend_Db_Expr('null')]]
        ], 'left')
            ->addAttributeToFilter(
                'special_to_date',
                ['or'=> [
                    0 => ['date' => true, 'from' => $todayDate],
                    1 => ['is' => new \Zend_Db_Expr('null')]
                ]
                ],
                'left'
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'special_from_date', 'is'=>new \Zend_Db_Expr('not null')],
                    ['attribute' => 'special_to_date', 'is'=>new \Zend_Db_Expr('not null')]
                ]
            );
        foreach ($collection as $product) {
            $price = $product->getPrice();
            if ($product->getTypeId() == "bundle") {
                $price  = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue() ;
            }
            $specialPrice = $product->getSpecialPrice();
            $specialFromDate = $product->getSpecialFromDate();
            if ($price > 0 && isset($specialFromDate)) {
                if (time() >= strtotime($specialFromDate)) {
                    $dis = ($price - $specialPrice) * 100 / $price;
                    if ($dis >= $from) {
                        $entity_id[] = $product->getId();
                    }
                }
            }
        }
        $filter = $request->getParam($this->getRequestVar());
        $this->getLayer()
            ->getProductCollection()
            ->addAttributeToFilter('entity_id', ['in' => ($entity_id)]);
        $this->getLayer()->getState()->addFilter($this->_createItem($this->getOptionText($filter), $filter));
        $this->setItems([]);
        return $this;
    }

    /**
     * Get filter name
     *
     * @return Phrase|string
     */
    public function getName(): Phrase|string
    {
        return  __('Discount Percentage');
    }

    /**
     * Get option text from frontend model by option id
     *
     * @param int $discountRange
     * @return Phrase
     */
    protected function getOptionText($discountRange)
    {
        if ($discountRange == 1) {
            return __('%1', $discountRange);
        }

        return __('%1', $discountRange);
    }

    /**
     * Get filter value for reset current filter state
     *
     * @return null|string
     */
    public function getResetValue(): ?string
    {
        return $this->dataProvider->getResetValue();
    }

    /**
     * Get data array for building category filter items
     *
     * @return array
     */
    protected function _getItemsData(): array
    {
        $facets = $this->getDiscountRange();
        if (count($facets) > 1) {
            foreach ($facets as $key => $label) {
                $filter = explode('-', $key);
                list($from, $to) = $filter;
                $currentCategory = $this->getLayer()->getCurrentCategory();
                $collection = $currentCategory->getProductCollection();
                $todayDate = date('m/d/y');
                $collection->addAttributeToSelect('*');
                $collection->addAttributeToFilter('special_price', ['notnull' => true]);
                $collection->addAttributeToFilter('special_from_date', ['or'=> [
                    0 => ['date' => true, 'to' => $todayDate],
                    1 => ['is' => new \Zend_Db_Expr('null')]]
                ], 'left')
                    ->addAttributeToFilter('special_to_date', ['or'=> [
                        0 => ['date' => true, 'from' => $todayDate],
                        1 => ['is' => new \Zend_Db_Expr('null')]]
                    ], 'left')
                    ->addAttributeToFilter(
                        [
                            ['attribute' => 'special_from_date', 'is'=>new \Zend_Db_Expr('not null')],
                            ['attribute' => 'special_to_date', 'is'=>new \Zend_Db_Expr('not null')]
                        ]
                    );
                $count1 = 0;
                foreach ($collection as $product) {
                    $price = $product->getPrice();
                    if ($product->getTypeId() == "bundle") {
                        $price  = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue() ;
                    }
                    $specialPrice = $product->getSpecialPrice();
                    $specialFromDate = $product->getSpecialFromDate();
                    if ($price > 0 && isset($specialFromDate)) {

                        if (time() >= strtotime($specialFromDate)) {
                            $dis = ($price - $specialPrice) * 100 / $price;
                            if ($dis >= $from) {
                                $count1++;
                                [$product];
                            }
                        }
                    }
                }
                if ($count1 > 0) {
                    $this->itemDataBuilder->addItemData(
                        $label,
                        $key,
                        $count1
                    );
                }
            }
        }
        return $this->itemDataBuilder->build();
    }

    /**
     * Get Discount Range
     *
     * @return array
     */
    private function getDiscountRange(): array
    {
        $resultArray = [];
        for ($i = 1; $i <= 100; $i += 20) {
            $start = $i;
            $end = min($i + 19, 100);
            $label = "$start-$end";
            $resultArray[$label] =
                '<div class="discount-filter-class" style="display: inline-block;margin-top: -5px">
                    <span>' . __('' . ($start + 0) . '% to ' . ($end + 0) . '%') . '</span>
                 </div>';
        }
        return $resultArray;
    }
}
