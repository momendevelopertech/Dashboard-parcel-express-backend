<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FinancialReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'summary' => [
                'total_revenue' => $this->resource['summary']['total_revenue'] ?? 0,
                'cod_collected' => $this->resource['summary']['cod_collected'] ?? 0,
                'total_expenses' => $this->resource['summary']['total_expenses'] ?? 0,
                'net_profit' => $this->resource['summary']['net_profit'] ?? 0,
            ],
            'chart_data' => $this->resource['chart_data'] ?? [],
            'daily_breakdown' => $this->resource['daily_breakdown'] ?? [],
        ];
    }
}
