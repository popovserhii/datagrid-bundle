<?php

namespace Popov\DatagridBundle\DataSource;

use LogicException;
use Popov\DatagridBundle\DataSource\Remote\Paginator;
use Popov\DatagridBundle\DataSource\Remote\Request;
use Popov\DatagridBundle\DataSource\Remote\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ZfcDatagrid\DataSource\AbstractDataSource;

/** Forwards the grid's final conditions and returns remote rows unchanged. */
class Remote extends AbstractDataSource
{
    private $httpClient;
    private $endpoint;
    private $resource;
    private $token;
    private $query;
    private $result;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameters)
    {
        $this->httpClient = $httpClient;
        $this->endpoint = (string) $parameters->get('elasticsearch_endpoint');
        $this->token = (string) $parameters->get('elasticsearch_token');
    }

    public function setResource(string $resource): self
    {
        $this->resource = $resource;
        $this->query = null;
        $this->result = null;
        $this->setPaginatorAdapter(null);

        return $this;
    }

    public function getData(): string
    {
        if (!$this->resource) {
            throw new LogicException('Configure the remote datagrid resource before enabling this data source.');
        }

        return $this->resource;
    }

    public function execute()
    {
        if ('' === $this->endpoint) {
            throw new LogicException('Configure the remote datagrid endpoint before enabling this data source.');
        }
        $this->query = (new Request())->encode($this);
        $this->result = null;
        $this->setPaginatorAdapter(new Paginator($this));
    }

    /**
     * Execute the prepared remote request without a Laminas paginator.
     *
     * A listing endpoint normally returns all rows when pagination parameters
     * are absent. If it applies a server-side default limit, continue through
     * its native pages until the reported record count is reached.
     */
    public function getResult(): array
    {
        if (null !== $this->result) {
            return $this->result;
        }
        $response = $this->request();
        $rows = $response['rows'];
        $total = $response['records'];
        $this->assertPage($response, 1, $total);

        if (count($rows) < $total) {
            $size = count($rows);
            if (0 === $size) {
                $size = min(500, $total);
                $response = $this->request(['page' => 1, 'itemsPerPage' => $size]);
                $rows = $response['rows'];
                $this->assertPage($response, 1, $total);
                if (!$rows) {
                    throw new LogicException('Remote listing returned no rows for a non-empty result.');
                }
                $size = count($rows);
            }

            $page = 2;
            while (count($rows) < $total) {
                $response = $this->request(['page' => $page, 'itemsPerPage' => $size]);
                $this->assertPage($response, $page, $total);
                if (!$response['rows']) {
                    throw new LogicException('Remote listing ended before its reported record count.');
                }
                $rows = array_merge($rows, $response['rows']);
                if (count($rows) > $total) {
                    throw new LogicException('Remote listing returned more rows than its reported record count.');
                }
                ++$page;
            }
        }

        return $this->result = $rows;
    }

    /** @internal Used by the paginator adapter. */
    public function request(array $pagination = []): array
    {
        if (null === $this->query) {
            $this->execute();
        }

        $query = $this->query;
        $filters = $query['filters'] ?? '';
        unset($query['filters']);

        $options = [
            'query' => array_merge($query, $pagination),
            'headers' => [
                'Accept' => 'application/json',
                'filters' => $filters
            ],
            'timeout' => 30,
            'max_duration' => 120,
            'max_redirects' => 0,
        ];
        if ('' !== $this->token) {
            $options['auth_bearer'] = $this->token;
        }

        $data = $this->httpClient->request('GET', $this->endpoint . '/api/v1/' . $this->resource, $options)->toArray();

        return (new Response())->decode($data, $this->resource);
    }

    private function assertPage(array $response, int $page, int $total): void
    {
        if ($response['records'] !== $total) {
            throw new LogicException('Remote listing changed its record count while retrieving all rows.');
        }
        if (null !== $response['page'] && (int) $response['page'] !== $page) {
            throw new LogicException('Remote listing returned a different page.');
        }
    }
}
