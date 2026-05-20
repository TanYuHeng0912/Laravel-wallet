<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Console\Command;

class WalletConcurrencyDepositCommand extends Command
{
    protected $signature = 'wallet:concurrency-deposit
        {userId : User id that owns the wallet}
        {amount : Deposit amount}
        {count=1 : Number of deposits this process should execute}
        {--run-rebate : Credit the rebate immediately after each deposit}';

    protected $description = 'Hidden helper used by the MySQL concurrency test and manual stress checks.';

    protected $hidden = true;

    public function handle(WalletService $walletService): int
    {
        $userId = (int) $this->argument('userId');
        $amount = (string) $this->argument('amount');
        $count = (int) $this->argument('count');

        for ($i = 0; $i < $count; $i++) {
            $wallet = $walletService->deposit($userId, $amount);

            /*
             * The API keeps rebates asynchronous. This option exists only for
             * deterministic stress tests where child processes should finish
             * both the deposit and the rebate before the parent assertion runs.
             */
            if ($this->option('run-rebate')) {
                $walletService->creditRebate($wallet->id, $amount);
            }
        }

        $wallet = Wallet::query()->where('user_id', $userId)->first();
        $this->line($wallet?->balance ?? '0.00');

        return self::SUCCESS;
    }
}
