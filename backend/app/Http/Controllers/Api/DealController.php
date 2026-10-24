<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListDealsRequest;
use App\Http\Requests\StoreDealRequest;
use App\Http\Requests\UpdateDealRequest;
use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Http\JsonResponse;

class DealController extends Controller
{
    public function __construct(private readonly DealService $deals) {}

    public function index(ListDealsRequest $request): JsonResponse
    {
        return response()->json($this->deals->paginate($request->validated()));
    }

    public function store(StoreDealRequest $request): JsonResponse
    {
        return response()->json($this->deals->create($request->validated()), 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        return response()->json($this->deals->detail($deal));
    }

    public function update(UpdateDealRequest $request, Deal $deal): JsonResponse
    {
        return response()->json($this->deals->update($deal, $request->validated()));
    }
}
