<?php

namespace Popov\DatagridBundle\Tests\Unit\DataSource;

use PHPUnit\Framework\TestCase;
use Popov\DatagridBundle\DataSource\Remote;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use UnexpectedValueException;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\Filter;
use ZfcDatagrid\FilterGroup;

class RemotePaginatorTest extends TestCase
{
    public function testBatchMakesOneHttpRequestWithPreparedCursorAndSorting(): void
    {
        $requests = [];
        $source = $this->source(function ($method, $url, $options) use (&$requests) {
            $requests[] = $url;
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('2', $query['itemsPerPage']);
            self::assertSame('project.id', $query['sortBy']);
            self::assertSame('asc', $query['sortOrder']);
            $filters = json_decode(substr($options['normalized_headers']['filters'][0], strlen('filters: ')), true);
            self::assertSame([
                'groupOp' => 'AND',
                'rules' => [['field' => 'project.id', 'op' => 'gt', 'data' => '3']],
            ], $filters);

            return new MockResponse(json_encode(['rows' => [['id' => 5], ['id' => 8]], 'records' => 2]));
        });
        $column = $source->getColumns()[0];
        $column->setFilterDefaultOperation(Filter::GREATER);
        $filters = new FilterGroup();
        $filters->addFilter((new Filter())->setFromColumn($column, '3'));
        $source->setFilterGroup($filters);
        $source->addSortCondition($column, 'ASC');
        $source->execute();

        self::assertSame([['project_id' => 5], ['project_id' => 8]], $source->getPaginatorAdapter()->getItems(0, 2));
        self::assertCount(1, $requests);
    }

    /** @dataProvider terminalBatches */
    public function testShortOrEmptyBatchMakesOnlyOneRequest(array $rows): void
    {
        $calls = 0;
        $source = $this->source(function () use (&$calls, $rows) {
            ++$calls;

            return new MockResponse(json_encode(['rows' => $rows, 'records' => count($rows)]));
        });
        $source->execute();

        self::assertCount(count($rows), $source->getPaginatorAdapter()->getItems(0, 2));
        self::assertSame(1, $calls);
    }

    public function terminalBatches(): array
    {
        return [[[['id' => 13]]], [[]]];
    }

    public function testUnalignedWindowSupportsReturnedRowCountsWithoutProbe(): void
    {
        $calls = [];
        $source = $this->source(function ($method, $url) use (&$calls) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $calls[] = [(int) $query['page'], (int) $query['itemsPerPage']];
            $rows = 1 === (int) $query['page'] ? [['id' => 1], ['id' => 2]] : [['id' => 3]];

            return new MockResponse(json_encode(['rows' => $rows, 'records' => count($rows), 'page' => (int) $query['page']]));
        });
        $source->execute();

        self::assertSame([['project_id' => 2], ['project_id' => 3]], $source->getPaginatorAdapter()->getItems(1, 2));
        self::assertSame([[1, 2], [2, 2]], $calls);
    }

    public function testExactTotalAndUnalignedWindowRemainSupported(): void
    {
        $source = $this->source(function ($method, $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $rows = array_slice([['id' => 1], ['id' => 2], ['id' => 3]], ((int) $query['page'] - 1) * 2, 2);

            return new MockResponse(json_encode(['rows' => $rows, 'records' => 3, 'page' => (int) $query['page']]));
        });
        $source->execute();

        self::assertSame([['project_id' => 2], ['project_id' => 3]], $source->getPaginatorAdapter()->getItems(1, 2));
    }

    public function testUnpaginatedRemoteResponseIsSlicedLocally(): void
    {
        $calls = 0;
        $source = $this->source(function () use (&$calls) {
            ++$calls;

            return new MockResponse(json_encode(['rows' => [['id' => 1], ['id' => 2], ['id' => 3]], 'records' => 3]));
        });
        $source->execute();
        $adapter = $source->getPaginatorAdapter();

        self::assertSame([['project_id' => 1], ['project_id' => 2]], $adapter->getItems(0, 2));
        self::assertSame([['project_id' => 2], ['project_id' => 3]], $adapter->getItems(1, 2));
        self::assertSame(1, $calls);
    }

    public function testUnboundedReadPreservesDirectRemoteRequest(): void
    {
        $calls = 0;
        $source = $this->source(function ($method, $url) use (&$calls) {
            ++$calls;
            self::assertFalse(strpos($url, 'itemsPerPage='));

            return new MockResponse(json_encode(['rows' => [['id' => 1], ['id' => 2]], 'records' => 2]));
        });
        $source->execute();

        self::assertSame([['project_id' => 1], ['project_id' => 2]], $source->getPaginatorAdapter()->getItems(0, -1));
        self::assertSame(1, $calls);
    }

    public function testIncorrectPagePropagatesAsAnError(): void
    {
        $source = $this->source(function () {
            return new MockResponse(json_encode(['rows' => [['id' => 1]], 'records' => 1, 'page' => 2]));
        });
        $source->execute();
        $this->expectException(UnexpectedValueException::class);

        $source->getPaginatorAdapter()->getItems(0, 2);
    }

    private function source(callable $callback): Remote
    {
        $source = new Remote(new MockHttpClient($callback), 'https://remote.test', 'project');
        $source->setColumns([new Select('id', 'project')]);
        $source->setFilterGroup(new FilterGroup());

        return $source;
    }
}
