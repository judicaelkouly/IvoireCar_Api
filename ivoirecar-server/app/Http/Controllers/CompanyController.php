<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = Company::withCount(['stations', 'vehicles'])->paginate(15);
        return response()->json($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return response()->json([
            'message' => 'Compagnie créée avec succès.',
            'company' => $company,
        ], 201);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'company' => $company->load([
                'stations',
                'vehicles',
                'routes',
                'employees.roles',
                'employees.station'
            ])->loadCount(['stations', 'vehicles', 'employees', 'routes']),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return response()->json([
            'message' => 'Compagnie mise à jour avec succès.',
            'company' => $company,
        ]);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json([
            'message' => 'Compagnie supprimée avec succès.',
        ]);
    }
}
