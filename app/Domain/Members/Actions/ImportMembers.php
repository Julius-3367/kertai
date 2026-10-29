<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Support\Phone;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adds members from a CSV saved out of Excel or Google Sheets. Every row is
 * checked first; if any row is wrong nothing is saved. People already on
 * the register (same phone) are skipped.
 */
final class ImportMembers
{
    /** Header names accepted for each field, lower case. */
    private const HEADERS = [
        'name' => ['name', 'full name', 'member name'],
        'phone' => ['phone', 'phone number', 'mobile', 'telephone'],
        'member_number' => ['member number', 'member no', 'number', 'no'],
        'email' => ['email', 'email address'],
        'id_number' => ['id number', 'id no', 'id', 'national id'],
        'gender' => ['gender', 'sex'],
        'date_of_birth' => ['date of birth', 'dob', 'birthday'],
        'joined_on' => ['joined on', 'joined', 'date joined', 'join date'],
    ];

    public const TEMPLATE = "Name,Phone,Member number,Email,ID number,Gender,Date of birth,Joined on\nJane Wanjiku,0712345678,,jane@example.com,12345678,female,1990-05-14,2024-01-01\n";

    public function __construct(private readonly SaveMember $save) {}

    /**
     * @return array{added: list<Member>, skipped: int}
     */
    public function handle(Group $group, string $path, string $defaultJoinedOn): array
    {
        $rows = $this->read($path);

        if ($rows === []) {
            throw new RuleViolation('The file has no member rows under the header.');
        }

        $existingPhones = Member::withoutGlobalScopes()->where('group_id', $group->id)->pluck('phone')->all();
        $existingNumbers = Member::withoutGlobalScopes()->where('group_id', $group->id)->pluck('member_number')->map(fn ($n) => strtoupper((string) $n))->all();

        $errors = [];
        $valid = [];
        $skipped = 0;
        $seenPhones = [];
        $seenNumbers = [];

        foreach ($rows as $line => $row) {
            $problems = [];
            $name = trim($row['name'] ?? '');
            $phone = Phone::normalize($row['phone'] ?? null);
            $number = strtoupper(trim($row['member_number'] ?? ''));
            $email = strtolower(trim($row['email'] ?? ''));

            if ($name === '') {
                $problems[] = 'name is missing';
            }

            if ($phone === null) {
                $problems[] = 'phone "'.($row['phone'] ?? '').'" is not a Kenyan mobile number';
            } elseif (in_array($phone, $existingPhones, true)) {
                $skipped++;

                continue;
            } elseif (in_array($phone, $seenPhones, true)) {
                $problems[] = 'phone appears twice in the file';
            }

            if ($number !== '' && (in_array($number, $existingNumbers, true) || in_array($number, $seenNumbers, true))) {
                $problems[] = "member number {$number} is already used";
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $problems[] = "email \"{$email}\" is not valid";
            }

            $gender = strtolower(trim($row['gender'] ?? ''));
            $gender = match ($gender) {
                'f', 'female', 'woman' => 'female',
                'm', 'male', 'man' => 'male',
                '' => null,
                default => false,
            };

            if ($gender === false) {
                $problems[] = 'gender must be female or male';
            }

            $born = $this->date($row['date_of_birth'] ?? '');
            $joined = $this->date($row['joined_on'] ?? '') ?? $defaultJoinedOn;

            if ($born === false || ($born !== null && $born >= CarbonImmutable::today()->toDateString())) {
                $problems[] = 'date of birth is not a past date (use YYYY-MM-DD or DD/MM/YYYY)';
            }

            if ($joined === false) {
                $problems[] = 'joined date is not a date (use YYYY-MM-DD or DD/MM/YYYY)';
            }

            if ($problems !== []) {
                $errors[] = "Row {$line}: ".implode('; ', $problems).'.';

                continue;
            }

            $seenPhones[] = $phone;

            if ($number !== '') {
                $seenNumbers[] = $number;
            }

            $valid[] = array_filter([
                'name' => $name,
                'phone' => $phone,
                'member_number' => $number ?: null,
                'email' => $email ?: null,
                'id_number' => trim($row['id_number'] ?? '') ?: null,
                'gender' => $gender,
                'date_of_birth' => $born,
                'joined_on' => $joined,
                'locale' => 'en',
            ], fn ($v) => $v !== null);
        }

        if ($errors !== []) {
            $more = count($errors) > 15 ? ' And '.(count($errors) - 15).' more.' : '';

            throw new RuleViolation('Nothing was imported. Fix these rows and upload again: '.implode(' ', array_slice($errors, 0, 15)).$more);
        }

        $added = DB::transaction(fn () => array_map(fn (array $data) => $this->save->handle($group, $data), $valid));

        Audit::log('members.imported', 'Imported '.count($added).' members from a file', null, ['skipped' => $skipped], $group->id);

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * @return array<int, array<string, string>> keyed by the row's line number in the file
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuleViolation('The file could not be read.');
        }

        $header = fgetcsv($handle, escape: '\\');

        if ($header === false || $header === [null]) {
            fclose($handle);

            throw new RuleViolation('The file is empty.');
        }

        // Excel adds a byte-order mark to the first heading.
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? '')), $header);
        $map = [];

        foreach ($header as $i => $heading) {
            foreach (self::HEADERS as $field => $aliases) {
                if (in_array($heading, $aliases, true) && ! in_array($field, $map, true)) {
                    $map[$i] = $field;
                }
            }
        }

        if (! in_array('name', $map, true) || ! in_array('phone', $map, true)) {
            fclose($handle);

            throw new RuleViolation('The first row must hold the column names, including Name and Phone. Download the template to see the layout.');
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;

            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;
            }

            $row = [];
            foreach ($map as $i => $field) {
                $row[$field] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[$line] = $row;

            if (count($rows) > 1000) {
                fclose($handle);

                throw new RuleViolation('Import at most 1,000 members at a time.');
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return string|false|null Y-m-d, false when unreadable, null when blank
     */
    private function date(string $value): string|false|null
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date !== null && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return false;
    }
}
