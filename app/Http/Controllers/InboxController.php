<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\Models\InboxItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $items = InboxItem::query()->where('user_id', $request->user()->id)->latest('id')->limit(100)->get();

        return Inertia::render('inbox/index', [
            'items' => $items->map(fn (InboxItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'body' => $i->body,
                'url' => $i->url,
                'read' => $i->read_at !== null,
                'at' => $i->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function open(Request $request, InboxItem $item): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 404);

        if ($item->read_at === null) {
            $item->forceFill(['read_at' => now()])->save();
        }

        return $item->url && str_starts_with($item->url, '/') ? redirect($item->url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        InboxItem::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
