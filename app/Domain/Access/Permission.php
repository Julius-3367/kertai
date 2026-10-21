<?php

namespace App\Domain\Access;

/**
 * Every member can see their own money and pay. These permissions are what a
 * position adds on top. The system administrator bundle deliberately holds
 * no money permissions.
 */
enum Permission: string
{
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case PositionsManage = 'positions.manage';
    case FinanceView = 'finance.view';
    case PaymentsRecord = 'payments.record';
    case DuesManage = 'dues.manage';
    case FinesWaive = 'fines.waive';
    case PayoutsRequest = 'payouts.request';
    case PayoutsApprove = 'payouts.approve';
    case PayoutsPay = 'payouts.pay';
    case LedgerManage = 'ledger.manage';
    case MgrManage = 'mgr.manage';
    case MgrApproveSwaps = 'mgr.approve_swaps';
    case FundraisersManage = 'fundraisers.manage';
    case WelfareManage = 'welfare.manage';
    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case AssetsManage = 'assets.manage';
    case MeetingsManage = 'meetings.manage';
    case MessagesSend = 'messages.send';
    case DocumentsManage = 'documents.manage';
    case SettingsManage = 'settings.manage';
    case IntegrationsManage = 'integrations.manage';
    case AuditView = 'audit.view';
    case BackupsManage = 'backups.manage';

    public function label(): string
    {
        return match ($this) {
            self::MembersView => 'View member register',
            self::MembersManage => 'Add and edit members',
            self::PositionsManage => 'Manage positions and terms',
            self::FinanceView => 'View group finances and reports',
            self::PaymentsRecord => 'Record payments and import statements',
            self::DuesManage => 'Generate dues and fines',
            self::FinesWaive => 'Waive fines',
            self::PayoutsRequest => 'Request payouts',
            self::PayoutsApprove => 'Approve payouts',
            self::PayoutsPay => 'Mark payouts as paid',
            self::LedgerManage => 'Transfers, reversals and opening balances',
            self::MgrManage => 'Run the merry-go-round',
            self::MgrApproveSwaps => 'Approve merry-go-round swaps',
            self::FundraisersManage => 'Manage fundraisers',
            self::WelfareManage => 'Manage welfare cases',
            self::ProjectsView => 'View projects',
            self::ProjectsManage => 'Manage projects, stock and sales',
            self::AssetsManage => 'Manage assets and hire',
            self::MeetingsManage => 'Manage meetings and minutes',
            self::MessagesSend => 'Send SMS and email',
            self::DocumentsManage => 'Manage documents and compliance',
            self::SettingsManage => 'Change group rules',
            self::IntegrationsManage => 'Manage SMS, email and payment keys',
            self::AuditView => 'View audit log',
            self::BackupsManage => 'Create and download backups',
        };
    }

    public function touchesMoney(): bool
    {
        return in_array($this, [
            self::FinanceView, self::PaymentsRecord, self::DuesManage, self::FinesWaive,
            self::PayoutsRequest, self::PayoutsApprove, self::PayoutsPay, self::LedgerManage,
            self::MgrManage, self::WelfareManage, self::FundraisersManage,
        ], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
