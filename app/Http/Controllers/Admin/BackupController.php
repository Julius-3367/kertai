<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Reports\DataExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Full data export as CSVs in a zip. These are the group's own copy; the
 * server should also take database dumps (see README).
 */
class BackupController extends Controller
{
    public function index(): Response
    {
        $prefix = $this->group()->slug.'-';
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_starts_with(basename($f), $prefix))
            ->sortDesc()
            ->values()
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => Storage::disk('local')->size($f),
                'at' => date(DATE_ATOM, Storage::disk('local')->lastModified($f)),
            ]);

        return Inertia::render('admin/backups', ['files' => $files]);
    }

    public function store(DataExport $export): RedirectResponse
    {
        $path = $export->toZip($this->group());
        Audit::log('backup.created', 'Created data export '.basename($path));
        $this->toast('Export ready to download.');

        return back();
    }

    public function download(string $name): StreamedResponse
    {
        $name = basename($name);
        abort_unless(str_starts_with($name, $this->group()->slug.'-') && str_ends_with($name, '.zip'), 404);
        abort_unless(Storage::disk('local')->exists("backups/{$name}"), 404);

        Audit::log('backup.downloaded', "Downloaded data export {$name}");

        return Storage::disk('local')->download("backups/{$name}");
    }
}
