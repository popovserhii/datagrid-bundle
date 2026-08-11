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

class JsonableArray extends AbstractType
{
    /**
     * @return string
     */
    public function getTypeName(): string
    {
        return 'array';
    }

    /**
     * Convert a value into an array.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function getUserValue($value)
    {
        if (is_string($value)) {
            if ('' == $value) {
                $value = [];
            } else {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // JSON isn't valid — return as is
                    return $value;
                }
                // JSON is valid
                return $this->getUserValue($decoded);
            }
        }

        if (is_array($value)) {
            foreach ($value as $key => $sub) {
                $value[$key] = $this->getUserValue($sub);
            }
        }

        return $value;
    }
}
