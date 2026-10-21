<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2021 Mediapark
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Datagrid
 * @author Serhii Popov <serhii.popov@mediapark.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Type;

use ZfcDatagrid\Column\Type\AbstractType;

class JsonObject extends JsonableArray
{
    /**
     * @return string
     */
    public function getTypeName(): string
    {
        return 'object';
    }
}
