<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function show($type, $id, Request $request)
    {
        if ($type !== 'merchant') {
            return sendResponse('Invalid account type', [], false, [], 400);
        }

        $startDate = $request->query('startDate', null);
        $endDate = $request->query('endDate', null);
        $typeFilter = $request->query('type', 'all');

        $wallet = Wallet::whereHas('user', function ($query) use ($id) {
            $query->where('id', $id);
        })->with([
                    'transactions' => function ($query) use ($startDate, $endDate, $typeFilter) {
                        $query->orderBy('created_at', 'desc');

                        if ($startDate) {
                            $query->where('created_at', '>=', $startDate . ' 00:00:00');
                        }

                        if ($endDate) {
                            $query->where('created_at', '<=', $endDate . ' 23:59:59');
                        }

                        if ($typeFilter !== 'all') {
                            $query->where('type', $typeFilter);
                        }
                    }
                ])->first();

        if (!$wallet) {
            return sendResponse('Wallet not found', [], false, [], 404);
        }

        return sendResponse('Wallet retrieved successfully', [
            'balance' => $wallet->balance,
            'transactions' => $wallet->transactions
        ]);
    }

    public function deposit($type, $id, Request $request)
    {
        if ($type !== 'merchant') {
            return response()->json(['error' => 'Invalid account type'], 400);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $wallet = Wallet::whereHas('user', function ($query) use ($id) {
                $query->where('id', $id);
            })->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }

            $wallet->balance += $request->amount;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Manual deposit'
            ]);

            DB::commit();

            return sendResponse('Deposit successful', [
                'balance' => $wallet->balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse($e->getMessage(), [], false, [], 500);
        }
    }

    public function withdraw($type, $id, Request $request)
    {
        if ($type !== 'merchant') {
            return response()->json(['error' => 'Invalid account type'], 400);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $wallet = Wallet::whereHas('user', function ($query) use ($id) {
                $query->where('id', $id);
            })->first();

            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }

            if ($wallet->balance < $request->amount) {
                throw new \Exception('Insufficient balance');
            }

            $wallet->balance -= $request->amount;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'description' => $request->description ?? 'Manual withdrawal'
            ]);

            DB::commit();

            return sendResponse('Withdrawal successful', [
                'balance' => $wallet->balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse($e->getMessage(), [], false, [], 500);
        }
    }
}
