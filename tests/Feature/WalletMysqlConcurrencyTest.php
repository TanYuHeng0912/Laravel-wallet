<?php

namespace Tests\Feature;

use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class WalletMysqlConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_deposits_with_rebates_are_serialized_by_mysql_row_locks(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Real row-lock concurrency test requires DB_CONNECTION=mysql.');
        }

        $userId = 9001;
        $workers = 8;
        $depositsPerWorker = 5;
        $amount = '10.00';

        $processes = [];

        for ($i = 0; $i < $workers; $i++) {
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'wallet:concurrency-deposit',
                (string) $userId,
                $amount,
                (string) $depositsPerWorker,
                '--run-rebate',
            ], base_path(), [
                'APP_ENV' => 'testing',
                'QUEUE_CONNECTION' => 'database',
                'DB_CONNECTION' => 'mysql',
            ]);

            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() ?: $process->getOutput());
        }

        $wallet = Wallet::query()->where('user_id', $userId)->firstOrFail();

        /*
         * Each deposit is 10.00 and each rebate is 0.10.
         * 8 workers * 5 deposits * 10.10 total movement = 404.00.
         */
        $this->assertSame('404.00', $wallet->balance);
        $this->assertSame($workers * $depositsPerWorker * 2, $wallet->transactions()->count());
    }
}
