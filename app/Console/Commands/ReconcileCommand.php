<?php

namespace App\Console\Commands;

use App\Domain\Payments\Actions\ReconcilePendingPayments;
use Illuminate\Console\Command;

class ReconcileCommand extends Command
{
    protected $signature = 'kertai:reconcile';

    protected $description = 'Check pending M-Pesa payments with the gateway and expire stale ones';

    public function handle(ReconcilePendingPayments $reconcile): int
    {
        $stats = $reconcile->handle();
        $this->line("Checked {$stats['checked']}, settled {$stats['settled']}, expired {$stats['expired']}.");

        return self::SUCCESS;
    }
}
