<?php

namespace Popov\DatagridBundle\DataSource\Remote;

use UnexpectedValueException;

/** Validates listing envelopes and native Elasticsearch search responses. */
class Response
{
    public function decode(array $data, string $resource): array
    {
        if (isset($data['hits'])) {
            return $this->elasticsearch($data);
        }

        $listing = $data['data'] ?? $data;
        if (is_array($listing) && isset($listing[$resource]) && is_array($listing[$resource])) {
            $listing = $listing[$resource];
        }
        if (!is_array($listing) || !isset($listing['rows'], $listing['records']) || !is_array($listing['rows'])) {
            throw new UnexpectedValueException('Remote listing must contain rows and records.');
        }
        $records = $listing['records'];
        if (is_string($records) && ctype_digit($records) && (string) (int) $records === $records) {
            $records = (int) $records;
        }
        if (!is_int($records) || $records < 0 || count($listing['rows']) > $records) {
            throw new UnexpectedValueException('Invalid remote listing record count.');
        }
        foreach ($listing['rows'] as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Remote listing rows must be JSON objects.');
            }
        }

        return ['rows' => $listing['rows'], 'records' => $records, 'page' => $listing['page'] ?? null];
    }

    private function elasticsearch(array $data): array
    {
        $total = $data['hits']['total'] ?? null;
        if (($data['timed_out'] ?? true) !== false
            || ($data['_shards']['failed'] ?? null) !== 0
            || isset($data['error'])
            || !is_array($total)
            || ($total['relation'] ?? null) !== 'eq'
            || !is_int($total['value'] ?? null)
            || $total['value'] < 0
        ) {
            throw new UnexpectedValueException('Elasticsearch returned an incomplete search result.');
        }

        if (isset($data['aggregations']['groups'])) {
            return $this->groups($data['aggregations']['groups'], $total['value']);
        }

        $rows = $this->sources($data['hits']['hits'] ?? null);
        if (count($rows) > $total['value']) {
            throw new UnexpectedValueException('Elasticsearch returned more rows than its exact total.');
        }

        return ['rows' => $rows, 'records' => $total['value'], 'page' => null];
    }

    private function groups(array $groups, int $total): array
    {
        $buckets = $groups['buckets'] ?? null;
        if (!is_array($buckets) || count($buckets) > 10000) {
            throw new UnexpectedValueException('Elasticsearch returned too many or invalid groups.');
        }

        $rows = [];
        $covered = 0;
        foreach ($buckets as $bucket) {
            if (!is_array($bucket) || !is_int($bucket['doc_count'] ?? null) || $bucket['doc_count'] < 1) {
                throw new UnexpectedValueException('Elasticsearch returned an invalid group.');
            }
            $hits = $this->sources($bucket['row']['hits']['hits'] ?? null);
            if (count($hits) !== 1) {
                throw new UnexpectedValueException('An Elasticsearch group must contain one representative row.');
            }
            $rows[] = $hits[0];
            $covered += $bucket['doc_count'];
        }
        if ($covered !== $total) {
            throw new UnexpectedValueException('Elasticsearch groups are incomplete or overlap.');
        }

        return ['rows' => $rows, 'records' => count($rows), 'page' => null, 'all_rows' => true];
    }

    private function sources($hits): array
    {
        if (!is_array($hits)) {
            throw new UnexpectedValueException('Elasticsearch search hits must be an array.');
        }
        $rows = [];
        foreach ($hits as $hit) {
            if (!is_array($hit) || !is_array($hit['_source'] ?? null)) {
                throw new UnexpectedValueException('Elasticsearch search hits must contain source objects.');
            }
            $rows[] = $hit['_source'];
        }

        return $rows;
    }
}
