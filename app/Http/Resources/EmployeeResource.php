<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'department' => new EmployeeDepartmentResource($this->department),
            'position' => new EmployeePositionResource($this->position),
            'level' => new HierarchyLevelResource($this->level),
            'direct_manager' => $this->directManager ? new UserResource($this->directManager) : null,
            'salary' => $this->salary,
            'date_of_joining' => $this->date_of_joining,
            'basic_salary' => $this->basic_salary,
            'base_hours' => $this->base_hours,
            'overtime_hour_salary' => $this->overtime_hour_salary,
            'country_id' => $this->country_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


// namespace App\Http\Resources;

// use Illuminate\Http\Request;
// use Illuminate\Http\Resources\Json\JsonResource;

// class EmployeeResource extends JsonResource
// {
    
//     public function toArray(Request $request): array
//     {
//         return [
//             'id' => $this->id,
//             'employable' => [
//                 'type' => $this->employable_type,
//                 'id' => $this->employable_id,
//             ],
//             'name' => $this->getEmployableName(),
//             'position' => new EmployeePositionResource($this->whenLoaded('position')),
//             'department' => new EmployeeDepartmentResource($this->whenLoaded('department')),
//             'direct_manager' => new SampleEmployeeResource($this->whenLoaded('directManager')),
//             'country' => new CountryResource($this->whenLoaded('country')),
//             'date_of_joining' => $this->date_of_joining,
//             'base_hours' => $this->base_hours,
//             'overtime_hour_salary' => $this->overtime_hour_salary,
//             'base_hour_salary' => $this->base_hour_salary,
//             'created_at' => $this->created_at,
//             'updated_at' => $this->updated_at,
//         ];
//     }
//     public function getEmployableName(): string
//     {
//         if ($this->employable_type == 'User') {
//             return $this->employable ? $this->employable->name : '';
//         }
//         if ($this->employable_type == 'Driver') {
//             return $this->employable ? $this->employable->user->name : '';
//         }
//         return $this->employable_type;
//     }
// }

