<?php

namespace App\Jobs;

use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateDepositRebateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $walletId,
        public readonly string $depositAmount,
    ) {
        /*
         * Keep the payload small and deterministic. The job receives the wallet
         * id and original deposit amount, then re-locks the wallet when it runs.
         */
    }

    public function handle(WalletService $walletService): void
    {
        $walletService->creditRebate($this->walletId, $this->depositAmount);
    }
}
