<?php

namespace Popov\DatagridBundle\DataSource;

use LogicException;
use Popov\DatagridBundle\DataSource\Remote\Paginator;
use Popov\DatagridBundle\DataSource\Remote\Request;
use Popov\DatagridBundle\DataSource\Remote\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use UnexpectedValueException;
use ZfcDatagrid\DataSource\AbstractDataSource;

/** Forwards the grid's final conditions and returns remote rows unchanged. */
class Remote extends AbstractDataSource
{
    private const LISTING_TYPE = 'application/vnd.timebase.grid-list+json';

    private $httpClient;
    private $endpoint;
    private $resource;
    private $token;
    private $query;
    private $result;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $parameters)
    {
        $this->httpClient = $httpClient;
        $this->endpoint = (string) $parameters->get('elasticsearch_dns');
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
        $data = $this->send($pagination, ['Accept' => 'application/json'])->toArray();

        return (new Response())->decode($data, $this->resource);
    }

    /** Relay the ready-to-serve Grid body while a failed read can still fall back to SQL. */
    public function listingResponse(string $acceptEncoding): HttpResponse
    {
        $upstream = $this->send([], [
            'Accept' => self::LISTING_TYPE,
            'Accept-Encoding' => $acceptEncoding ?: 'identity',
        ]);
        $headers = [
            'Vary' => 'Accept-Encoding',
            'Cache-Control' => 'private, no-store',
            'Access-Control-Allow-Origin' => '*',
        ];
        if ($upstream->getStatusCode() === HttpResponse::HTTP_NOT_ACCEPTABLE) {
            return new HttpResponse('', HttpResponse::HTTP_NOT_ACCEPTABLE, $headers);
        }

        $received = $upstream->getHeaders();
        if ($upstream->getStatusCode() !== HttpResponse::HTTP_OK
            || self::LISTING_TYPE !== strtolower(trim(explode(';', $received['content-type'][0] ?? '', 2)[0]))) {
            throw new UnexpectedValueException('Expected a public Grid listing.');
        }

        $body = $upstream->getContent();
        if ($body === '' || (isset($received['content-length']) && (string) strlen($body) !== $received['content-length'][0])) {
            throw new UnexpectedValueException('Incomplete public Grid listing.');
        }

        return new HttpResponse($body, HttpResponse::HTTP_OK, $headers + [
            'Content-Type' => 'application/json',
            'Content-Encoding' => $received['content-encoding'][0] ?? 'identity',
            'Content-Length' => (string) strlen($body),
        ]);
    }

    /** Send the prepared filters and ordering for either remote representation. */
    private function send(array $pagination, array $headers): ResponseInterface
    {
        if (null === $this->query) {
            $this->execute();
        }

        $query = $this->query;
        $filters = $query['filters'] ?? '';
        unset($query['filters']);

        $options = [
            'query' => array_merge($query, $pagination),
            'headers' => $headers + ['filters' => $filters],
            'buffer' => false,
            'timeout' => 30,
            'max_duration' => 120,
            'max_redirects' => 0,
        ];
        if ('' !== $this->token) {
            $options['auth_bearer'] = $this->token;
        }

        return $this->httpClient->request('GET', $this->endpoint . '/api/v1/' . $this->resource, $options);
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
