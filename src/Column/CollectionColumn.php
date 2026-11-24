<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2025 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Popov
 * @package Popov_<package>
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Column;

use RuntimeException;
use ZfcDatagrid\Column\Type;
use Popov\DatagridBundle\Type\JsonCollection;
use Doctrine\Inflector\InflectorFactory;

class CollectionColumn extends ObjectColumn
{
    const COLUMNS_PLACEHOLDER = ':columnConcatenated:';

    protected $sqlTemplate = '';

    public function setUniqueId($id): self
    {
        [$entityName, $fieldName] = $this->getUniqueParts($id);

        $fieldName = $this->pluralize($fieldName);

        parent::setUniqueId($entityName . '_' . $fieldName);

        return $this;
    }

    public function getType(): Type\AbstractType
    {
        if (null === $this->type) {
            $this->type = new JsonCollection();
        }

        return $this->type;
    }

    public function getSelectPart1()
    {
        $sql = new CompositeExpression(
            "CONCAT('[', GROUP_CONCAT(DISTINCT ?), ']')",
            [$this->buildSqlJsonExpression()]
        );

        $sql = $this->getSqlWrapped($sql);

        return $sql;
    }

    public function setSqlTemplate($sqlTemplate)
    {
        $this->sqlTemplate = $sqlTemplate;

        return $this;
    }

    protected function getSqlWrapped(CompositeExpression $sql): CompositeExpression
    {
        if (!$this->sqlTemplate) {
            // There is nothing to replace. The nested SQL is prepared on the Table level.
            return $sql;
        }

        if (strpos($this->sqlTemplate, self::COLUMNS_PLACEHOLDER) === false) {
            throw new RuntimeException('The nested SQL template must contain the placeholder ' . self::COLUMNS_PLACEHOLDER);
        }
        
        return new CompositeExpression(
            str_replace(self::COLUMNS_PLACEHOLDER, '?', $this->sqlTemplate),
            array_fill(0, substr_count($this->sqlTemplate, self::COLUMNS_PLACEHOLDER), $sql)
        );
    }

    private function pluralize(string $word): string
    {
        static $inflector;

        if (!$inflector) {
            $inflector = InflectorFactory::create()->build();
        }

        return $inflector->pluralize($word);
    }
}
