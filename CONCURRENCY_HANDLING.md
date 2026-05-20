# Concurrency Handling Documentation

This wallet system allows deposits, withdrawals, and asynchronous rebate jobs to update the same wallet balance. The main concurrency risk is that multiple requests may read the same old balance, calculate from stale data, and overwrite each other.

The project prevents this with database transactions and pessimistic row locking.

## Why Concurrency Handling Is Needed

The wallet balance is shared mutable data. Without locking, this scenario can happen:

```text
Current balance: 50.00

Request A reads balance = 50.00
Request B reads balance = 50.00
Request C reads balance = 50.00

All three requests withdraw 50.00.
All three pass the insufficient-balance check.
All three save the new balance as 0.00.
```

The final wallet balance may look correct at `0.00`, but the system has approved `150.00` in withdrawals while only deducting `50.00`. This is a race condition.

## Chosen Strategy: Pessimistic Locking

This project uses pessimistic locking through Laravel's `lockForUpdate()`:

```php
$wallet = Wallet::query()
    ->whereKey($wallet->id)
    ->lockForUpdate()
    ->firstOrFail();
```

In MySQL/InnoDB, this becomes a `SELECT ... FOR UPDATE` query. It locks the selected wallet row until the current database transaction commits or rolls back.

Only one transaction can hold the lock for the same wallet row at a time. Other deposit, withdrawal, or rebate operations for that wallet must wait.

## Why The Lock Must Be Inside DB::transaction()

`lockForUpdate()` is useful only when it is executed inside a database transaction:

```php
DB::transaction(function () {
    $wallet = Wallet::query()
        ->whereKey($walletId)
        ->lockForUpdate()
        ->firstOrFail();

    // calculate balance
    // save wallet
    // create transaction history
});
```

The row lock is released automatically when the transaction ends:

```text
callback succeeds  -> COMMIT   -> lock released
callback fails     -> ROLLBACK -> lock released
```

Laravel's `DB::transaction()` is a framework helper. It starts the transaction, commits when the callback finishes successfully, and rolls back if an exception is thrown.

## Wallet Creation Safety

The `wallets.user_id` column has a unique index:

```php
$table->unsignedBigInteger('user_id')->unique();
```

This prevents duplicate wallets when two first-time requests try to create a wallet for the same user at the same time.

The service uses `firstOrCreate()`, and if a duplicate insert race happens, it catches the database exception and reloads the wallet that was created by the other request.

## Deposit Flow

Deposit operations are handled in `WalletService::deposit()`.

Flow:

```text
1. Normalize the amount into a two-decimal money string.
2. Convert the amount into integer cents for calculation.
3. Start a database transaction.
4. Get or create the user's wallet.
5. Lock the wallet row with lockForUpdate().
6. Add the deposit amount to the latest locked balance.
7. Save the wallet.
8. Insert a deposit transaction record.
9. Commit the database transaction.
10. Dispatch the rebate job after commit.
```

Important code:

```php
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

CalculateDepositRebateJob::dispatch($wallet->id, $amount)->afterCommit();
```

`afterCommit()` ensures the rebate job is dispatched only after the deposit transaction is successfully committed. If the deposit rolls back, no rebate job is queued for that failed deposit.

## Withdrawal Flow

Withdrawal operations are handled in `WalletService::withdraw()`.

Flow:

```text
1. Normalize the amount.
2. Convert the amount into integer cents.
3. Start a database transaction.
4. Lock the wallet row with lockForUpdate().
5. Read the latest locked balance.
6. Reject the withdrawal if the latest balance is insufficient.
7. Subtract the amount.
8. Save the wallet.
9. Insert a withdrawal transaction record.
10. Commit the database transaction.
```

Important code:

```php
$wallet = $this->lockWalletForUser($userId);
$balanceCents = $this->moneyToCents($wallet->balance);

if ($balanceCents < $amountCents) {
    throw new DomainException('Insufficient wallet balance.');
}

$wallet->balance = $this->centsToMoney($balanceCents - $amountCents);
$wallet->save();
```

The insufficient-balance check happens after the row is locked. This is important because the operation must check the latest committed balance, not a stale balance read before another request finished.

