<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type')->lower()->value(); // 'hub' | 'station' | null
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $forSelect = (bool) $request->boolean('for_select', false);

        $user = $request->user();
        $isSuper = $user && method_exists($user, 'hasRole') ? $user->hasRole('Super Admin') : false;

        // هنطبق الـ scope بس لو مش Super Admin ومفيش all=1
        $applyOwnerScope = !$isSuper && !$request->boolean('all', false);

        $hubQuery = Hub::query()
            ->with([
                'country',
                'state',
                'city',
                'governorate',
                'place',
                'stations' => fn($q) => $q->with(['country', 'state', 'city', 'governorate', 'place', 'hub']),
            ])
            ->when($applyOwnerScope && method_exists(Hub::class, 'scopeByOwner'), fn($q2) => $q2->byOwner())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%");
                });
            });

        $stationQuery = Station::query()
            ->with(['hub', 'country', 'state', 'city', 'governorate', 'place'])
            ->when($applyOwnerScope && method_exists(Station::class, 'scopeByOwner'), fn($q2) => $q2->byOwner())
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%");
                });
            });

        $items = collect();

        if (!$type || $type === 'hub') {
            $hubs = $hubQuery->orderBy('name')->get()->map(function ($hub) use ($forSelect) {
                if ($forSelect) {
                    return [
                        'id' => $hub->id,
                        'name' => $hub->name,
                        'type' => 'App\\Models\\Hub',
                        'label' => $hub->name,
                        'value' => $hub->id,
                        'meta' => ['readable_type' => 'Hub'],
                    ];
                }
                $arr = $hub->toArray();
                $arr['type'] = 'App\\Models\\Hub';
                return $arr;
            });
            $items = $items->merge($hubs);
        }

        if (!$type || $type === 'station') {
            $stations = $stationQuery->orderBy('name')->get()->map(function ($st) use ($forSelect) {
                if ($forSelect) {
                    return [
                        'id' => $st->id,
                        'name' => $st->name,
                        'type' => 'App\\Models\\Station',
                        'label' => "{$st->name} (Station)",
                        'value' => $st->id,
                        'meta' => ['readable_type' => 'Station', 'hub_id' => $st->hub_id, 'hub_name' => optional($st->hub)->name],
                    ];
                }
                $arr = $st->toArray();
                $arr['type'] = 'App\\Models\\Station';
                return $arr;
            });
            $items = $items->merge($stations);
        }

        $items = $items->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $total = $items->count();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $results,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil(($total ?: 1) / $perPage),
            ],
        ]);
    }

}
