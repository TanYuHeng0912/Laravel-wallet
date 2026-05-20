<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet System Test Page</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --surface: #ffffff;
            --surface-2: #eef3f8;
            --text: #15202b;
            --muted: #657386;
            --line: #d8e0ea;
            --primary: #146c63;
            --primary-dark: #0d514a;
            --danger: #b42318;
            --danger-bg: #fff0ed;
            --success: #0f7a4f;
            --success-bg: #eaf8f0;
            --shadow: 0 12px 30px rgba(21, 32, 43, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        a {
            color: var(--primary);
        }

        .shell {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0;
        }

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
        }

        .eyebrow {
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 32px;
            line-height: 1.2;
            margin: 0;
        }

        .user-switch {
            display: flex;
            align-items: end;
            gap: 10px;
            flex-wrap: wrap;
        }

        label {
            display: block;
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            font: inherit;
            padding: 10px 12px;
        }

        input:focus,
        button:focus {
            outline: 3px solid rgba(20, 108, 99, 0.22);
            outline-offset: 2px;
        }

        button {
            min-height: 44px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            padding: 10px 16px;
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: var(--danger);
            color: #ffffff;
        }

        .btn-muted {
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--line);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1.25fr;
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 22px;
        }

        .balance {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 20px;
        }

        .balance strong {
            display: block;
            font-size: 40px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .wallet-id {
            color: var(--muted);
            font-size: 14px;
            margin-top: 8px;
        }

        .forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .action-form {
            border-top: 1px solid var(--line);
            padding-top: 18px;
        }

        .action-form h2,
        .transactions h2 {
            font-size: 18px;
            margin: 0 0 12px;
        }

        .field-row {
            display: grid;
            gap: 10px;
        }

        .notice,
        .error {
            border-radius: 8px;
            margin-bottom: 18px;
            padding: 12px 14px;
        }

        .notice {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid rgba(15, 122, 79, 0.22);
        }

        .error {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(180, 35, 24, 0.22);
        }

        .hint {
            color: var(--muted);
            font-size: 14px;
            margin: 12px 0 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 12px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
        }

        td.amount {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 9px;
        }

        .badge-deposit,
        .badge-rebate {
            background: var(--success-bg);
            color: var(--success);
        }

        .badge-withdrawal {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .empty {
            color: var(--muted);
            margin: 0;
            padding: 24px 0 4px;
        }

        @media (max-width: 860px) {
            .topbar,
            .grid,
            .forms {
                grid-template-columns: 1fr;
            }

            .topbar {
                display: grid;
            }

            .user-switch {
                align-items: stretch;
            }

            .user-switch > div {
                flex: 1 1 180px;
            }
        }

        @media (max-width: 560px) {
            .shell {
                width: min(100% - 20px, 1120px);
                padding: 20px 0;
            }

            h1 {
                font-size: 26px;
            }

            .balance {
                display: block;
            }

            .balance strong {
                font-size: 34px;
                margin-top: 8px;
            }

            th:nth-child(4),
            td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Laravel Wallet System</p>
                <h1>Wallet System</h1>
            </div>

            <form class="user-switch" method="GET" action="{{ route('wallet.page') }}">
                <div>
                    <label for="user_id">Test User ID</label>
                    <input id="user_id" name="user_id" type="number" min="1" step="1" value="{{ $userId }}" required>
                </div>
                <button class="btn-muted" type="submit">Switch User</button>
            </form>
        </header>

        @if (session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="grid">
            <div class="panel">
                <div class="balance">
                    <div>
                        <div class="eyebrow">Current Balance</div>
                        <div class="wallet-id">Wallet #{{ $wallet->id }} · User #{{ $wallet->user_id }}</div>
                    </div>
                    <strong>RM {{ $wallet->balance }}</strong>
                </div>

                <div class="forms">
                    <form class="action-form" method="POST" action="{{ route('wallet.deposit', ['userId' => $userId]) }}">
                        @csrf
                        <h2>Deposit</h2>
                        <div class="field-row">
                            <div>
                                <label for="deposit_amount">Amount</label>
                                <input id="deposit_amount" name="amount" type="number" min="0.01" step="0.01" placeholder="100.00" required>
                            </div>
                            <button class="btn-primary" type="submit">Deposit Funds</button>
                        </div>
                        <p class="hint">A 1% rebate job is queued after deposit.</p>
                    </form>

                    <form class="action-form" method="POST" action="{{ route('wallet.withdraw', ['userId' => $userId]) }}">
                        @csrf
                        <h2>Withdraw</h2>
                        <div class="field-row">
                            <div>
                                <label for="withdraw_amount">Amount</label>
                                <input id="withdraw_amount" name="amount" type="number" min="0.01" step="0.01" placeholder="25.00" required>
                            </div>
                            <button class="btn-danger" type="submit">Withdraw Funds</button>
                        </div>
                        <p class="hint">The wallet cannot be overdrawn.</p>
                    </form>
                </div>
            </div>

            <div class="panel transactions">
                <h2>Transaction History</h2>

                @if ($transactions->isEmpty())
                    <p class="empty">No transactions yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th style="text-align: right;">Amount</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td>#{{ $transaction->id }}</td>
                                    <td>
                                        <span class="badge badge-{{ $transaction->type }}">
                                            {{ ucfirst($transaction->type) }}
                                        </span>
                                    </td>
                                    <td class="amount">RM {{ $transaction->amount }}</td>
                                    <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
