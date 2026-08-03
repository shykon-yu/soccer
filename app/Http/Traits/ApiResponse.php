<?php

namespace App\Http\Traits;

use App\Constants\ApiCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * API 统一响应 Trait（合并版：取两份优点 + 对齐前端约定）
 *
 * ========== 响应固定结构 ==========
 *   { "code": 0, "message": "success", "data": {} }            // 成功
 *   { "code": 1001, "message": "xxx", "data": null }           // 业务失败
 *   { "code": 1000, "message": "xxx", "data": null,
 *     "errors": { "mobile": ["格式错误"] } }                    // 失败 + 字段级错误
 *
 * ========== 前端判断规则（soccer_vue） ==========
 *   if (res.code === 0) { 成功，用 res.data }
 *   else { 弹 res.message；表单场景读 res.errors 做字段回显 }
 *
 * ========== 约定 ==========
 *  - 成功默认 HTTP 200；created 返回 201；业务失败统一 200 + 业务码（由前端 code 判断）。
 *  - 列表分页 data = { list, total, page, pageSize }，对齐前端 useTable 的 res.list / res.total。
 *  - 业务码一律用 App\Constants\ApiCode 常量，不散写魔法数字。
 *
 * ========== 示例 ==========
 *   return $this->resource(new UserResource($user));
 *   return $this->created(new UserResource($user));
 *   return $this->fail('库存不足', ApiCode::STOCK_INSUFFICIENT);
 *   return $this->fail('字段有误', ApiCode::PARAM_ERROR, ['mobile' => ['格式错误']]);
 *   return $this->resourceCollection(UserResource::collection($paginator));
 */
trait ApiResponse
{
    /**
     * 成功响应。
     * 业务接口的 data 优先传入 JsonResource；简单无业务示例才直接传数组。
     */
    public function success(mixed $data = null, string $message = 'success'): JsonResponse
    {
        return $this->json(ApiCode::SUCCESS, $message, $data);
    }

    /**
     * 创建成功（HTTP 201）
     */
    public function created(mixed $data = null, string $message = '创建成功'): JsonResponse
    {
        return $this->json(ApiCode::SUCCESS, $message, $data, 201);
    }

    /**
     * 更新成功
     */
    public function updated(mixed $data = null, string $message = '更新成功'): JsonResponse
    {
        return $this->success($data, $message);
    }

    /**
     * 删除成功（返回 200 + JSON，避免 204 无 body 导致前端拦截器误判）
     */
    public function deleted(string $message = '删除成功'): JsonResponse
    {
        return $this->success(null, $message);
    }

    /**
     * 业务失败
     *
     * @param  string  $message  错误提示（前端弹窗）
     * @param  int  $code  业务码，用 ApiCode::XXX
     * @param  mixed  $errors  字段级错误（可选，用于表单回显）
     */
    public function fail(string $message = '操作失败', int $code = ApiCode::FAIL, mixed $errors = null): JsonResponse
    {
        return $this->json($code, $message, null, 200, $errors);
    }

    /**
     * 列表分页：data = { list, total, page, pageSize }
     */
    public function paginated(LengthAwarePaginator $paginator, string $message = 'success'): JsonResponse
    {
        return $this->json(ApiCode::SUCCESS, $message, [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
        ]);
    }

    /**
     * 单个 Resource 响应
     */
    public function resource(JsonResource $resource, string $message = 'success'): JsonResponse
    {
        return $this->json(ApiCode::SUCCESS, $message, $resource);
    }

    /**
     * Resource 集合响应（兼容分页 / 非分页，统一输出 { list, total, page, pageSize }）
     */
    public function resourceCollection(ResourceCollection $collection, string $message = 'success'): JsonResponse
    {
        $response = $collection->response();
        $data = $response->getData(true);

        $result = ['list' => $data['data'] ?? []];

        if (isset($data['meta'])) {
            $result['total'] = $data['meta']['total'] ?? 0;
            $result['page'] = $data['meta']['current_page'] ?? 1;
            $result['pageNum'] = $data['meta']['current_page'] ?? 1;
            $result['pageSize'] = $data['meta']['per_page'] ?? 0;
        }

        return $this->json(ApiCode::SUCCESS, $message, $result);
    }

    /**
     * 底层 JSON 响应（唯一出口）
     */
    protected function json(int $code, string $message, mixed $data = null, int $httpStatus = 200, mixed $errors = null): JsonResponse
    {
        $payload = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $httpStatus);
    }
}
