<?php

namespace App\Http\Controllers\Members;

use App\Domain\Members\Actions\GiveMemberLogin;
use App\Domain\Members\Actions\ImportMembers;
use App\Http\Controllers\Controller;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('members/import');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(fn () => print (ImportMembers::TEMPLATE), 'kertai-members-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, ImportMembers $import, GiveMemberLogin $login): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'joined_on' => ['required', 'date'],
            'send_logins' => ['boolean'],
        ], ['file.mimes' => 'Upload a CSV file. In Excel use File > Save As > CSV.']);

        $result = $import->handle($this->group(), $data['file']->getRealPath(), $data['joined_on']);

        $sent = 0;
        if ($data['send_logins'] ?? false) {
            foreach ($result['added'] as $member) {
                try {
                    $login->handle($member);
                    $sent++;
                } catch (RuleViolation) {
                    // No usable phone or email; the secretary can issue it later.
                }
            }
        }

        $added = count($result['added']);
        $message = "{$added} ".($added === 1 ? 'member' : 'members').' added';
        $message .= $result['skipped'] > 0 ? ", {$result['skipped']} already on the register skipped" : '';
        $message .= $sent > 0 ? ", login details sent to {$sent}" : '';
        $this->toast($message.'.');

        return to_route('members.index');
    }
}
