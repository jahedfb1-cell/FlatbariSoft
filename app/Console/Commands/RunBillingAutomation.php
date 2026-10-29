<?php

namespace App\Console\Commands;

use App\Services\BillingAutomationService;
use Illuminate\Console\Command;

/** The daily housekeeping: month's bills on the 1st, late fees, due reminders. Run by the schedule (see routes/console.php). */
class RunBillingAutomation extends Command
{
    protected $signature = 'bms:daily';

    protected $description = 'Generate the monthly bills (1st), add late fees, and send due reminders - each only if switched on in Settings';

    public function handle(BillingAutomationService $automation): int
    {
        $period = $automation->generateMonth();
        $this->info($period ? "Bills generated for {$period->year}-{$period->month}." : 'No bills to generate today.');
        $this->info('Late fee added to '.$automation->applyLateFees().' bill(s).');
        $this->info('Reminders sent for '.$automation->sendReminders().' bill(s).');

        return self::SUCCESS;
    }
}
