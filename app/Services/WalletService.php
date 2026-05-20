<?php

namespace App\Services;

use App\Jobs\CalculateDepositRebateJob;
use App\Models\Transaction;
use App\Models\Wallet;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Deposit money and enqueue the asynchronous rebate calculation.
     *
     * The wallet balance update and transaction audit record live in the same
     * database transaction. The rebate job is dispatched only after commit, so
     * a worker can never calculate a rebate for a deposit that later rolls back.
     */
    public function deposit(int $userId, string|float|int $amount): Wallet
    {
        $amount = $this->normalizeMoney($amount);
        $amountCents = $this->moneyToCents($amount);

        $wallet = DB::transaction(function () use ($userId, $amount, $amountCents): Wallet {
            $wallet = $this->lockWalletForUser($userId);

            $wallet->balance = $this->centsToMoney(
                $this->moneyToCents($wallet->balance) + $amountCents
            );
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
            ]);

            return $wallet->fresh();
        });

        /*
         * Rebate calculation is intentionally outside the deposit transaction.
         * The queue job will open its own transaction and lock the same wallet
         * row before crediting the rebate, keeping async work concurrency-safe.
         */
        CalculateDepositRebateJob::dispatch($wallet->id, $amount)->afterCommit();

        return $wallet;
    }

    /**
     * Withdraw money while preventing overdrafts.
     *
     * lockForUpdate() serializes all balance-changing operations for this one
     * wallet. If two withdrawals arrive together, the second request sees the
     * balance left by the first request before making its insufficient-funds
     * decision.
     */
    public function withdraw(int $userId, string|float|int $amount): Wallet
    {
        $amount = $this->normalizeMoney($amount);
        $amountCents = $this->moneyToCents($amount);

        return DB::transaction(function () use ($userId, $amount, $amountCents): Wallet {
            $wallet = $this->lockWalletForUser($userId);
            $balanceCents = $this->moneyToCents($wallet->balance);

            if ($balanceCents < $amountCents) {
                throw new DomainException('Insufficient wallet balance.');
            }

            $wallet->balance = $this->centsToMoney($balanceCents - $amountCents);
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'withdrawal',
                'amount' => $amount,
            ]);

            return $wallet->fresh();
        });
    }

    /**
     * Credit the 1% deposit rebate.
     *
     * Jobs can run at the same time as deposits and withdrawals, so the rebate
     * uses the exact same locking pattern as the public wallet operations.
     */
    public function creditRebate(int $walletId, string|float|int $depositAmount): ?Wallet
    {
        $depositAmount = $this->normalizeMoney($depositAmount);
        $rebateCents = (int) round($this->moneyToCents($depositAmount) * 0.01);

        /*
         * Very tiny deposits such as 0.01 produce less than one cent of rebate
         * after two-decimal currency rounding, so there is nothing to credit.
         */
        if ($rebateCents <= 0) {
            return Wallet::find($walletId);
        }

        return DB::transaction(function () use ($walletId, $rebateCents): Wallet {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();

            $wallet->balance = $this->centsToMoney(
                $this->moneyToCents($wallet->balance) + $rebateCents
            );
            $wallet->save();

            $wallet->transactions()->create([
                'type' => 'rebate',
                'amount' => $this->centsToMoney($rebateCents),
            ]);

            return $wallet->fresh();
        });
    }

    public function getWalletForUser(int $userId): Wallet
    {
        return $this->getOrCreateWallet($userId)->fresh('transactions');
    }

    public function getTransactionsForUser(int $userId)
    {
        return $this->getOrCreateWallet($userId)
            ->transactions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Create a wallet if needed, then lock it for this transaction.
     *
     * firstOrCreate() is convenient, but two first-time requests can both try
     * to insert. The unique index on user_id rejects the duplicate insert; the
     * catch block simply reloads the already-created wallet and carries on.
     */
    private function lockWalletForUser(int $userId): Wallet
    {
        $wallet = $this->getOrCreateWallet($userId);

        /** @var Wallet $lockedWallet */
        $lockedWallet = Wallet::query()
            ->whereKey($wallet->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedWallet;
    }

    private function getOrCreateWallet(int $userId): Wallet
    {
        try {
            return Wallet::query()->firstOrCreate(
                ['user_id' => $userId],
                ['balance' => '0.00'],
            );
        } catch (QueryException) {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->where('user_id', $userId)->firstOrFail();

            return $wallet;
        }
    }

    /**
     * Convert input into a database-safe two-decimal money string.
     */
    private function normalizeMoney(string|float|int $amount): string
    {
        if (is_string($amount) && preg_match('/^\d+(\.\d{1,2})?$/', $amount) === 1) {
            [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '00');

            return $whole.'.'.str_pad($decimal, 2, '0');
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Convert a DECIMAL(15,2) string to integer cents.
     *
     * Storing calculations in cents avoids floating point drift such as
     * 10.10 + 0.20 becoming 10.299999999 internally. The conversion parses
     * the normalized string instead of multiplying a float, so large DECIMAL
     * values keep their exact cents too.
     */
    private function moneyToCents(string|float|int $amount): int
    {
        $amount = $this->normalizeMoney($amount);
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
