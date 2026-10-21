<?php

namespace App\Domain\Reports;

use App\Domain\Groups\Models\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Everything the group owns, as one CSV per table in a zip. Used for the
 * "download all our data" button and for scheduled backups. Secrets
 * (passwords, provider keys, 2FA) are left out.
 */
final class DataExport
{
    /** Tables with a group_id column, exported for that group only. */
    private const GROUP_TABLES = [
        'members', 'positions', 'position_terms', 'accounts', 'journal_entries', 'payments', 'dues',
        'payouts', 'mgr_cycles', 'fundraisers', 'welfare_cases', 'projects', 'assets', 'hires',
        'meetings', 'resolutions', 'message_templates', 'outbound_messages', 'audit_logs',
        'documents', 'compliance_items', 'statement_imports',
    ];

    /** Child tables, exported through their parent's group. */
    private const CHILD_TABLES = [
        'dependants' => ['members', 'member_id'],
        'journal_lines' => ['journal_entries', 'journal_entry_id'],
        'payout_approvals' => ['payouts', 'payout_id'],
        'mgr_slots' => ['mgr_cycles', 'mgr_cycle_id'],
        'mgr_swaps' => ['mgr_cycles', 'mgr_cycle_id'],
        'fundraiser_pledges' => ['fundraisers', 'fundraiser_id'],
        'project_participants' => ['projects', 'project_id'],
        'project_items' => ['projects', 'project_id'],
        'project_entries' => ['projects', 'project_id'],
        'sales' => ['projects', 'project_id'],
        'meeting_attendances' => ['meetings', 'meeting_id'],
    ];

    public function toZip(Group $group, string $disk = 'local', string $directory = 'backups'): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for exports.');
        }

        $name = sprintf('%s/%s-%s.zip', $directory, $group->slug, now()->format('Ymd-His'));
        Storage::disk($disk)->makeDirectory($directory);
        $path = Storage::disk($disk)->path($name);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create {$path}.");
        }

        $zip->addFromString('group.csv', $this->csv(DB::table('groups')->where('id', $group->id)->get()->all()));

        foreach (self::GROUP_TABLES as $table) {
            $rows = DB::table($table)->where('group_id', $group->id)->orderBy('id')->get()->all();
            $zip->addFromString("{$table}.csv", $this->csv($rows));
        }

        foreach (self::CHILD_TABLES as $table => [$parent, $key]) {
            $rows = DB::table($table)
                ->whereIn($key, DB::table($parent)->where('group_id', $group->id)->select('id'))
                ->orderBy('id')
                ->get()
                ->all();
            $zip->addFromString("{$table}.csv", $this->csv($rows));
        }

        $stock = DB::table('stock_movements')
            ->whereIn('project_item_id', DB::table('project_items')->whereIn('project_id', DB::table('projects')->where('group_id', $group->id)->select('id'))->select('id'))
            ->orderBy('id')->get()->all();
        $zip->addFromString('stock_movements.csv', $this->csv($stock));

        $users = DB::table('users')->where('group_id', $group->id)->select(['id', 'name', 'email', 'phone', 'locale', 'last_login_at', 'created_at'])->get()->all();
        $zip->addFromString('users.csv', $this->csv($users));

        $zip->addFromString('README.txt', "Export of {$group->name} taken ".now()->toDateTimeString().".\nMoney columns are in cents (divide by 100 for shillings).\nPasswords and provider keys are not included.\n");
        $zip->close();

        return $name;
    }

    /**
     * @param  list<object>  $rows
     */
    private function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');

        if ($rows !== []) {
            fputcsv($out, array_keys((array) $rows[0]), escape: '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? (int) $v : $v, (array) $row), escape: '\\');
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }
}
