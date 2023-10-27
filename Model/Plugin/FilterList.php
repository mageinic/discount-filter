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

namespace MageINIC\DiscountFilter\Model\Plugin;

use MageINIC\DiscountFilter\Model\Config;
use MageINIC\DiscountFilter\Model\Layer\Filter\Discount;
use Magento\Catalog\Model\Layer;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Layer\FilterList as CoreFilterList;

/**
 * DiscountFilter filter list plugin class
 */
class FilterList
{
    public const STATE_FILTER = 'layer_state';
    /**
     * @var array
     */
    protected array $customFilter = [];
    /**
     * @var array|string[]
     */
    protected array $filterTypes = [self::STATE_FILTER => Discount::class];
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Config $config
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Config                 $config
    ) {
        $this->objectManager = $objectManager;
        $this->config = $config;
    }

    /**
     * Add Discount Filter
     *
     * @param CoreFilterList $subject
     * @param \Closure $proceed
     * @param Layer $layer
     * @return $this|array
     */
    public function aroundGetFilters(
        CoreFilterList $subject,
        \Closure       $proceed,
        Layer          $layer
    ) {
        $filter = $proceed($layer);
        if (!$this->customFilter) {
            if ($this->config->isModuleEnable()) {
                $this->customFilter[] = $this->objectManager->create(
                    $this->filterTypes[self::STATE_FILTER],
                    ['data' => ['position' => 4], 'layer' => $layer]
                );
            }
        }
        if (count($this->customFilter)) {
            $filter = array_merge($filter, $this->customFilter);
        }
        return $filter;
    }
}
