<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletAmountRequest;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletPageController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function show(Request $request, ?int $userId = null): View|RedirectResponse
    {
        /*
         * Let testers switch users from the page without typing URLs manually.
         * /wallet defaults to user 1, while the user_id form redirects to the
         * canonical /wallet/{userId} URL.
         */
        if ($request->filled('user_id')) {
            return redirect()->route('wallet.page', [
                'userId' => (int) $request->query('user_id'),
            ]);
        }

        $userId ??= 1;

        return view('wallet.index', [
            'userId' => $userId,
            'wallet' => $this->walletService->getWalletForUser($userId),
            'transactions' => $this->walletService->getTransactionsForUser($userId),
        ]);
    }

    public function deposit(WalletAmountRequest $request, int $userId): RedirectResponse
    {
        $this->walletService->deposit($userId, (string) $request->validated('amount'));

        return redirect()
            ->route('wallet.page', ['userId' => $userId])
            ->with('status', 'Deposit accepted. The rebate will appear after the queue worker processes the job.');
    }

    public function withdraw(WalletAmountRequest $request, int $userId): RedirectResponse
    {
        try {
            $this->walletService->withdraw($userId, (string) $request->validated('amount'));
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->withErrors(['withdraw' => $exception->getMessage()]);
        }

        return redirect()
            ->route('wallet.page', ['userId' => $userId])
            ->with('status', 'Withdrawal completed.');
    }
}
