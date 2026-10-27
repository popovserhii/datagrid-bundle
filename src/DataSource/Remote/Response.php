<?php

namespace Popov\DatagridBundle\DataSource\Remote;

use UnexpectedValueException;

/** Validates the listing envelope without changing row keys or values. */
class Response
{
    public function decode(array $data, string $resource): array
    {
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
}
