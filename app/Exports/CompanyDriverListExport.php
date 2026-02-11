<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CompanyDriverListExport implements FromCollection, WithHeadings, WithMapping
{
    protected $drivers;

    /**
     * Accept a collection of driver records.
     *
     * @param \Illuminate\Support\Collection $drivers
     */
    public function __construct($drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Return the collection of drivers passed from the controller.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->drivers;
    }

    /**
     * Define the headings shown in the exported file.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Company',
        ];
    }

    /**
     * Map each driver record into a row for the export.
     *
     * @param mixed $driver A user record with the driver relationship loaded.
     * @return array
     */
    public function map($driver): array
    {
        $name  = $driver->name ?? '';
        $email = $driver->email ?? '';
        $phone = isset($driver->driver) && isset($driver->driver->phone)
            ? $driver->driver->phone
            : '';

        $company = isset($driver->driver) && isset($driver->driver->company)
            ? $driver->driver->company->name
            : '';

        return [
            $name,
            $email,
            $phone,
            $company,
        ];
    }
}
