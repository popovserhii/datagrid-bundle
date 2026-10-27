<?php

namespace Popov\DatagridBundle\DataSource\Remote;

use InvalidArgumentException;
use Laminas\Paginator\Adapter\AdapterInterface;
use Popov\DatagridBundle\DataSource\Remote as RemoteDataSource;
use UnexpectedValueException;

class Paginator implements AdapterInterface
{
    private $dataSource;
    private $total;
    private $pages = [];
    private $allRows;

    public function __construct(RemoteDataSource $dataSource)
    {
        $this->dataSource = $dataSource;
    }

    public function count(): int
    {
        if (null === $this->total) {
            $this->fetch(1, 1);
        }

        return $this->total;
    }

    public function getItems($offset, $itemCountPerPage): array
    {
        $offset = (int) $offset;
        $limit = (int) $itemCountPerPage;
        if ($offset < 0 || $limit < -1) {
            throw new InvalidArgumentException('Offset must be nonnegative; limit must be -1 or nonnegative.');
        }
        if (0 === $limit) {
            return [];
        }
        if (-1 === $limit) {
            return array_slice($this->dataSource->getResult(), $offset);
        }
        if (null !== $this->allRows) {
            return array_slice($this->allRows, $offset, $limit);
        }

        // Native list endpoints use page/itemsPerPage. Offset need not be page-aligned.
        // Fetch the requested window directly, without a preliminary count request.
        $size = $limit;
        $page = intdiv($offset, $size) + 1;
        $skip = $offset % $size;
        $items = [];
        while (count($items) < $limit) {
            $rows = $this->fetch($page, $size);
            if (null !== $this->allRows) {
                return array_slice($this->allRows, $offset, $limit);
            }
            foreach (array_slice($rows, $skip, $limit - count($items)) as $row) {
                $items[] = $row;
            }
            if (count($rows) < $size) {
                if ($this->total > ($page - 1) * $size + count($rows)) {
                    throw new UnexpectedValueException('Remote listing ended before its reported record count.');
                }
                break;
            }
            $skip = 0;
            ++$page;
        }

        return $items;
    }

    private function fetch(int $page, int $size): array
    {
        $key = $size . ':' . $page;
        if (isset($this->pages[$key])) {
            return $this->pages[$key];
        }
        $response = $this->dataSource->request(['page' => $page, 'itemsPerPage' => $size]);
        $this->total = $response['records'];
        $rows = $response['rows'];
        if (count($rows) > $size) {
            if (count($rows) !== $this->total) {
                throw new UnexpectedValueException('Remote listing did not honor the requested page.');
            }
            // Some existing list endpoints intentionally return all rows without pagination.
            $this->allRows = $rows;
        } elseif (null !== $response['page'] && (int) $response['page'] !== $page) {
            throw new UnexpectedValueException('Remote listing returned a different page.');
        }
        $this->pages[$key] = $rows;

        return $rows;
    }
}
