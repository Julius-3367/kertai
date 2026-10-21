<?php

namespace App\Domain\Notifications;

/**
 * Starting wording for every automatic message, in English and Kiswahili.
 * Officials can edit them under Messages > Templates. SMS bodies are kept
 * near one 160-character page; email reuses the same text with a subject.
 */
final class DefaultTemplates
{
    /** Keys a member can switch off with "reminders off". Everything else always goes. */
    public const REMINDERS = ['contribution_reminder', 'arrears_reminder', 'meeting_notice', 'mgr_turn'];

    /**
     * @return array<string, array{label: string, subject: string, en: string, sw: string}>
     */
    public static function all(): array
    {
        return [
            'payment_received' => [
                'label' => 'Payment received',
                'subject' => 'Payment received: {amount}',
                'en' => 'Dear {name}, we have received {amount} for {purpose}. Ref {receipt}. Savings {savings}, arrears {arrears}. {group}',
                'sw' => 'Mpendwa {name}, tumepokea {amount} kwa {purpose}. Kumb. {receipt}. Akiba {savings}, madeni {arrears}. {group}',
            ],
            'contribution_reminder' => [
                'label' => 'Contribution reminder',
                'subject' => 'Contribution due {due_date}',
                'en' => 'Dear {name}, your {period} contribution of {amount} is due on {due_date}. Paybill {paybill}, account {account}. {group}',
                'sw' => 'Mpendwa {name}, mchango wako wa {period} wa {amount} unadaiwa tarehe {due_date}. Paybill {paybill}, akaunti {account}. {group}',
            ],
            'arrears_reminder' => [
                'label' => 'Arrears reminder',
                'subject' => 'Outstanding balance {arrears}',
                'en' => 'Dear {name}, you have arrears of {arrears}. Please clear them to stay in good standing. Paybill {paybill}, account {account}. {group}',
                'sw' => 'Mpendwa {name}, una madeni ya {arrears}. Tafadhali lipa ili kubaki katika hali nzuri. Paybill {paybill}, akaunti {account}. {group}',
            ],
            'fine_applied' => [
                'label' => 'Fine charged',
                'subject' => 'Fine charged: {amount}',
                'en' => 'Dear {name}, a fine of {amount} has been charged: {reason}. {group}',
                'sw' => 'Mpendwa {name}, umetozwa faini ya {amount}: {reason}. {group}',
            ],
            'payout_approval_needed' => [
                'label' => 'Payout needs approval',
                'subject' => 'Payout #{number} needs your approval',
                'en' => 'Payout #{number} of {amount} to {payee} ({description}) needs your approval: {link}',
                'sw' => 'Malipo #{number} ya {amount} kwa {payee} ({description}) yanahitaji idhini yako: {link}',
            ],
            'payout_paid' => [
                'label' => 'Payout sent to member',
                'subject' => '{amount} paid to you',
                'en' => 'Dear {name}, {amount} has been paid to you for {description}. Ref {reference}. {group}',
                'sw' => 'Mpendwa {name}, umelipwa {amount} kwa {description}. Kumb. {reference}. {group}',
            ],
            'meeting_notice' => [
                'label' => 'Meeting notice',
                'subject' => 'Meeting: {title} on {date}',
                'en' => 'Dear {name}, {title} is on {date} at {venue}. Please attend. {group}',
                'sw' => 'Mpendwa {name}, {title} itakuwa tarehe {date} katika {venue}. Tafadhali hudhuria. {group}',
            ],
            'welfare_levy' => [
                'label' => 'Welfare levy raised',
                'subject' => 'Welfare levy: {amount}',
                'en' => 'Dear {name}, a welfare levy of {amount} has been raised for {beneficiary}. Due {due_date}. {group}',
                'sw' => 'Mpendwa {name}, mchango wa ustawi wa {amount} umewekwa kwa ajili ya {beneficiary}. Lipa kabla ya {due_date}. {group}',
            ],
            'mgr_turn' => [
                'label' => 'Merry-go-round turn',
                'subject' => 'Your merry-go-round turn',
                'en' => 'Dear {name}, your merry-go-round payout of {amount} is scheduled for {date}. {group}',
                'sw' => 'Mpendwa {name}, zamu yako ya mchezo ya {amount} imepangwa tarehe {date}. {group}',
            ],
            'account_created' => [
                'label' => 'Login details',
                'subject' => 'Your {group} login',
                'en' => 'Welcome to {group} online. Sign in at {link} with {login} and password {password}. You will be asked to change it.',
                'sw' => 'Karibu {group} mtandaoni. Ingia {link} kwa {login} na nenosiri {password}. Utaombwa kulibadilisha.',
            ],
            'balance_reply' => [
                'label' => 'Reply to BAL',
                'subject' => 'Your balance',
                'en' => '{name}: savings {savings}, arrears {arrears}, fines {fines}. {group}',
                'sw' => '{name}: akiba {savings}, madeni {arrears}, faini {fines}. {group}',
            ],
            'compliance_due' => [
                'label' => 'Compliance reminder',
                'subject' => 'Due {date}: {title}',
                'en' => 'Reminder: {title} is due on {date}. {group}',
                'sw' => 'Kumbusho: {title} inahitajika tarehe {date}. {group}',
            ],
        ];
    }
}
