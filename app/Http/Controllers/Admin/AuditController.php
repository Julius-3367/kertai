<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('description', "%{$term}%")
                ->orWhereLike('action', "%{$term}%")))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'at' => $l->created_at->toIso8601String(),
                'user' => $l->user->name ?? 'System',
                'action' => $l->action,
                'description' => $l->description,
                'properties' => $l->properties,
                'ip' => $l->ip_address,
            ]);

        return Inertia::render('admin/audit', [
            'logs' => $logs,
            'filters' => $request->only('q'),
        ]);
    }
}
