<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DriverInvoice;
use Illuminate\Http\Request;

class DriverInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $query = DriverInvoice::with(['driver', 'createdBy']);
       
        if ($request->filled('search')) {
            $search=$request->search;
            $query->whereHas('driver', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $like = '%' . $search . '%';

                    $qq->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('username', 'like', $like);
                });
            });
        }

        // ✅ Filter by exact amount
        if ($request->filled('amount')) {
            $query->where('amount', $request->amount);
        }

        // ✅ Filter by minimum amount
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        // ✅ Filter by maximum amount
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        // ✅ Filter by created_by
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // ✅ Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // ✅ Sorting
        $query->orderBy(
            $request->get('sort_by', 'created_at'),
            $request->get('sort_order', 'desc')
        );
         
        // ✅ Pagination
        $invoices = $query->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }
    public function show(Request $request, $driverId)
    {
        $query = DriverInvoice::with(['driver', 'createdBy']);

        if (isset($driverId)) {
            $query->where('driver_id', $driverId);
        }

        // ✅ Filter by exact amount
        if ($request->filled('amount')) {
            $query->where('amount', $request->amount);
        }

        // ✅ Filter by minimum amount
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        // ✅ Filter by maximum amount
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        // ✅ Filter by created_by
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // ✅ Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // ✅ Sorting
        $query->orderBy(
            $request->get('sort_by', 'created_at'),
            $request->get('sort_order', 'desc')
        );

        // ✅ Pagination
        $invoices = $query->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

}