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
    public const REMINDERS = ['contribution_reminder', 'arrears_reminder', 'loan_reminder', 'meeting_notice', 'meeting_reminder', 'mgr_turn', 'birthday'];

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
            'loan_reminder' => [
                'label' => 'Loan reminder',
                'subject' => 'Loan {loan}: {behind} to pay',
                'en' => 'Dear {name}, your loan {loan} has {balance} outstanding and {behind} is due now. Pay from the app or see the treasurer. {group}',
                'sw' => 'Mpendwa {name}, mkopo wako {loan} una salio la {balance} na {behind} inadaiwa sasa. Lipa kupitia programu au mwone mweka hazina. {group}',
            ],
            'guarantor_request' => [
                'label' => 'Asked to guarantee a loan',
                'subject' => '{borrower} asks you to guarantee {amount}',
                'en' => 'Dear {name}, {borrower} has asked you to guarantee {amount} of a {loan_amount} loan. Open {link} to accept or decline. {group}',
                'sw' => 'Mpendwa {name}, {borrower} amekuomba udhamini {amount} wa mkopo wa {loan_amount}. Fungua {link} kukubali au kukataa. {group}',
            ],
            'guarantor_response' => [
                'label' => 'Guarantor answered',
                'subject' => '{guarantor} {answer} your guarantor request',
                'en' => 'Dear {name}, {guarantor} has {answer} your request to guarantee loan {loan}. {link} {group}',
                'sw' => 'Mpendwa {name}, {guarantor} amejibu ombi la kudhamini mkopo wako {loan}: {answer}. {link} {group}',
            ],
            'loan_ready' => [
                'label' => 'Loan ready for approval',
                'subject' => 'Loan {loan} is ready to send for approval',
                'en' => 'Loan {loan} of {amount} for {borrower} has all its guarantors. Send it for approval: {link}',
                'sw' => 'Mkopo {loan} wa {amount} kwa {borrower} una wadhamini wote. Upeleke kwa idhini: {link}',
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
                'en' => 'Dear {name}, {title} is on {date} at {venue}. Please attend.{join} {group}',
                'sw' => 'Mpendwa {name}, {title} itakuwa tarehe {date} katika {venue}. Tafadhali hudhuria.{join_sw} {group}',
            ],
            'meeting_reminder' => [
                'label' => 'Meeting today',
                'subject' => 'Today: {title} at {time}',
                'en' => 'Reminder: {title} is today at {time}, {venue}.{join} {group}',
                'sw' => 'Kumbusho: {title} ni leo saa {time}, {venue}.{join_sw} {group}',
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
            'poll_opened' => [
                'label' => 'New vote',
                'subject' => 'Vote: {question}',
                'en' => 'Dear {name}, please vote on "{question}" by {closes}. Open {link} to vote. {group}',
                'sw' => 'Mpendwa {name}, tafadhali piga kura kuhusu "{question}" kabla ya {closes}. Fungua {link} kupiga kura. {group}',
            ],
            'birthday' => [
                'label' => 'Birthday greeting',
                'subject' => 'Happy birthday, {name}',
                'en' => 'Happy birthday {name}! Wishing you good health and a blessed year ahead. From all of us at {group}.',
                'sw' => 'Heri ya siku ya kuzaliwa {name}! Tunakutakia afya njema na mwaka wenye baraka. Kutoka kwetu sote {group}.',
            ],
            'change_request_new' => [
                'label' => 'Member asked to change details',
                'subject' => '{member} asked to change their details',
                'en' => 'Dear {name}, {member} has asked to change: {fields}. Open {link} to approve or reject. {group}',
                'sw' => 'Mpendwa {name}, {member} ameomba kubadilisha: {fields}. Fungua {link} kukubali au kukataa. {group}',
            ],
            'change_request_decided' => [
                'label' => 'Details change answered',
                'subject' => 'Your details change was {decision}',
                'en' => 'Dear {name}, your request to change {fields} was {decision}. {reason} {group}',
                'sw' => 'Mpendwa {name}, ombi lako la kubadilisha {fields} lime{decision_sw}. {reason} {group}',
            ],
        ];
    }
}
