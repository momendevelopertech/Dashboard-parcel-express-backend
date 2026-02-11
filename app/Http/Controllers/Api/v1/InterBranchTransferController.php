<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\FacilityAccount;
use App\Models\InterBranchTransfer;
use App\Models\InterBranchTransferShipment;
use App\Models\WarehouseTransaction;
use App\Models\Station;
use App\Models\Hub;
use App\Models\Shipment;
// use App\Models\TransferTaskShipment;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;
class InterBranchTransferController extends Controller
{
    // public function index(Request $r)
    // {
    //     Gate::authorize('Inter Branch Transfer access');

    //     $q = InterBranchTransfer::query()->with(['creator', 'approver'])->orderByDesc('id');

    //     if ($term = trim((string) $r->query('query', ''))) {
    //         $q->where('code', 'like', "%{$term}%")
    //             ->orWhere('notes', 'like', "%{$term}%");
    //         $items = $q->limit(200)->get()->map(fn($t) => $this->presentRow($t));
    //         return sendResponse('OK', $items);
    //     }

    //     $page = $q->paginate(10);
    //     $page->getCollection()->transform(fn($t) => $this->presentRow($t));
    //     return sendResponse('OK', $page);
    // }

    // // GET /inter-branch-transfers/{transfer}
    // public function show(InterBranchTransfer $transfer)
    // {
    //     Gate::authorize('Inter Branch Transfer access');

    //     $row = $this->presentRow($transfer->load(['shipments', 'creator', 'approver']));
    //     $row['shipments'] = $transfer->shipments()->get(['shipment_tracking_no', 'amount']);
    //     return sendResponse('OK', $row);
    // }

    // // POST /inter-branch-transfers   (create pending)
    // public function store(Request $r)
    // {
    //     Gate::authorize('Inter Branch Transfer access');

    //     $validator = Validator::make($r->all(), [
    //         'from_type' => ['required', 'string'],
    //         'from_id' => ['required', 'integer', 'min:1'],
    //         'to_type' => ['required', 'string'],            // ← شلنا different:from_type
    //         'to_id' => ['required', 'integer', 'min:1', 'different:from_id'],
    //         'amount' => ['required', 'numeric', 'min:0.01'],
    //         'notes' => ['nullable', 'string', 'max:2000'],
    //         'shipments' => ['array'],
    //         'shipments.*' => ['string'],
    //         'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:8192'],
    //     ]);

    //     // ضمان إن (type,id) مش نفس الكيان في الناحيتين
    //     $validator->after(function ($v) use ($r) {
    //         if (
    //             $r->input('from_type') === $r->input('to_type') &&
    //             (int) $r->input('from_id') === (int) $r->input('to_id')
    //         ) {
    //             $v->errors()->add('to_id', 'Destination must be a different facility than source.');
    //         }
    //     });

    //     $data = $validator->validate();

    //     // خزن الإيصال لو موجود
    //     $receiptPath = null;
    //     if ($r->hasFile('receipt')) {
    //         $receiptPath = $r->file('receipt')->store('inter_transfers', 'public');
    //     }

    //     $code = 'TRF-' . now()->format('ymd') . '-' . strtoupper(str()->random(4));

    //     DB::beginTransaction();
    //     try {
    //         $t = InterBranchTransfer::create([
    //             'code' => $code,
    //             'from_type' => $data['from_type'],
    //             'from_id' => $data['from_id'],
    //             'to_type' => $data['to_type'],
    //             'to_id' => $data['to_id'],
    //             'amount' => $data['amount'],
    //             'shipments_count' => isset($data['shipments']) ? count($data['shipments']) : 0,
    //             'status' => 'pending',
    //             'notes' => $data['notes'] ?? null,
    //             'created_by' => Auth::id(),
    //             'receipt_path' => $receiptPath,
    //         ]);