## Rebate Flow

Each deposit dispatches `CalculateDepositRebateJob`. The job calls `WalletService::creditRebate()`.

Flow:

```text
1. Queue worker receives wallet id and original deposit amount.
2. Calculate 1% rebate.
3. Start a database transaction.
4. Lock the same wallet row with lockForUpdate().
5. Add the rebate amount to the latest locked balance.
6. Save the wallet.
7. Insert a rebate transaction record.
8. Commit the database transaction.
```

Important code:

```php
$rebateCents = (int) round($this->moneyToCents($depositAmount) * 0.01);

return DB::transaction(function () use ($walletId, $rebateCents): Wallet {
    $wallet = Wallet::query()
        ->whereKey($walletId)
        ->lockForUpdate()
        ->firstOrFail();

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
```

The rebate job uses the same locking strategy as deposits and withdrawals. This means async jobs cannot overwrite live API requests.

## Deposit, Withdrawal, And Rebate At The Same Time

If one wallet receives a deposit, withdrawal, and rebate at the same time, all three operations try to lock the same wallet row.

Example:

```text
Deposit request  -> waits for wallet row lock
Withdraw request -> waits for wallet row lock
Rebate job       -> waits for wallet row lock
```

Whichever operation gets the lock first runs first. The others wait until the current transaction commits or rolls back.

The database does not guarantee a business-level FIFO order, so the application should not rely on "first HTTP request always runs first." What the system guarantees is that each operation sees the latest committed balance at the moment it holds the row lock.

## Money Calculation

The database stores balances as `DECIMAL(15,2)`, but the service converts values to integer cents before arithmetic:

```php
$amount = $this->normalizeMoney($amount);
$amountCents = $this->moneyToCents($amount);
```

This avoids floating-point precision problems such as:

```text
0.1 + 0.2 = 0.30000000000000004
```

Example:

```text
100.00 -> 10000 cents
1.00   -> 100 cents
10000 + 100 = 10100 cents
10100 cents -> 101.00
```

## Why Not Optimistic Locking

Optimistic locking is also possible, but it usually requires a version column and retry logic:

```text
1. Read wallet with version = 1.
2. Calculate new balance.
3. Update only if version is still 1.
4. If no row is updated, another request changed the wallet first.
5. Reload and retry.
```

This project uses pessimistic locking because wallet balance updates are sensitive and conflicts are expected when multiple deposits, withdrawals, and rebate jobs target the same wallet. Pessimistic locking makes the critical section explicit and easier to reason about for this practical test.

## Idempotency Note

This implementation does not include full idempotency handling.

If the same client sends the same deposit request twice, the system treats it as two deposits. That is acceptable for the current practical test because the requirement focuses on concurrent balance accuracy, not duplicate request prevention.

For a production wallet system, idempotency is recommended. A common approach is to require an `idempotency_key` or external reference for deposit and withdrawal requests, then store it with a unique constraint on the transaction:

```php
$table->string('idempotency_key')->nullable();
$table->unique(['wallet_id', 'idempotency_key']);
```

Then repeated requests with the same key can return the existing result instead of applying the balance movement again.

## Testing

Primary feature tests are in:

```text
tests/Feature/WalletApiTest.php
```

They cover:

```text
- automatic wallet creation
- deposit API
- rebate job dispatch
- rebate job crediting 1%
- withdrawal success
- overdraft rejection
- transaction history
- repeated deposits and rebates
- amount validation
```

There is also a MySQL-specific concurrency test:

```text
tests/Feature/WalletMysqlConcurrencyTest.php
```

This test starts multiple PHP processes that deposit into the same wallet. It verifies that the final balance and transaction count are correct after overlapping operations.

The MySQL test is skipped unless `DB_CONNECTION=mysql`, because SQLite does not provide the same row-level locking behavior as MySQL/InnoDB.

## Summary

The concurrency design is:

```text
DB::transaction()
+ lockForUpdate()
+ integer-cent money calculation
+ transaction history insert
+ async rebate job after commit
```

This ensures that deposits, withdrawals, and rebate jobs update the same wallet safely, without lost updates or stale-balance withdrawal checks.
