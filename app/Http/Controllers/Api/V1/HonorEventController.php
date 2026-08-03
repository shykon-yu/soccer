<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\HonorEventDeleteRequest;
use App\Http\Requests\Api\V1\HonorEventListRequest;
use App\Http\Requests\Api\V1\HonorEventSaveRequest;
use App\Http\Resources\Api\V1\HonorEventResource;
use App\Services\HonorEventService;
use Illuminate\Http\JsonResponse;

class HonorEventController extends BaseController
{
    public function __construct(private readonly HonorEventService $honorEventService) {}

    public function publicList(): JsonResponse
    {
        $events = $this->honorEventService->publicList();

        return $this->resource(resource: HonorEventResource::collection($events));
    }

    public function list(HonorEventListRequest $request): JsonResponse
    {
        $list = $this->honorEventService->paginate($request->validated());

        return $this->resourceCollection(HonorEventResource::collection($list));
    }

    public function add(HonorEventSaveRequest $request): JsonResponse
    {
        $event = $this->honorEventService->create($request->validated());

        return $this->created(data: HonorEventResource::make($event));
    }

    public function edit(HonorEventSaveRequest $request): JsonResponse
    {
        $event = $this->honorEventService->update($request->validated());

        return $this->updated(data: HonorEventResource::make($event));
    }

    public function delete(HonorEventDeleteRequest $request): JsonResponse
    {
        $this->honorEventService->delete((int) $request->validated('id'));

        return $this->deleted('历史荣誉删除成功');
    }
}
