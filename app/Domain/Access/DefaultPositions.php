<?php

namespace App\Domain\Access;

use App\Domain\Access\Permission as P;

final class DefaultPositions
{
    /**
     * @return array<string, array{name: string, description: string, max_holders: int|null, is_official: bool, sort: int, permissions: list<P>}>
     */
    public static function all(): array
    {
        return [
            'chairperson' => [
                'name' => 'Chairperson',
                'description' => 'Leads the group, approves payouts, swaps and rule changes.',
                'max_holders' => 1,
                'is_official' => true,
                'sort' => 10,
                'permissions' => [
                    P::MembersView, P::FinanceView, P::FinesWaive, P::PayoutsApprove, P::MgrApproveSwaps,
                    P::WelfareManage, P::FundraisersManage, P::ProjectsView, P::MeetingsManage,
                    P::MessagesSend, P::DocumentsManage, P::SettingsManage, P::PositionsManage, P::AuditView,
                ],
            ],
            'secretary' => [
                'name' => 'Secretary',
                'description' => 'Keeps the register, meetings and minutes; second approver on payouts.',
                'max_holders' => 1,
                'is_official' => true,
                'sort' => 20,
                'permissions' => [
                    P::MembersView, P::MembersManage, P::FinanceView, P::PayoutsApprove, P::WelfareManage,
                    P::ProjectsView, P::MeetingsManage, P::MessagesSend, P::DocumentsManage,
                ],
            ],
            'treasurer' => [
                'name' => 'Treasurer',
                'description' => 'Records money in and out, runs dues, the merry-go-round and reconciliation.',
                'max_holders' => 1,
                'is_official' => true,
                'sort' => 30,
                'permissions' => [
                    P::MembersView, P::FinanceView, P::PaymentsRecord, P::DuesManage, P::PayoutsRequest,
                    P::PayoutsApprove, P::PayoutsPay, P::LedgerManage, P::MgrManage, P::FundraisersManage,
                    P::WelfareManage, P::ProjectsView, P::AssetsManage,
                ],
            ],
            'coordinator' => [
                'name' => 'Coordinator',
                'description' => 'Organises group activities, fundraisers and communication.',
                'max_holders' => null,
                'is_official' => true,
                'sort' => 40,
                'permissions' => [
                    P::MembersView, P::FundraisersManage, P::ProjectsView, P::MeetingsManage,
                    P::MessagesSend, P::DocumentsManage,
                ],
            ],
            'project-coordinator' => [
                'name' => 'Project coordinator',
                'description' => 'Runs projects: participants, expenses, stock and sales.',
                'max_holders' => null,
                'is_official' => true,
                'sort' => 50,
                'permissions' => [P::MembersView, P::ProjectsView, P::ProjectsManage, P::AssetsManage, P::PayoutsRequest],
            ],
            'auditor' => [
                'name' => 'Auditor',
                'description' => 'Read-only access to every record and the audit log.',
                'max_holders' => null,
                'is_official' => true,
                'sort' => 60,
                'permissions' => [P::MembersView, P::FinanceView, P::ProjectsView, P::AuditView],
            ],
            'system-admin' => [
                'name' => 'System administrator',
                'description' => 'Manages accounts, positions, keys and backups. Cannot see or move money.',
                'max_holders' => null,
                'is_official' => false,
                'sort' => 90,
                'permissions' => [
                    P::MembersView, P::MembersManage, P::PositionsManage, P::IntegrationsManage,
                    P::AuditView, P::BackupsManage, P::MessagesSend,
                ],
            ],
        ];
    }
}
