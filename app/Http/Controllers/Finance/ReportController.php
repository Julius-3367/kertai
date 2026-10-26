<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\Permission;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\Reports;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Reports $reports): Response
    {
        $from = $request->date('from')?->toImmutable() ?? CarbonImmutable::today()->startOfYear();
        $to = $request->date('to')?->toImmutable() ?? CarbonImmutable::today();
        $year = $request->integer('year') ?: CarbonImmutable::today()->year;

        return Inertia::render('reports/index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'year' => $year],
            'overview' => $reports->overview(CarbonImmutable::today()),
            'funds' => $reports->fundMovements($from, $to),
            'trial' => $reports->trialBalance($to),
            'grid' => $reports->contributionGrid($year),
            'arrears' => $reports->arrears(),
            'members' => $this->memberOptions(false),
        ]);
    }

    /**
     * The group's books as every member sees them, once the group has agreed
     * to open them. No phone numbers; officials can preview it before then.
     */
    public function groupBooks(Request $request, Reports $reports): Response
    {
        abort_unless($this->group()->settings()->membersSeeGroupFinances() || $request->user()->hasPermission(Permission::FinanceView->value), 403);

        $year = $request->integer('year') ?: CarbonImmutable::today()->year;

        return Inertia::render('reports/group', [
            'year' => $year,
            'overview' => $reports->overview(CarbonImmutable::today()),
            'grid' => $reports->contributionGrid($year),
            'arrears' => collect($reports->arrears())
                ->map(fn (array $r) => Arr::only($r, ['member_id', 'name', 'member_number', 'arrears', 'fines', 'oldest']))
                ->all(),
        ]);
    }

    public function annual(Request $request, Reports $reports): Response
    {
        $year = $request->integer('year') ?: CarbonImmutable::today()->year;

        return Inertia::render('reports/annual', ['report' => $reports->annual($this->group(), $year)]);
    }

    /**
     * Member statement. Members can open their own; officials with finance
     * access can open anyone's.
     */
    public function statement(Request $request, Member $member, MemberFinance $finance): Response|HttpResponse|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->member?->id === $member->id || $user->hasPermission(Permission::FinanceView->value), 403);

        $from = $request->date('from')?->toImmutable();
        $to = $request->date('to')?->toImmutable();
        $lines = $finance->statement($member, $from, $to);
        $summary = $finance->summary($member);
        $format = $request->string('format')->toString();

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($lines) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, ['Date', 'Entry', 'Description', 'Fund', 'In (KES)', 'Out (KES)', 'Savings balance (KES)']);
                foreach ($lines as $l) {
                    fputcsv($out, [$l['date'], $l['entry_number'], $l['description'], $l['fund'], Money::toShillings($l['in']), Money::toShillings($l['out']), Money::toShillings($l['savings_balance'])]);
                }
                fclose($out);
            }, Str::slug("{$member->member_number}-{$member->name}-statement").'.csv', ['Content-Type' => 'text/csv']);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.statement', [
                'group' => Group::findOrFail($member->group_id),
                'member' => $member,
                'lines' => $lines,
                'summary' => $summary,
                'from' => $from,
                'to' => $to,
            ])->setPaper('a4');

            return $pdf->download(Str::slug("{$member->member_number}-{$member->name}-statement").'.pdf');
        }

        return Inertia::render('reports/statement', [
            'member' => ['id' => $member->id, 'name' => $member->name, 'member_number' => $member->member_number, 'status' => $member->status],
            'lines' => array_reverse($lines),
            'summary' => $summary,
            'filters' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
        ]);
    }

    public function arrearsCsv(Reports $reports): StreamedResponse
    {
        $rows = $reports->arrears();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Member no.', 'Name', 'Phone', 'Arrears (KES)', 'Fines (KES)', 'Oldest due']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['member_number'], $r['name'], $r['phone'], Money::toShillings($r['arrears']), Money::toShillings($r['fines']), $r['oldest'] ? CarbonImmutable::parse($r['oldest'])->toDateString() : '']);
            }
            fclose($out);
        }, 'arrears-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
