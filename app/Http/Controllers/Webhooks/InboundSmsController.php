<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Models\InboundMessage;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Two-way SMS. A member texts BAL (or SALIO) to the group's shortcode and
 * gets their balances back. Field names differ per provider, so the common
 * ones are all accepted. Protected by a shared token in the URL.
 */
class InboundSmsController extends Controller
{
    public function __invoke(Request $request, string $token, Notifier $notifier, CurrentGroup $current): JsonResponse
    {
        abort_unless(hash_equals((string) config('kertai.inbound_sms_token'), $token) && config('kertai.inbound_sms_token') !== null, 404);

        $from = Phone::normalize((string) ($request->input('from') ?? $request->input('msisdn') ?? $request->input('phoneNumber') ?? $request->input('MobileNumber')));
        $text = trim((string) ($request->input('text') ?? $request->input('message') ?? $request->input('Message') ?? ''));

        if ($from === null) {
            return response()->json(['message' => 'No sender.'], 422);
        }

        $member = Member::withoutGlobalScopes()->where('phone', $from)->first();
        $group = $member ? Group::find($member->group_id) : Group::query()->first();

        $inbound = InboundMessage::create([
            'group_id' => $group?->id,
            'provider' => (string) $request->input('provider', 'sms'),
            'sender' => $from,
            'body' => mb_substr($text, 0, 1000),
            'member_id' => $member?->id,
        ]);

        $keyword = strtoupper(strtok($text, " \n") ?: '');

        if ($member && $group && in_array($keyword, ['BAL', 'BALANCE', 'SALIO'], true)) {
            $replies = $current->runAs($group, fn () => $notifier->toMember($member, 'balance_reply', [], ['sms']));
            $inbound->update(['reply_id' => $replies[0]->id ?? null]);
        }

        return response()->json(['message' => 'ok']);
    }
}
