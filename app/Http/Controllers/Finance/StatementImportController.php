<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Payments\Actions\ImportPaybillStatement;
use App\Domain\Payments\Models\StatementImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatementImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('payments/import', [
            'imports' => StatementImport::query()->with('importer:id,name')->latest()->limit(20)->get()->map(fn (StatementImport $i) => [
                'id' => $i->id,
                'filename' => $i->filename,
                'rows' => $i->rows,
                'created' => $i->created,
                'matched' => $i->matched,
                'duplicates' => $i->duplicates,
                'by' => $i->importer?->name,
                'at' => $i->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, ImportPaybillStatement $import): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        $result = $import->handle($this->group(), $file->getRealPath(), $file->getClientOriginalName());

        $unmatched = $result->created - $result->matched;
        $this->toast("Imported {$result->created} payments: {$result->matched} matched to members, {$unmatched} to match by hand, {$result->duplicates} already recorded.");

        return to_route('payments.index', $unmatched > 0 ? ['status' => 'unmatched'] : []);
    }
}
