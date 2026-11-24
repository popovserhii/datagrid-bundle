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

use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\Query\Expr;
use InvalidArgumentException;
use Laminas\Db\Sql\Expression;
use Popov\DatagridBundle\Type\JsonCollection;
use RuntimeException;
use ZfcDatagrid\Column\Type;

class CollectionColumn extends ObjectColumn
{
    const COLUMNS_PLACEHOLDER = ':columnConcated:';

    protected $sqlTemplate = '';

    /** Use a plural field name for a collection in the JSON response. */
    public function setUniqueId($id): self
    {
        [$entityName, $fieldName] = array_pad(explode('_', rtrim($id, '_'), 2), 2, '');
        if ($fieldName === '') {
            return parent::setUniqueId($id);
        }

        static $inflector;

        $inflector ??= InflectorFactory::create()->build();
        parent::setUniqueId($entityName . '_' . $inflector->pluralize($fieldName));

        return $this;
    }

    public function getType(): Type\AbstractType
    {
        if (null === $this->type) {
            $this->type = new JsonCollection();
        }

        return $this->type;
    }

    /** Aggregate the object and apply the caller's query template in SQL. */
    protected function getSelectExpression(): array
    {
        [$object, $type] = parent::getSelectExpression();
        $collection = ["CONCAT('[', GROUP_CONCAT(DISTINCT ", ...$object, "), ']')"];
        if (!$this->sqlTemplate) {
            return [$collection, $type];
        }

        if ($type === Expr\Select::class) {
            throw new InvalidArgumentException('A collection SQL template cannot contain Doctrine expressions.');
        }

        return [$this->getSqlWrapped($collection), Expression::class];
    }

    public function setSqlTemplate($sqlTemplate)
    {
        $this->sqlTemplate = $sqlTemplate;

        return $this;
    }

    /** Keep the nested SQL expression intact when it is used in a subquery. */
    private function getSqlWrapped(array $collection): array
    {
        $template = str_replace(':columnConcatenated:', self::COLUMNS_PLACEHOLDER, $this->sqlTemplate);
        $sections = explode(self::COLUMNS_PLACEHOLDER, $template);
        if (count($sections) === 1) {
            throw new RuntimeException('The nested SQL template must contain the placeholder ' . self::COLUMNS_PLACEHOLDER);
        }

        $parts = [array_shift($sections)];
        foreach ($sections as $section) {
            array_push($parts, ...$collection);
            $parts[] = $section;
        }

        return $parts;
    }
}
