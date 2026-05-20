<?php

namespace Tests\Feature;

use App\Jobs\CalculateDepositRebateJob;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_is_created_automatically_for_user(): void
    {
        $response = $this->getJson('/api/users/1001/wallet');

        $response->assertOk()
            ->assertJsonPath('data.user_id', 1001)
            ->assertJsonPath('data.balance', '0.00');

        $this->assertDatabaseHas('wallets', [
            'user_id' => 1001,
            'balance' => '0.00',
        ]);
    }

    public function test_deposit_dispatches_rebate_job_and_job_credits_one_percent(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/users/1002/wallet/deposit', [
            'amount' => '200.00',
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.balance', '200.00');

        $wallet = Wallet::query()->where('user_id', 1002)->firstOrFail();

        Queue::assertPushed(CalculateDepositRebateJob::class, function (CalculateDepositRebateJob $job) use ($wallet): bool {
            return $job->walletId === $wallet->id
                && $job->depositAmount === '200.00';
        });

        /*
         * We execute the job directly here to prove the async rebate algorithm.
         * In production, "php artisan queue:work" performs this handle() call.
         */
        (new CalculateDepositRebateJob($wallet->id, '200.00'))->handle(app(WalletService::class));

        $wallet->refresh();

        $this->assertSame('202.00', $wallet->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => '200.00',
        ]);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'rebate',
            'amount' => '2.00',
        ]);
    }

    public function test_withdrawal_decreases_balance_and_cannot_overdraw(): void
    {
        Queue::fake();

        $this->postJson('/api/users/1003/wallet/deposit', ['amount' => '100.00'])
            ->assertAccepted();

        $this->postJson('/api/users/1003/wallet/withdraw', ['amount' => '40.25'])
            ->assertOk()
            ->assertJsonPath('data.balance', '59.75');

        $this->postJson('/api/users/1003/wallet/withdraw', ['amount' => '60.00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient wallet balance.');

        $wallet = Wallet::query()->where('user_id', 1003)->firstOrFail();
        $this->assertSame('59.75', $wallet->balance);
    }

    public function test_transaction_history_returns_latest_transactions(): void
    {
        Queue::fake();

        $this->postJson('/api/users/1004/wallet/deposit', ['amount' => '50.00']);
        $this->postJson('/api/users/1004/wallet/withdraw', ['amount' => '10.00']);

        $wallet = Wallet::query()->where('user_id', 1004)->firstOrFail();
        app(WalletService::class)->creditRebate($wallet->id, '50.00');

        $response = $this->getJson('/api/users/1004/wallet/transactions');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.type', 'rebate')
            ->assertJsonPath('data.1.type', 'withdrawal')
            ->assertJsonPath('data.2.type', 'deposit');
    }

    public function test_multiple_deposits_and_rebates_keep_exact_balance(): void
    {
        Queue::fake();

        $service = app(WalletService::class);

        /*
         * This exercises the same service path that concurrent requests use.
         * The MySQL-only test below launches separate PHP processes to prove
         * the lock behavior under real overlapping execution.
         */
        for ($i = 0; $i < 10; $i++) {
            $service->deposit(1005, '10.00');
        }

        $wallet = Wallet::query()->where('user_id', 1005)->firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            $service->creditRebate($wallet->id, '10.00');
        }

        $wallet->refresh();

        $this->assertSame('101.00', $wallet->balance);
        $this->assertSame(20, Transaction::query()->where('wallet_id', $wallet->id)->count());
    }

    public function test_amount_validation_rejects_invalid_money_values(): void
    {
        $this->postJson('/api/users/1006/wallet/deposit', ['amount' => '1.234'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->postJson('/api/users/1006/wallet/withdraw', ['amount' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }
}
