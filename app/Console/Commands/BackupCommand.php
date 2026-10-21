<?php

namespace App\Console\Commands;

use App\Domain\Groups\Models\Group;
use App\Domain\Reports\DataExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupCommand extends Command
{
    protected $signature = 'kertai:backup {--keep=30 : How many exports to keep per group}';

    protected $description = 'Write a CSV export of every group to storage/app/private/backups and prune old ones';

    public function handle(DataExport $export): int
    {
        foreach (Group::all() as $group) {
            $path = $export->toZip($group);
            $this->line("{$group->name}: {$path}");

            $old = collect(Storage::disk('local')->files('backups'))
                ->filter(fn ($f) => str_starts_with(basename($f), $group->slug.'-'))
                ->sortDesc()
                ->slice((int) $this->option('keep'));

            Storage::disk('local')->delete($old->all());
        }

        return self::SUCCESS;
    }
}
