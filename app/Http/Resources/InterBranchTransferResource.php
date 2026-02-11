<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
class InterBranchTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        // 1) لو Paginator -> رجّع data + links (كـ Array)
        if ($this->resource instanceof AbstractPaginator) {
            $p = $this->resource;

            // حوّل العناصر لنفس شكل العنصر الواحد:
            $items = collect($p->items())->map(function ($item) {
                return self::make($item)->itemPayload();
            });

            $pArray = $p->toArray(); // يحتوي meta.links (Array) و links (Object)

            return [
                'data' => $items,
                // نفضّل الـ Array:
                'links' => $pArray['meta']['links'] ?? [],
            ];
        }

        // 2) لو Collection (نتيجة بحث بدون paginate) -> Array من العناصر
        if ($this->resource instanceof Collection) {
            return $this->resource->map(fn($item) => self::make($item)->itemPayload());
        }

        // 3) عنصر واحد
        return $this->itemPayload();
    }
    private function itemPayload(): array
    {
        $t = $this->resource;
        return [
            'id' => $t->id,
            'date' => $t->created_at,
            'code' => $t->code,
            'from' => ['type' => $t->from_type, 'id' => $t->from_id],
            'to' => ['type' => $t->to_type, 'id' => $t->to_id],
            'amount' => (float) $t->amount,
            'shipments_count' => (int) $t->shipments_count,
            'status' => $t->status,
            'notes' => $t->notes,
            'created_by' => optional($t->creator)->only(['id', 'name']),
            'approved_by' => optional($t->approver)->only(['id', 'name']),
        ];
    }
    public static function collection($resource)
    {
        return collect($resource)->map(fn($item) => (new static($item))->toArray(request()));
    }
}
