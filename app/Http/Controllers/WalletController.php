<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletAmountRequest;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function show(int $userId): JsonResponse
    {
        $wallet = $this->walletService->getWalletForUser($userId);

        return response()->json([
            'data' => [
                'id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'balance' => $wallet->balance,
                'created_at' => $wallet->created_at,
                'updated_at' => $wallet->updated_at,
            ],
        ]);
    }

    public function transactions(int $userId): JsonResponse
    {
        return response()->json([
            'data' => $this->walletService->getTransactionsForUser($userId)->map(fn ($transaction): array => [
                'id' => $transaction->id,
                'wallet_id' => $transaction->wallet_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'created_at' => $transaction->created_at,
            ]),
        ]);
    }

    public function deposit(WalletAmountRequest $request, int $userId): JsonResponse
    {
        $wallet = $this->walletService->deposit($userId, (string) $request->validated('amount'));

        return response()->json([
            'message' => 'Deposit accepted. Rebate will be credited by the queue worker.',
            'data' => [
                'id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'balance' => $wallet->balance,
            ],
        ], 202);
    }

    public function withdraw(WalletAmountRequest $request, int $userId): JsonResponse
    {
        try {
            $wallet = $this->walletService->withdraw($userId, (string) $request->validated('amount'));
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Withdrawal completed.',
            'data' => [
                'id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'balance' => $wallet->balance,
            ],
        ]);
    }
}
