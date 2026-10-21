<?php

namespace App\Domain\Payments\Import;

use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads the credit lines from an Equity Bank statement or an M-Pesa paybill
 * statement exported as CSV. Column names differ between exports, so each
 * field is found by any of its usual headings.
 */
final class StatementParser
{
    private const ALIASES = [
        'date' => ['transaction date', 'date', 'value date', 'completion time', 'trans date', 'posting date', 'initiation time'],
        'narrative' => ['narrative', 'description', 'details', 'particulars', 'transaction details', 'other party info', 'remarks'],
        'credit' => ['credit', 'paid in', 'money in', 'credit amount', 'deposits', 'deposit', 'amount'],
        'reference' => ['reference', 'transaction reference', 'receipt no.', 'receipt no', 'receipt', 'bank reference', 'ref', 'transaction id', 'tran ref'],
        'phone' => ['phone', 'msisdn', 'mobile', 'phone number'],
        'name' => ['name', 'customer name', 'payer', 'sender'],
        'account' => ['a/c no.', 'a/c no', 'account', 'bill ref', 'account reference', 'account number'],
        'status' => ['transaction status', 'status'],
    ];

    /**
     * @return array<int, array{date: CarbonImmutable, amount: int, reference: string, narrative: string, phone: string|null, phones: array<int, string>, name: string|null, account: string|null}>
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuleViolation('Could not open the uploaded file.');
        }

        $columns = null;
        $rows = [];

        try {
            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                if ($columns === null) {
                    $columns = $this->detectColumns($row);

                    continue;
                }

                $parsed = $this->parseRow($row, $columns);

                if ($parsed !== null) {
                    $rows[] = $parsed;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($columns === null) {
            throw new RuleViolation('The file has no header row with Date and Credit / Paid In columns.');
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>|null
     */
    private function detectColumns(array $header): ?array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $columns = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $index = array_search($alias, $normalized, true);

                if ($index !== false && ! in_array($index, $columns, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        // Banners above the real header: keep reading until we find one.
        return isset($columns['date'], $columns['credit']) ? $columns : null;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $columns
     * @return array{date: CarbonImmutable, amount: int, reference: string, narrative: string, phone: string|null, phones: array<int, string>, name: string|null, account: string|null}|null
     */
    private function parseRow(array $row, array $columns): ?array
    {
        $get = fn (string $field) => isset($columns[$field]) ? trim((string) ($row[$columns[$field]] ?? '')) : '';

        $status = strtolower($get('status'));
        if ($status !== '' && $status !== 'completed') {
            return null;
        }

        $rawAmount = preg_replace('/[^\d.\-]/', '', $get('credit')) ?? '';
        if ($rawAmount === '' || ! is_numeric($rawAmount) || (float) $rawAmount <= 0) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse(str_replace('/', '-', $get('date')));
        } catch (Throwable) {
            return null;
        }

        $amount = Money::toCents(number_format((float) $rawAmount, 2, '.', ''));
        $narrative = $get('narrative');
        $reference = $get('reference');

        if ($reference === '' && preg_match('/\b([A-Z]{2}[A-Z0-9]{8})\b/', $narrative, $m)) {
            $reference = $m[1];
        }

        if ($reference === '') {
            $reference = 'ROW-'.substr(sha1($date->toDateString().'|'.$amount.'|'.$narrative), 0, 16);
        }

        $phones = array_values(array_unique(array_filter([Phone::normalize($get('phone')), ...Phone::extractAll($narrative)])));

        return [
            'date' => $date,
            'amount' => $amount,
            'reference' => mb_substr($reference, 0, 100),
            'narrative' => $narrative,
            'phone' => $phones[0] ?? null,
            'phones' => $phones,
            'name' => $get('name') ?: null,
            'account' => $get('account') ?: null,
        ];
    }
}
