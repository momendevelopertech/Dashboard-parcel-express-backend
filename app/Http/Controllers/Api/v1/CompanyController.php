<?php

namespace App\Http\Controllers\Api\v1;


use App\Exports\CompanyCommissionExport;
use App\Exports\CompanyDriverListExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\CompanyCommission;
use App\Models\Driver;
use App\Models\DriverSetting;
use App\Models\State;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="Other", description="Company management")
 * @OA\Server(url="/api")
 */
class CompanyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/companies",
     *     summary="Get a list of companies",
     *     description="Retrieve a list of companies. Optionally, search for companies using the query parameter.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for company name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Companies retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $companies = Company::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $companies = $companies
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with('drivers.settings', 'drivers.user')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $companies = $companies->with('drivers.settings', 'drivers.user')->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Companies reterived successfully.", new CompanyResource($companies), []);
    }

    /**
     * @OA\Post(
     *     path="/companies/store",
     *     summary="Create a new company",
     *     description="Create a new company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the company", example="Company Name"),
     *             @OA\Property(property="payment_proof_required", type="boolean", description="Whether payment proof is required"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating company",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(StoreCompanyRequest $request)
    {
        try {
            $data = $request->validated();
            $data['payment_proof_required'] = $request->payment_proof_required == 'on' ? true : false;
            $company = Company::create($data);
            activityLog('company create',"new company created called {$company->name}");
            return sendResponse("Company created successfully.", new CompanyResource($company));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating company.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/companies/update",
     *     summary="Update an existing company",
     *     description="Update an existing company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the company", example=1),
     *             @OA\Property(property="name", type="string", description="Name of the company", example="Updated Company Name"),
     *             @OA\Property(property="payment_proof_required", type="boolean", description="Whether payment proof is required"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating company",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateCompanyRequest $request)
    {
        try {
            $company = Company::findOrFail($request->id);
            $data = $request->all();
            $data['payment_proof_required'] = $request->payment_proof_required == 'on' ? true : false;
            $company->update($data);
            activityLog('company update',"company updated called {$company->name}");
            return sendResponse("Company updated successfully.", new CompanyResource($company));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating company.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/companies/delete",
     *     summary="Delete a company",
     *     description="Delete a company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the company", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting company",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
            $company = Company::findOrFail($request->id);
            $company->delete();
            activityLog('company delete',"company deleted called {$company->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Company deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/companies/all",
     *     summary="Get all companies",
     *     description="Retrieve all companies.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Companies retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        return sendResponse("Companies", new CompanyResource(Company::all()));
    }

    /**
     * @OA\Get(
     *     path="/companies/view",
     *     summary="Get a company by ID",
     *     description="Retrieve a company by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="query",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Company not found",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function view(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
        ]);
        $companyId = $request->input('company_id');
        $company = Company::find($companyId);
        if (!$company) {
            return sendResponse("Company not found.", [], 404);
        }
        $company['states'] = State::where('country_id', 165)->get();
        return sendResponse("Company retrieved successfully.", $company, []);
    }
    /**
     * @OA\Get(
     *     path="/companies/commissions",
     *     summary="Get commissions for a company",
     *     description="Retrieve commissions for a specific company.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="query",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commissions retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function commissions(Request $request)
    {
        $company_id = $request->company_id;
        $commissions = CompanyCommission::where('company_id', $company_id)->with('company', 'state')->get();
        return sendResponse("Commissions reterived successfully.", $commissions, []);
    }

    /**
     * @OA\Post(
     *     path="/companies/store_commissions",
     *     summary="Store commissions for a company",
     *     description="Store or update commissions for a company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="company_id", type="integer", description="ID of the company"),
     *             @OA\Property(
     *                 property="commissions",
     *                 type="array",
     *                 description="Array of commissions",
     *                 @OA\Items(
     *                     @OA\Property(property="state_id", type="integer", description="ID of the state"),
     *                     @OA\Property(property="delivery_fee", type="number", format="float", description="Delivery fee"),
     *                     @OA\Property(property="pickup_fee", type="number", format="float", description="Pickup fee"),
     *                 ),
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commission saved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while saving commission",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store_commissions(Request $request)
    {
        try {
            $data = $request->validate([
                'company_id' => 'required|exists:companies,id',
                'commissions' => 'required|array',
                'commissions.*.state_id' => 'required|exists:states,id',
                'commissions.*.delivery_fee' => 'required|numeric|min:0',
                'commissions.*.pickup_fee' => 'required|numeric|min:0',
            ]);
            foreach ($data['commissions'] as $commissionData) {
               $commitions =CompanyCommission::updateOrCreate(
                    [
                        'company_id' => $data['company_id'],
                        'state_id' => $commissionData['state_id'],
                    ],
                    [
                        'delivery_fee' => $commissionData['delivery_fee'],
                        'pickup_fee' => $commissionData['pickup_fee'],
                    ]
                );
            }
            activityLog('company commission update',"company commission updated called {$commitions?->company?->name}");
            return response()->json(["message" => "Commission saved successfully."], 200);
        } catch (QueryException $e) {
            return response()->json(["error" => "Error occurred while saving commission.", "details" => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/companies/export_commissions/{company_id}",
     *     summary="Export commissions for a company",
     *     description="Export commissions for a specific company.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commissions exported successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Account not found",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function export_commissions($company_id)
    {
        $commissions = CompanyCommission::where("company_id", $company_id)
            ->with('company', 'state')
            ->first();
        if (!$commissions) {
            return sendResponse("Account not found.", [], false, ["Account not found."], 500);
        }
        return Excel::download(new CompanyCommissionExport($commissions), 'commissions.csv');
    }

    /**
     * @OA\Post(
     *     path="/companies/import_commissions/{company_id}",
     *     summary="Import commissions for a company",
     *     description="Import commissions for a specific company.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="file",
     *                     description="CSV, XLSX, or XLS file containing commission data",
     *                 ),
     *                 @OA\Property(
     *                     property="confirm",
     *                     type="boolean",
     *                     description="Confirmation flag for importing data",
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commission data imported and saved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Some rows have errors or data parsed successfully. Please review the data before confirming.",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="An error occurred while saving commission data",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function import_commissions(Request $request, $company_id)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls'
        ]);
        $confirm = $request->input('confirm', false);
        $dataArray = Excel::toArray(null, $request->file('file'));
        $data = [];
        if (!empty($dataArray) && isset($dataArray[0])) {
            $data = $dataArray[0];
        }
        $firstRow = $data[0] ?? [];
        if (!empty($firstRow) && is_array($firstRow) && !array_key_exists('Company', $firstRow)) {
            $headers = array_map('trim', $data[0]);
            unset($data[0]);
            $data = array_values($data);
            $data = array_map(function ($row) use ($headers) {
                return array_combine($headers, $row);
            }, $data);
        }
        $errors = [];
        $validatedData = [];
        foreach ($data as $index => $row) {
            if (
                !isset($row['Company']) ||
                !isset($row['State ID']) ||
                !isset($row['State Name']) ||
                !isset($row['Delivery Fee']) ||
                !isset($row['Pickup Fee'])
            ) {
                $errors[] = "Row " . ($index + 1) . " is missing one or more required columns.";
                continue;
            }
            if (!is_numeric($row['Delivery Fee']) || !is_numeric($row['Pickup Fee'])) {
                $errors[] = "Row " . ($index + 1) . " has invalid fee values.";
                continue;
            }
            $validatedData[] = [
                'company_id'   => $company_id,
                'company'   => $row['Company'],
                'state_id'     => $row['State ID'],
                'state_name'     => $row['State Name'],
                'delivery_fee' => $row['Delivery Fee'],
                'pickup_fee'   => $row['Pickup Fee']
            ];
        }
        if (!empty($errors)) {
            return sendResponse("Some rows have errors.", $errors, false, [], 422);
        }
        if (!$confirm) {
            return sendResponse("Data parsed successfully. Please review the data before confirming.", $validatedData);
        }
        DB::beginTransaction();
        try {
            foreach ($validatedData as $row) {
                $commissions=CompanyCommission::updateOrCreate(
                    [
                        'company_id' => $row['company_id'],
                        'state_id'   => $row['state_id']
                    ],
                    [
                        'delivery_fee' => $row['delivery_fee'],
                        'pickup_fee'   => $row['pickup_fee']
                    ]
                );
             activityLog('company commission import',"company commission imported for company {$commissions?->company?->name}");

            }
            DB::commit();
            return sendResponse("Commission data imported and saved successfully.", $validatedData);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while saving commission data.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/companies/export_drivers_list/{company_id}",
     *     summary="Export drivers list for a company",
     *     description="Export drivers list for a specific company.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Drivers list exported successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Account not found",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function export_drivers_list($company_id)
    {
        $drivers = User::whereHas('driver', function ($q) use ($company_id) {
            $q->where("company_id", $company_id);
        })
            ->with('driver.company')
            ->get();
        if (!$drivers) {
            return sendResponse("Account not found.", [], false, [], 500);
        }
        return Excel::download(new CompanyDriverListExport($drivers), 'company_drivers_list.csv');
    }

    /**
     * @OA\Post(
     *     path="/companies/update_payment_proof_required/{company_id}",
     *     summary="Update payment proof requirement for a company",
     *     description="Update payment proof requirement for a specific company.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="payment_proof_required",
     *                 type="boolean",
     *                 description="Whether payment proof is required",
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment proof requirement updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating payment proof requirement",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update_payment_proof_required(Request $request, $company_id)
    {
        $request->validate([
            'payment_proof_required' => 'required'
        ]);
        try {
            $company = Company::findOrFail($company_id);
            $company->payment_proof_required = $request->payment_proof_required;
            $company->save();
            return sendResponse(
                "Payment proof requirement updated successfully.",
                new CompanyResource($company)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating payment proof requirement.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/companies/drivers_status_update",
     *     summary="Update drivers status",
     *     description="Update drivers status for a company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the company"),
     *             @OA\Property(property="driver_ids", type="array", description="Array of driver IDs", @OA\Items(type="integer")),
     *             @OA\Property(property="status", type="string", description="Status of the driver"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ability updated",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating payment proof requirement",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function drivers_status_update(Request $request)
    {
        $data = $request->validate([
            'id'           => 'required|integer|exists:companies,id',
            'driver_ids'   => 'array',
            'driver_ids.*' => 'integer|exists:users,id',
            'status' => 'required',
        ]);
        $companyId       = $data['id'];
        DB::beginTransaction();
        try {
            $drivers = Driver::where('company_id', $companyId)->get();
            foreach ($drivers as $driver) {
                $status = $request->status;
                DriverSetting::updateOrCreate(
                    ['driver_id' => $driver->id],
                    ['edit_proof' => $status]
                );
            }
            DB::commit();
            return sendResponse("Ability updated.", []);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while updating payment proof requirement.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/companies/delivery_confirmation_method",
     *     summary="Update delivery confirmation method",
     *     description="Update delivery confirmation method for a company.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the company"),
     *             @OA\Property(property="method", type="string", description="Delivery confirmation method (otp, proof, otp_proof)"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Confirmation Method updated",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating payment proof requirement",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delivery_confirmation_method(Request $request)
    {
        $data = $request->validate([
            'id'           => 'required|integer|exists:companies,id',
            'method'       => 'required|in:otp,proof,otp_proof',
        ]);
        $companyId       = $data['id'];
        DB::beginTransaction();
        try {
            $drivers = Driver::where('company_id', $companyId)->get();
            foreach ($drivers as $driver) {
                $method = $request->method;
                DriverSetting::updateOrCreate(
                    ['driver_id' => $driver->id],
                    ['delivery_confirmation_method' => $method]
                );
            }
            DB::commit();
            return sendResponse("Delivery Confirmation Method updated.", []);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while updating payment proof requirement.", [], false, [$e->getMessage()], 422);
        }
    }
}
