# Laravel Wallet System

This project is a Laravel wallet management API built for the practical test requirement. It supports deposits, withdrawals, asynchronous deposit rebates, transaction history, and concurrency-safe balance updates.

## Features

- One wallet per user
- Deposit funds into a wallet
- Withdraw funds with overdraft protection
- Dispatch a queued 1% rebate job for every deposit
- Record deposit, withdrawal, and rebate transactions
- Retrieve wallet balance and transaction history
- Protect concurrent wallet updates with pessimistic row locking
- Feature tests for wallet operations and rebate behavior
- Optional MySQL process-based concurrency test

## API Endpoints

```http
GET /api/users/{userId}/wallet
GET /api/users/{userId}/wallet/transactions
POST /api/users/{userId}/wallet/deposit
POST /api/users/{userId}/wallet/withdraw
```

Example deposit request:

```json
{
  "amount": "100.00"
}
```

## Important Files

```text
routes/api.php
app/Http/Controllers/WalletController.php
app/Services/WalletService.php
app/Jobs/CalculateDepositRebateJob.php
app/Models/Wallet.php
app/Models/Transaction.php
database/migrations/2026_05_14_000000_create_wallets_table.php
database/migrations/2026_05_14_000001_create_transactions_table.php
tests/Feature/WalletApiTest.php
tests/Feature/WalletMysqlConcurrencyTest.php
CONCURRENCY_HANDLING.md
```

## Setup

```bash
composer install
php artisan key:generate
php artisan migrate
```

Run the API:

```bash
php artisan serve
```

Run the queue worker for rebate jobs:

```bash
php artisan queue:work
```

## Testing

```bash
php artisan test
```

The MySQL concurrency test is skipped unless `DB_CONNECTION=mysql`, because SQLite does not provide the same row-level locking behavior as MySQL/InnoDB.

## Concurrency Documentation

See [CONCURRENCY_HANDLING.md](CONCURRENCY_HANDLING.md) for the full explanation of pessimistic locking, `DB::transaction()`, `lockForUpdate()`, deposit/withdraw/rebate race conditions, and the concurrency testing strategy.