    //         if (!empty($data['shipments'])) {
    //             $rows = [];
    //             foreach ($data['shipments'] as $trk) {
    //                 $rows[] = [
    //                     'transfer_id' => $t->id,
    //                     'shipment_tracking_no' => $trk,
    //                     'amount' => 0, // اختياري لو هتوزّع المبلغ
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];
    //             }
    //             InterBranchTransferShipment::insert($rows);
    //         }

    //         DB::commit();
    //         return sendResponse('Transfer created (pending).', $this->presentRow($t));
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         if ($receiptPath)
    //             Storage::disk('public')->delete($receiptPath);
    //         return sendResponse('Failed.', [], false, [$e->getMessage()], 500);
    //     }
    // }

    // // POST /inter-branch-transfers/approve/{transfer}
    // public function approve(Request $r, InterBranchTransfer $transfer)
    // {
    //     Gate::authorize('Inter Branch Transfer approve');

    //     if ($transfer->status !== 'pending') {
    //         return sendResponse('Only pending transfers can be approved.', [], false, [], 422);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         // اقفل الحسابين
    //         $fromAcc = Facility::lockAccount($transfer->from_type, $transfer->from_id);
    //         $toAcc = Facility::lockAccount($transfer->to_type, $transfer->to_id);

    //         $fromName = Facility::name($transfer->from_type, $transfer->from_id);
    //         $toName = Facility::name($transfer->to_type, $transfer->to_id);

    //         // تأكد من الرصيد (لو تحب تسمح بالسالب احذف الشرط)
    //         if ((float) $fromAcc->balance < (float) $transfer->amount) {
    //             return sendResponse('Insufficient balance on source facility.', [], false, [], 422);
    //         }

    //         // حدّث الأرصدة
    //         DB::table('facility_accounts')
    //             ->where('owner_type', $transfer->from_type)->where('owner_id', $transfer->from_id)
    //             ->update(['balance' => DB::raw('balance - ' . (float) $transfer->amount), 'updated_at' => now()]);

    //         DB::table('facility_accounts')
    //             ->where('owner_type', $transfer->to_type)->where('owner_id', $transfer->to_id)
    //             ->update(['balance' => DB::raw('balance + ' . (float) $transfer->amount), 'updated_at' => now()]);

    //         // Ledger: warehouse_transactions
    //         DB::table('warehouse_transactions')->insert([
    //             [
    //                 'warehouse_type' => $transfer->from_type,
    //                 'warehouse_id' => $transfer->from_id,
    //                 'type' => 'cash_out',
    //                 'amount' => $transfer->amount,
    //                 'reference' => $transfer->code,
    //                 'description' => "IBT to {$toName}",
    //                 'created_by' => Auth::id(),
    //                 'created_at' => now(),
    //             ],
    //             [
    //                 'warehouse_type' => $transfer->to_type,
    //                 'warehouse_id' => $transfer->to_id,
    //                 'type' => 'cash_in',
    //                 'amount' => $transfer->amount,
    //                 'reference' => $transfer->code,
    //                 'description' => "IBT from {$fromName}",
    //                 'created_by' => Auth::id(),
    //                 'created_at' => now(),
    //             ],
    //         ]);

    //         // Ledger: transactions (قيدين)
    //         Transaction::create([
    //             'from_type' => $transfer->from_type,
    //             'from_id' => $transfer->from_id,
    //             'to_type' => $transfer->to_type,
    //             'to_id' => $transfer->to_id,
    //             'amount' => $transfer->amount,
    //             'type' => 'transfer_out',
    //             'reference' => $transfer->code,
    //             'description' => "Inter-branch transfer to {$toName}",
    //             'receipt_path' => $transfer->receipt_path,
    //             'receipt_uploaded_by' => $transfer->receipt_path ? Auth::id() : null,
    //             'receipt_uploaded_at' => $transfer->receipt_path ? now() : null,
    //             'created_by' => Auth::id(),
    //             'warehouse_id' => $transfer->from_id,
    //         ]);

    //         Transaction::create([
    //             'from_type' => $transfer->from_type,
    //             'from_id' => $transfer->from_id,
    //             'to_type' => $transfer->to_type,
    //             'to_id' => $transfer->to_id,
    //             'amount' => $transfer->amount,
    //             'type' => 'transfer_in',
    //             'reference' => $transfer->code,
    //             'description' => "Inter-branch transfer from {$fromName}",
    //             'receipt_path' => $transfer->receipt_path,
    //             'receipt_uploaded_by' => $transfer->receipt_path ? Auth::id() : null,
    //             'receipt_uploaded_at' => $transfer->receipt_path ? now() : null,
    //             'created_by' => Auth::id(),
    //             'warehouse_id' => $transfer->to_id,
    //         ]);

    //         // قفل التحويل
    //         $transfer->status = 'approved';
    //         $transfer->approved_by = Auth::id();
    //         $transfer->approved_at = now();
    //         $transfer->save();

    //         DB::commit();
    //         return sendResponse('Transfer approved.', $this->presentRow($transfer));
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return sendResponse('Failed.', [], false, [$e->getMessage()], 500);
    //     }
    // }

    // // POST /inter-branch-transfers/reject/{transfer}
    // public function reject(Request $r, InterBranchTransfer $transfer)
    // {
    //     Gate::authorize('Inter Branch Transfer reject');

    //     if ($transfer->status !== 'pending') {
    //         return sendResponse('Only pending transfers can be rejected.', [], false, [], 422);
    //     }

    //     $data = $r->validate(['comment' => 'required|string|max:2000']);

    //     $transfer->status = 'rejected';
    //     $transfer->rejected_by = Auth::id();
    //     $transfer->rejected_at = now();
    //     $transfer->reject_comment = $data['comment'];
    //     $transfer->save();

    //     return sendResponse('Transfer rejected.', $this->presentRow($transfer));
    // }

    // // POST /inter-branch-transfers/export
    // public function export(Request $r): StreamedResponse
    // {
    //     Gate::authorize('Inter Branch Transfer export');

    //     $term = trim((string) $r->input('query', ''));
    //     $rows = InterBranchTransfer::query()
    //         ->when($term !== '', fn($q) => $q->where('code', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%"))
    //         ->orderByDesc('id')
    //         ->limit(2000)
    //         ->get();

    //     $filename = 'transfers_' . now()->format('Ymd_His') . '.csv';
    //     $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$filename}"];

    //     return response()->stream(function () use ($rows) {
    //         $out = fopen('php://output', 'w');
    //         fputcsv($out, ['Date', 'Code', 'From', 'To', 'Amount', 'Shipments', 'Status', 'Created By', 'Approved By']);
    //         foreach ($rows as $t) {
    //             fputcsv($out, [
    //                 $t->created_at?->toDateTimeString(),
    //                 $t->code,
    //                 Facility::name($t->from_type, $t->from_id) . " (#{$t->from_id})",
    //                 Facility::name($t->to_type, $t->to_id) . " (#{$t->to_id})",
    //                 number_format((float) $t->amount, 3, '.', ''),
    //                 $t->shipments_count,
    //                 $t->status,
    //                 optional($t->creator)->name,
    //                 optional($t->approver)->name,
    //             ]);
    //         }
    //         fclose($out);
    //     }, 200, $headers);
    // }

    // // ===== Presentation helper (يشبه الفورمات اللي واجهتك عايزاه) =====
    // private function presentRow(InterBranchTransfer $t): array
    // {
    //     return [
    //         'id' => $t->id,
    //         'date' => $t->created_at?->toDateTimeString(),
    //         'code' => $t->code,
    //         'from' => [
    //             'type' => class_basename($t->from_type),
    //             'id' => $t->from_id,
    //             'name' => Facility::name($t->from_type, $t->from_id),
    //         ],
    //         'to' => [
    //             'type' => class_basename($t->to_type),
    //             'id' => $t->to_id,
    //             'name' => Facility::name($t->to_type, $t->to_id),
    //         ],
    //         'amount' => (float) $t->amount,
    //         'shipments_count' => (int) $t->shipments_count,
    //         'status' => $t->status,
    //         'created_by' => $t->creator?->only(['id', 'name']),
    //         'approved_by' => $t->approver?->only(['id', 'name']),
    //         'receipt_url' => $t->receipt_path ? Storage::disk('public')->url($t->receipt_path) : null,
    //         'notes' => $t->notes,
    //     ];
    // }

    private function presentRow(\App\Models\InterBranchTransfer $t): array
    {
        return [
            'id' => $t->id,
            'date' => $t->created_at?->toDateTimeString(),
            'code' => $t->code,
            'from' => [
                'type' => class_basename($t->from_type),
                'id' => $t->from_id,
                'name' => $this->labelOf($t->from_type, $t->from_id),
            ],
            'to' => [
                'type' => class_basename($t->to_type),
                'id' => $t->to_id,
                'name' => $this->labelOf($t->to_type, $t->to_id),
            ],
            'amount' => (float) $t->amount,
            'shipments_count' => (int) $t->shipments_count,
            'status' => $t->status,
            'created_by' => $t->creator?->only(['id', 'name']),
            'approved_by' => $t->approver?->only(['id', 'name']),
            'notes' => $t->notes,
        ];
    }

    public function options()
    {
        $hubs = \App\Models\Hub::query()->select('id', 'name')->orderBy('name')->get();
        $stations = \App\Models\Station::query()->select('id', 'name')->orderBy('name')->get();

        // لو عايز تمنع الوجهة = نفس المصدر، ابعتها للفرونت وهو يستبعد
        return sendResponse('ok', [
            'hubs' => $hubs,
            'stations' => $stations,
        ], []);
    }

    private function normalizeWarehouseType(string $type): string
    {
        // نظّف المسافات والباك-سلاش الزائد
        $t = trim($type, " \\ \t\n\r\0\x0B");
        $tLower = strtolower($t);

        // أي صيغة فيها "station" ترجع Station::class
        if (str_ends_with($tLower, 'station') || str_contains($tLower, 'models\\station') || str_contains($tLower, 'station')) {
            return Station::class;
        }
        // وأي صيغة فيها "hub" ترجع Hub::class
        if (str_ends_with($tLower, 'hub') || str_contains($tLower, 'models\\hub') || str_contains($tLower, 'hub')) {
            return Hub::class;
        }

        throw new HttpException(422, "Unsupported warehouse type: {$type}");
    }

    private function resolveWarehouse(string $type, int $id)
    {
        $fqcn = $this->normalizeWarehouseType($type);

        // مهم: بدون أي Global Scopes
        $model = app($fqcn)->newQueryWithoutScopes()->find($id);
        if (!$model) {
            \Log::warning('warehouse_not_found', ['type' => $fqcn, 'id' => $id]);
            abort(404, "Warehouse not found ({$fqcn} #{$id})");
        }

        // تأكد من وجود حساب facility_accounts
        FacilityAccount::firstOrCreate(
            ['owner_type' => $fqcn, 'owner_id' => $id],
            ['balance' => 0]
        );

        return $model;
    }

    public function index(Request $r)
    {
        $q = InterBranchTransfer::query()
            ->with(['creator:id,name', 'approver:id,name'])
            ->orderBy('id', 'desc');

        if ($search = trim((string) $r->input('query', ''))) {
            $rows = $q->where('code', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")
                ->limit(200)->get();
            $items = $rows->map(fn($t) => $this->presentRow($t));
            return sendResponse('Transfers list.', $items);
        }

        $page = $q->paginate(10);
        $page->getCollection()->transform(fn($t) => $this->presentRow($t));
        return sendResponse('Transfers list.', $page);
    }


    public function show(int $id)
    {
        $t = InterBranchTransfer::with(['creator:id,name', 'approver:id,name', 'shipments'])
            ->findOrFail($id);
        return sendResponse('Transfer details.', $t);
    }


    public function store(Request $r)
    {
        $data = $r->validate([
            'from_type' => ['required', 'string'],
            'from_id' => ['required', 'integer', 'min:1'],
            'to_type' => ['required', 'string'],
            'to_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipments' => ['nullable', 'array'],
            'shipments.*' => ['string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:8192'],
            'code' => ['nullable', 'string', 'max:64'],
        ]);

        // ثبّت الأنواع + تحقُّق “نفس الكيان” (زي ما عندك)
        $fromType = $this->normalizeWarehouseType($data['from_type']);
        $toType = $this->normalizeWarehouseType($data['to_type']);
        $fromId = (int) $data['from_id'];
        $toId = (int) $data['to_id'];

        if ($fromType === $toType && $fromId === $toId) {
            throw ValidationException::withMessages([
                'to_id' => 'From and To cannot be the same warehouse.',
            ]);
        }

        // تأكد من وجود الطرفين + إنشاء FacilityAccount لو ناقص
        $this->resolveWarehouse($fromType, $fromId);
        $this->resolveWarehouse($toType, $toId);

        // ✅ اقرأ code بأمان
        $code = $r->input('code'); // أو ($data['code'] ?? null)

        if ($code) {
            // لو المستخدم بعته، تأكد إنه غير مكرر
            if (InterBranchTransfer::where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'This code is already used. Please use a different one.',
                ]);
            }
        } else {
            // لو ماتبعتش، ولّد واحد فريد
            $base = 'IBT-' . now()->format('ymd');
            do {
                // $code = $base . '-' . Str::upper(Str::random(4));
                $code = $base . '-' . strtoupper(str()->random(4));
            } while (InterBranchTransfer::where('code', $code)->exists());
        }

        // تحقق من الرصيد المتاح لدى المصدر قبل الإنشاء
        $fromAcc = FacilityAccount::firstOrCreate([
            'owner_type' => $fromType,
            'owner_id' => $fromId,
        ], [ 'balance' => 0 ]);
        if ((float)$data['amount'] > (float)$fromAcc->balance + 1e-6) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient balance. Max available is ' . number_format((float)$fromAcc->balance, 3, '.', ''),
            ]);
        }

        $receiptPath = $r->hasFile('receipt')
            ? uploadFile($r->file('receipt'), 'public/ibt_receipts')
            : null;


        DB::beginTransaction();
        try {
            $shipments = $data['shipments'] ?? [];
            $t = InterBranchTransfer::create([
                'code' => $code,
                'from_type' => $fromType,
                'from_id' => $fromId,
                'to_type' => $toType,
                'to_id' => $toId,
                'amount' => (float) $data['amount'],
                'shipments_count' => is_array($shipments) ? count($shipments) : 0,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'receipt_path' => $receiptPath,
            ]);

            if (is_array($shipments) && !empty($shipments)) {
                foreach ($shipments as $trk) {
                    InterBranchTransferShipment::create([
                        'transfer_id' => $t->id,
                        'shipment_tracking_no' => $trk,
                        'amount' => 0,
                    ]);
                }
            }

            DB::commit();

            return sendResponse('Transfer created (pending).', [
                'id' => $t->id,
                'code' => $t->code,
                'status' => $t->status,
                'amount' => (float) $t->amount,
                'shipments_count' => (int) $t->shipments_count,
                'from' => [
                    'type' => class_basename($fromType),
                    'id' => $fromId,
                    'name' => $this->labelOf($fromType, $fromId),
                ],
                'to' => [
                    'type' => class_basename($toType),
                    'id' => $toId,
                    'name' => $this->labelOf($toType, $toId),
                ],
                'receipt_url' => $receiptPath ? \Storage::disk('public')->url($receiptPath) : null,
                'created_by' => Auth::user()?->only(['id', 'name']),
                'created_at' => $t->created_at?->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($receiptPath) {
                try {
                    \Storage::disk('public')->delete($receiptPath);
                } catch (\Throwable $ignored) {
                }
            }
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }
    public function storeFromShipment(Request $r)
    {
        $data = $r->validate([
            'tracking_no' => ['required', 'string', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:8192'],
        ]);

        // منشأة المستخدم الحالي (المكان اللي بيحوّل "منه")
        $fromType = Auth::user()->owner_type;
        $fromId = Auth::user()->owner_id;

        $shipment = Shipment::with('shipment_delivery')
            ->where('tracking_no', $data['tracking_no'])
            ->first();

        if (!$shipment) {
            return sendResponse('Shipment not found for the given tracking number.', [], false, [], 404);
        }



        $isPaidShipment = strtoupper((string) $shipment->payment_type) === 'PAID';
        if ($isPaidShipment) {
            return sendResponse('This shipment is not COD.', [], false, [], 422);
        }
        // المبلغ الذي يجب تحويله بناء على قواعد التحصيل
        $collectible = (float) $shipment->getDriverCollectibleAmount();
        if ($collectible <= 0) {
            return sendResponse('This shipment has no collectible amount.', [], false, [], 422);
        }

        $toType = $shipment->origin_owner_type ?? null;
        $toId = $shipment->origin_owner_id ?? null;

        // لو معندكش origin_*، جرّب تقرأ من جدول transfer-task (إن وجد)

        if (!$toType || !$toId) {
            $row = DB::table('transfer_task_shipments as tto')
                ->join('transfer_tasks as tt', 'tto.transfer_task_id', '=', 'tt.id')
                ->leftJoin('transfer_destinations as td', 'td.transfer_task_id', '=', 'tt.id')
                ->where('tto.shipment_tracking_no', $shipment->tracking_no)
                ->select(
                    'tt.origin_type',        // المصدر
                    'tt.origin_id',
                    'td.destination_type',   // الوجهة
                    'td.destination_id'
                )
                ->latest('tto.id')
                ->first();

            if ($row) {
                // لو أنا واقف عند الوجهة الحالية -> ارجع الفلوس للمصدر، غير كده ابعتها للوجهة
                $isAtDestination = ($row->destination_type === $fromType) && ((int) $row->destination_id === (int) $fromId);
                if ($isAtDestination) {
                    $toType = $row->origin_type;
                    $toId = (int) $row->origin_id;
                } else {
                    $toType = $row->destination_type;
                    $toId = (int) $row->destination_id;
                }
            }
        }

        if (!$toType || !$toId) {
            return sendResponse('Cannot resolve destination facility for this shipment.', [], false, [], 422);
        }

        if ($toType === $fromType && (int) $toId === (int) $fromId) {
            return sendResponse('Source and destination are the same facility.', [], false, [], 422);
        }

        $this->resolveWarehouse($fromType, (int) $fromId);
        $this->resolveWarehouse($toType, (int) $toId);

        // اقترح التحويل = collectible، وامنع تجاوز هذا الحد
        $amount = isset($data['amount']) ? (float) $data['amount'] : (float) $collectible;
        if ($amount > $collectible + 1e-6) {
            return sendResponse('Amount exceeds allowed collectible for this shipment.', [
                'max_collectible' => (float) $collectible,
            ], false, [], 422);
        }
        if ($amount <= 0.0) {
            return sendResponse('Amount must be greater than zero.', [], false, [], 422);
        }

        $dup = InterBranchTransferShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->whereHas('transfer', fn($q) => $q->whereIn('status', ['pending', 'approved']))
            ->exists();
        if ($dup) {
            return sendResponse('This shipment is already attached to a pending/approved transfer.', [], false, [], 422);
        }

        $receiptPath = $r->hasFile('receipt')
            ? uploadFile($r->file('receipt'), 'public/ibt_receipts')
            : null;

        $base = 'IBT-ORD-' . now()->format('ymd');
        do {
            $code = $base . '-' . strtoupper(str()->random(4));
        } while (InterBranchTransfer::where('code', $code)->exists());

        DB::beginTransaction();
        try {
            $t = InterBranchTransfer::create([
                'code' => $code,
                'from_type' => $fromType,
                'from_id' => $fromId,
                'to_type' => $toType,
                'to_id' => $toId,
                'amount' => $amount,
                'shipments_count' => 1,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'receipt_path' => $receiptPath,
            ]);

            InterBranchTransferShipment::create([
                'transfer_id' => $t->id,
                'shipment_id' => $shipment->id,
                'shipment_tracking_no' => $shipment->tracking_no,
                'amount' => $amount,
            ]);

            DB::commit();

            return sendResponse('Transfer created (pending).', $this->presentRow($t));
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($receiptPath) {
                try {
                    \Storage::disk('public')->delete($receiptPath);
                } catch (\Throwable $ignored) {
                }
            }
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }

    public function storeFromRunsheet(Request $r)
    {
        $data = $r->validate([
            'runsheet_id' => ['required', 'integer', 'min:1'],
            'to_type' => ['required', 'string'],
            'to_id' => ['required', 'integer', 'min:1'],
            'shipments' => ['required', 'array', 'min:1'], // [{shipment_id, cod}]
            'shipments.*.shipment_id' => ['required', 'integer', 'min:1'],
            'shipments.*.cod' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:8192'],
        ]);

        $fromType = auth()->user()->owner_type;
        $fromId = auth()->user()->owner_id;

        $this->resolveWarehouse($fromType, (int) $fromId);
        $toType = $this->normalizeWarehouseType($data['to_type']);
        $toId = (int) $data['to_id'];
        $this->resolveWarehouse($toType, $toId);

        if ($fromType === $toType && $fromId === $toId) {
            return sendResponse('Source and destination are the same facility.', [], false, [], 422);
        }

        // تحقّق من الأوردارات + منع التكرار
        $shipmentIds = collect($data['shipments'])->pluck('shipment_id')->unique()->values();
        $shipments = \App\Models\Shipment::whereIn('id', $shipmentIds)->get()->keyBy('id');
        if ($shipments->count() !== $shipmentIds->count()) {
            return sendResponse('One or more shipments not found.', [], false, [], 422);
        }

        // امنع تكرار تحويلات معلقة/معتمدة لنفس الأوردر
        $dups = \App\Models\InterBranchTransferShipment::whereIn('shipment_id', $shipmentIds)
            ->whereHas('transfer', fn($q) => $q->whereIn('status', ['pending', 'approved']))
            ->exists();
        if ($dups) {
            return sendResponse('Some shipments are already attached to pending/approved transfer.', [], false, [], 422);
        }

        $total = collect($data['shipments'])->sum('cod');
        if ($total <= 0) {
            return sendResponse('Total amount must be greater than zero.', [], false, [], 422);
        }

        $receiptPath = $r->hasFile('receipt') ?uploadFile($r->file('receipt'), 'public/ibt_receipts') : null;

        $base = 'IBT-RS-' . now()->format('ymd');
        do {
            $code = $base . '-' . strtoupper(str()->random(4));
        } while (\App\Models\InterBranchTransfer::where('code', $code)->exists());

        DB::beginTransaction();
        try {
            $t = \App\Models\InterBranchTransfer::create([
                'code' => $code,
                'from_type' => $fromType,
                'from_id' => $fromId,
                'to_type' => $toType,
                'to_id' => $toId,
                'amount' => (float) $total,
                'shipments_count' => $shipmentIds->count(),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'receipt_path' => $receiptPath,
            ]);

            // خزّن الأوردارات داخل سطور الشحن (مع عمود shipment_id)
            foreach ($data['shipments'] as $o) {
                \App\Models\InterBranchTransferShipment::create([
                    'transfer_id' => $t->id,
                    'shipment_id' => (int) $o['shipment_id'],
                    'shipment_tracking_no' => $shipments[$o['shipment_id']]->tracking_no ?? null,
                    'amount' => (float) $o['cod'],
                ]);
            }

            DB::commit();
            return sendResponse('Transfer created (pending).', [
                'id' => $t->id,
                'code' => $t->code,
                'status' => $t->status,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($receiptPath) {
                try {
                    \Storage::disk('public')->delete($receiptPath);
                } catch (\Throwable $ignored) {
                }
            }
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }



    public function approve(Request $r, int $id)
    {
        $t = InterBranchTransfer::where('status', 'pending')->findOrFail($id);

        DB::beginTransaction();
        try {
            $t->status = 'approved';
            $t->approved_by = Auth::id();
            $t->approved_at = now();
            $t->save();

            // from → transfer_out
            WarehouseTransaction::create([
                'warehouse_type' => $t->from_type,
                'warehouse_id' => $t->from_id,
                'type' => 'transfer_out',
                'amount' => (float) $t->amount,
                'reference' => $t->code,
                'description' => 'Transfer to ' . $this->labelOf($t->to_type, $t->to_id),
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);

            // to → transfer_in
            WarehouseTransaction::create([
                'warehouse_type' => $t->to_type,
                'warehouse_id' => $t->to_id,
                'type' => 'transfer_in',
                'amount' => (float) $t->amount,
                'reference' => $t->code,
                'description' => 'Transfer from ' . $this->labelOf($t->from_type, $t->from_id),
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);

            DB::commit();
            return sendResponse('Transfer approved.', $t);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }

    public function reject(Request $r, int $id)
    {
        $data = $r->validate(['comment' => ['required', 'string', 'max:2000']]);

        $t = InterBranchTransfer::where('status', 'pending')->findOrFail($id);
        $t->status = 'rejected';
        $t->rejected_by = Auth::id();
        $t->rejected_at = now();
        $t->reject_comment = $data['comment'];
        $t->save();

        return sendResponse('Transfer rejected.', $t);
    }

    // ------ helpers ------

    private function assertWarehouseExists(string $type, int $id): void
    {
        $m = match ($type) {
            Station::class => Station::class,
            Hub::class => Hub::class,
            default => null,
        };
        abort_if(!$m || !$m::whereKey($id)->exists(), 422, 'Warehouse not found (' . $type . ' #' . $id . ')');
    }

    private function labelOf(string $type, int $id): string
    {
        $m = match ($type) {
            Station::class => Station::class,
            Hub::class => Hub::class,
            default => null,
        };
        if (!$m)
            return $type . '#' . $id;
        $row = $m::find($id);
        return ($row?->name ?: class_basename($type)) . ' #' . $id;
    }
}
