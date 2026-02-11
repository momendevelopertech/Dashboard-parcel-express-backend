<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
    
class MerchantWalletController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            return sendResponse('Wallet not found', [], false, [], 404);
        }

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $typeFilter = $request->query('type', 'all');
        $perPage = (int) $request->query('per_page', 20);

        $txQuery = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc');

        if ($startDate) {
            $txQuery->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $txQuery->where('created_at', '<=', $endDate . ' 23:59:59');
        }
        if ($typeFilter !== 'all') {
            $txQuery->where('type', $typeFilter);
        }

        $transactions = $txQuery->paginate($perPage);

        return sendResponse('Wallet retrieved successfully', [
            'balance' => $wallet->balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * POST /merchant/wallet/deposit
     * body: { amount: number, description?: string }
     */
    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }

            $wallet->balance = bcadd((string) $wallet->balance, (string) $request->amount, 2);
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Merchant deposit',
            ]);

            DB::commit();

            return sendResponse('Deposit successful', [
                'balance' => $wallet->balance,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse($e->getMessage(), [], false, [], 500);
        }
    }

    /**
     * POST /merchant/wallet/withdraw
     * body: { amount: number, description?: string }
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }

            if (bccomp((string) $wallet->balance, (string) $request->amount, 2) < 0) {
                throw new \Exception('Insufficient balance');
            }

            $wallet->balance = bcsub((string) $wallet->balance, (string) $request->amount, 2);
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Merchant withdrawal',
            ]);

            DB::commit();

            return sendResponse('Withdrawal successful', [
                'balance' => $wallet->balance,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse($e->getMessage(), [], false, [], 500);
        }
    }
}
