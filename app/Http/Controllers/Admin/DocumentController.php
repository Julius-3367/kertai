<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\Position;
use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Documents\Models\ComplianceItem;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Document vault (constitution, certificate, minutes) and the compliance
 * calendar.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $isOfficial = $request->user()->hasPermission(Permission::DocumentsManage->value)
            || $request->user()->hasPermission(Permission::FinanceView->value);

        return Inertia::render('documents/index', [
            'documents' => Document::query()
                ->when(! $isOfficial, fn ($q) => $q->where('visibility', 'members'))
                ->with('uploader:id,name')
                ->latest()
                ->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'category' => $d->category,
                    'original_name' => $d->original_name,
                    'size' => $d->size,
                    'visibility' => $d->visibility,
                    'uploaded_by' => $d->uploader?->name,
                    'at' => $d->created_at->toDateString(),
                ]),
            'compliance' => ComplianceItem::query()->with('position:id,name')->orderBy('status', 'desc')->orderBy('due_on')->get()->map(fn (ComplianceItem $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'due_on' => $c->due_on->toDateString(),
                'recurrence' => $c->recurrence,
                'position' => $c->position?->name,
                'position_id' => $c->position_id,
                'status' => $c->status,
                'completed_on' => $c->completed_on?->toDateString(),
                'overdue' => $c->status === 'pending' && $c->due_on->isPast(),
            ]),
            'categories' => Document::CATEGORIES,
            'positions' => Position::query()->orderBy('sort')->get(['id', 'name']),
            'canManage' => $request->user()->hasPermission(Permission::DocumentsManage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'visibility' => ['required', Rule::in(['members', 'officials'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$this->group()->id, 'local');

        $document = Document::create([
            'title' => $data['title'],
            'category' => $data['category'],
            'visibility' => $data['visibility'],
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        Audit::log('document.uploaded', "Uploaded {$document->title}", $document);
        $this->toast('Document uploaded.');

        return back();
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $isOfficial = $request->user()->hasPermission(Permission::DocumentsManage->value)
            || $request->user()->hasPermission(Permission::FinanceView->value);
        abort_unless($document->visibility === 'members' || $isOfficial, 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        Storage::disk('local')->delete($document->path);
        $document->delete();
        Audit::log('document.deleted', "Deleted document {$document->title}");
        $this->toast('Document deleted.');

        return back();
    }

    public function storeCompliance(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        ComplianceItem::create($this->validatedCompliance($request));
        $this->toast('Added to the compliance calendar.');

        return back();
    }

    public function updateCompliance(Request $request, ComplianceItem $item): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        $item->update($this->validatedCompliance($request));
        $this->toast('Saved.');

        return back();
    }

    /**
     * Mark done. Recurring items roll forward to their next due date.
     */
    public function completeCompliance(ComplianceItem $item): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        if ($item->status === 'done') {
            throw new RuleViolation('Already done.');
        }

        $item->update(['status' => 'done', 'completed_on' => CarbonImmutable::today()->toDateString()]);

        if ($item->recurrence !== 'none') {
            ComplianceItem::create([
                'title' => $item->title,
                'description' => $item->description,
                'due_on' => ($item->recurrence === 'monthly' ? $item->due_on->addMonthNoOverflow()->endOfMonth() : $item->due_on->addYear())->toDateString(),
                'recurrence' => $item->recurrence,
                'position_id' => $item->position_id,
            ]);
        }

        Audit::log('compliance.done', "Completed: {$item->title}", $item);
        $this->toast('Marked as done'.($item->recurrence !== 'none' ? ' and the next one is scheduled.' : '.'));

        return back();
    }

    public function destroyCompliance(ComplianceItem $item): RedirectResponse
    {
        Gate::authorize(Permission::DocumentsManage->value);

        $item->delete();
        $this->toast('Removed.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCompliance(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_on' => ['required', 'date'],
            'recurrence' => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('group_id', $this->group()->id)],
        ]);
    }
}
