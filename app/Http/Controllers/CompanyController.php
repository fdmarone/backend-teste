<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\UseCases\Company\Show;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Responses\DefaultResponse;
use App\Http\Requests\Company\UpdateRequest;
use App\Http\Resources\Company\ShowResource;
use App\Http\Resources\Company\UpdateResource;
use App\Domains\Company\Update as UpdateDomain;
use App\Repositories\Company\Update as CompanyUpdate;

class CompanyController extends Controller
{
    /**
     * Endpoint de dados de empresa
     *
     * GET api/company
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        $response = (new Show(Auth::user()->company_id))->handle();

        return response()->json(
            (new DefaultResponse(
                new ShowResource($response)
            ))->toArray()
        );
    }

    /**
     * Endpoint de modificação de empresa
     *
     * PATCH api/company
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request): JsonResponse
    {
        $dominio = (new UpdateDomain(
            Auth::user()->company_id,
            $request->name,
        ))->handle();
        (new CompanyUpdate($dominio))->handle();

        $resposta = Company::find(Auth::user()->company_id)->first()->toArray();

        return $this->response(
            new DefaultResponse(
                new UpdateResource($resposta)
            )
        );
    }
}
