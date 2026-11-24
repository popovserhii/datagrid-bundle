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

    /** Aggregate the nested object in the same query language. */
    public function getSelectPart1()
    {
        $object = parent::getSelectPart1();
        if ($object instanceof Expr\Select && $this->sqlTemplate) {
            throw new InvalidArgumentException('A collection SQL template cannot contain Doctrine expressions.');
        }

        $sql = $object instanceof Expression ? $object->getExpression() : (string) $object;
        $sql = "CONCAT('[', GROUP_CONCAT(DISTINCT " . $sql . "), ']')";
        $sql = $this->getSqlWrapped($sql);

        return $object instanceof Expr\Select ? new Expr\Select($sql) : new Expression($sql);
    }

    public function setSqlTemplate($sqlTemplate)
    {
        $this->sqlTemplate = $sqlTemplate;

        return $this;
    }

    /** Insert the aggregate into the caller's SQL subquery. */
    private function getSqlWrapped(string $sql): string
    {
        if (!$this->sqlTemplate) {
            return $sql;
        }

        $template = str_replace(':columnConcatenated:', self::COLUMNS_PLACEHOLDER, $this->sqlTemplate);
        if (!str_contains($template, self::COLUMNS_PLACEHOLDER)) {
            throw new RuntimeException('The nested SQL template must contain the placeholder ' . self::COLUMNS_PLACEHOLDER);
        }

        return str_replace(self::COLUMNS_PLACEHOLDER, $sql, $template);
    }
}
