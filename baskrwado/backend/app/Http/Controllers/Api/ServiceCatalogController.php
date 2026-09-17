<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaseWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ServiceCatalogController extends Controller
{
    public function __invoke(CaseWorkflowService $workflow): JsonResponse
    {
        $services = collect($workflow->catalog())
            ->map(fn (array $service) => Arr::except($service, ['required_fields']))
            ->values();

        return response()->json(['data' => $services]);
    }
}
