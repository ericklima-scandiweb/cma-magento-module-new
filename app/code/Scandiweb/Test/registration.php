<?php
/**
 * @category Scandiweb
 * @package Scandiweb\Test
 * @author Erick Lima <erick.lima@scandiweb.com>
 * @copyright Copyright (c) 2023 Scandiweb, Ltd (http://scandiweb.com)
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Scandiweb_Test',
    __DIR__
);
