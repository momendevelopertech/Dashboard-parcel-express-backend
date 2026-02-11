<?php

namespace App\Imports;

use App\Models\Zone;
use App\Models\Governorate;
use App\Models\State;
use App\Models\Place;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ZoneImport implements ToCollection, WithHeadingRow
{
    private $importedCount = 0;
    private $errors = [];

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $rowArray = $row->toArray();

                // Normalize column names (case-insensitive)
                $normalizedRow = [];
                foreach ($rowArray as $key => $value) {
                    $normalizedKey = strtolower(trim(str_replace(' ', '_', $key)));
                    $normalizedRow[$normalizedKey] = $value;
                }

                // التحقق من وجود الأعمدة المطلوبة
                if (!isset($normalizedRow['coordinates']) && !isset($normalizedRow['coordinate'])) {
                    $this->errors[] = "Row " . ($index + 2) . ": Missing coordinates column";
                    continue;
                }

                // استخدام أي تسمية ممكنة للإحداثيات
                $coordinatesKey = isset($normalizedRow['coordinates']) ? 'coordinates' : (isset($normalizedRow['coordinate']) ? 'coordinate' : null);

                if (!$coordinatesKey) {
                    $this->errors[] = "Row " . ($index + 2) . ": Coordinates column not found";
                    continue;
                }

                // Validate row data
                $validator = Validator::make([
                    'name' => $normalizedRow['name'] ?? null,
                    'coordinates' => $normalizedRow[$coordinatesKey] ?? null
                ], $this->rules());

                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Get facility information if a helper function is available
                $facility = function_exists('facility') ? facility() : null;

                // Clean and validate coordinates
                $coordinates = trim($normalizedRow[$coordinatesKey]);

                if (!$this->isValidGeoJSON($coordinates)) {
                    $this->errors[] = "Row " . ($index + 2) . ": Invalid GeoJSON format: " . substr($coordinates, 0, 100) . "...";
                    continue;
                }

                // Use updateOrCreate to handle existing zones and create new ones
                $zone = Zone::updateOrCreate(
                    [
                        'name' => $normalizedRow['name'],
                        'owner_id' => $facility ? $facility->id : null,
                        'owner_type' => $facility ? $facility->type : null,
                    ],
                    [
                        'coordinates' => DB::raw("ST_GeomFromGeoJSON('" . addslashes($coordinates) . "')"),
                    ]
                );

                // معالجة العلاقات إذا وجدت IDs
                $this->processRelationships($zone, $normalizedRow, $index);

                $this->importedCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    /**
     * Process relationships (governorates, states, places)
     */
    private function processRelationships($zone, $normalizedRow, $index)
    {
        // معالجة Governorates
        if (!empty($normalizedRow['governorate_ids'])) {
            try {
                $governorateIds = $this->parseIds($normalizedRow['governorate_ids']);
                // Governorates يتم حسابها تلقائياً من States، لذا لا نحتاج إلى sync مباشر
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": Invalid governorate IDs - " . $e->getMessage();
            }
        }

        // معالجة States
        if (!empty($normalizedRow['state_ids'])) {
            try {
                $stateIds = $this->parseIds($normalizedRow['state_ids']);
                $zone->selectedStates()->sync($stateIds);
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": Invalid state IDs - " . $e->getMessage();
            }
        }

        // معالجة Places
        if (!empty($normalizedRow['place_ids'])) {
            try {
                $placeIds = $this->parseIds($normalizedRow['place_ids']);
                $zone->assignedPlaces()->sync($placeIds);
            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": Invalid place IDs - " . $e->getMessage();
            }
        }
    }

    /**
     * Parse comma-separated IDs string to array
     */
    private function parseIds($idsString)
    {
        $ids = array_map('intval', array_filter(explode(',', $idsString)));

        if (empty($ids)) {
            throw new \Exception("No valid IDs found");
        }

        // التحقق من أن جميع IDs أرقام صحيحة موجبة
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new \Exception("Invalid ID: " . $id);
            }
        }

        return $ids;
    }

    /**
     * Validate GeoJSON format
     */
    private function isValidGeoJSON($json): bool
    {
        if (!is_string($json) || empty(trim($json))) {
            return false;
        }

        $json = trim($json);
        if (strpos($json, '"type"') === false && strpos($json, "'type'") !== false) {
            $json = str_replace("'", '"', $json);
        }

        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE &&
            isset($data['type']) &&
            in_array($data['type'], ['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon']) &&
            isset($data['coordinates']);
    }

    /**
     * Define validation rules for each row.
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'coordinates' => 'required|string',
        ];
    }

    /**
     * Get the count of successfully imported zones.
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get the errors that occurred during the import.
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
