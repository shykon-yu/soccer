<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Http\Requests\Api\V1\DemoRequest;
use App\Http\Resources\Api\V1\DataResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 返回格式参考控制器（仅供对照，不接业务）。
 *
 * 本文件所有方法都「直接调用」 App\Http\Traits\ApiResponse trait
 * （由 BaseController 引入）的方法，统一输出 { code, message, data, errors? }。
 *
 * 注意：方法名刻意避开 trait 自带的方法名（success/created/fail/paginated/...），
 * 以免与 BaseController 上的同名方法冲突。
 *
 * 下面每个示例都标注了「真实返回的 JSON」（即 ApiResponse 实际产出的结构）。
 */
class DemoController extends BaseController
{
    /**
     * 调 success()：成功 + 普通 data
     * GET /api/v1/demo/success
     *
     * 真实返回：
     * {"code":0,"message":"获取成功","data":{"id":1,"name":"测试数据"}}
     */
    public function demoSuccess(): JsonResponse
    {
        return $this->resource(new DataResource([
            'id' => 1,
            'name' => '测试数据',
        ]), '获取成功');
    }

    /**
     * 调 success()：成功仅提示（data 为 null）
     * GET /api/v1/demo/success-empty
     *
     * 真实返回：
     * {"code":0,"message":"操作成功","data":null}
     */
    public function demoSuccessEmpty(): JsonResponse
    {
        return $this->success(message: '操作成功');
    }

    /**
     * 调 created()：201 创建成功
     * POST /api/v1/demo/created
     *
     * 真实返回（HTTP 201）：
     * {"code":0,"message":"创建成功","data":{"id":99,"name":"新建项"}}
     */
    public function demoCreated(Request $request): JsonResponse
    {
        return $this->created(new DataResource([
            'id' => 99,
            'name' => $request->input('name', '新建项'),
        ]), '创建成功');
    }

    /**
     * 调 updated()：更新成功
     * PUT /api/v1/demo/updated
     *
     * 真实返回：
     * {"code":0,"message":"更新成功","data":{"id":1}}
     */
    public function demoUpdated(Request $request): JsonResponse
    {
        return $this->updated(new DataResource([
            'id' => (int) $request->input('id', 1),
        ]), '更新成功');
    }

    /**
     * 调 deleted()：删除成功（返回 200 + JSON，不用 204）
     * DELETE /api/v1/demo/deleted
     *
     * 真实返回：
     * {"code":0,"message":"删除成功","data":null}
     */
    public function demoDeleted(): JsonResponse
    {
        return $this->deleted('删除成功');
    }

    /**
     * 调 paginated()：列表分页，data = { list, total, page, pageSize }
     * （这里用假数据模拟 LengthAwarePaginator，真实场景换成模型分页结果即可）
     * GET /api/v1/demo/paginate?page=1&pageSize=10
     *
     * 真实返回：
     * {"code":0,"message":"列表获取成功",
     *  "data":{"list":[...],"total":53,"page":1,"pageSize":10}}
     */
    public function demoPaginate(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 10);

        $all = collect(range(1, 53))->map(fn ($i) => ['id' => $i, 'name' => "item-{$i}"]);
        $items = $all->forPage($page, $pageSize)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $all->count(),
            $pageSize,
            $page,
            ['path' => $request->url()]
        );

        return $this->resource(new DataResource([
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
        ]), '列表获取成功');
    }

    /**
     * 调 fail()：业务失败（默认 code = ApiCode::FAIL）
     * GET /api/v1/demo/failed
     *
     * 真实返回（HTTP 200）：
     * {"code":1006,"message":"操作失败，请稍后重试","data":null}
     */
    public function demoFailed(): JsonResponse
    {
        return $this->fail('操作失败，请稍后重试', ApiCode::FAIL);
    }

    /**
     * 调 fail()：业务失败 + 字段级 errors（前端做表单回显）
     * GET /api/v1/demo/failed-with-errors
     *
     * 真实返回（HTTP 200）：
     * {"code":1000,"message":"提交的数据有误","data":null,
     *  "errors":{"mobile":["手机号格式不正确"],"code":["验证码错误"]}}
     */
    public function demoFailedWithErrors(): JsonResponse
    {
        return $this->fail(
            '提交的数据有误',
            ApiCode::PARAM_ERROR,
            ['mobile' => ['手机号格式不正确'], 'code' => ['验证码错误']]
        );
    }

    /**
     * 抛 BusinessException::fromCode()：用 ApiCode 常量，自动带出文案与 HTTP 状态
     * （异常由 Handler 转成和 fail() 同构的 JSON）
     * GET /api/v1/demo/throw-code
     *
     * 真实返回（HTTP 401）：
     * {"code":1001,"message":"用户名或密码错误","data":null}
     */
    public function demoThrowByCode(): JsonResponse
    {
        throw BusinessException::fromCode(ApiCode::LOGIN_FAILED);
    }

    /**
     * 抛 BusinessException::fromCode()：带字段级 errors
     * GET /api/v1/demo/throw-with-errors
     *
     * 真实返回（HTTP 400）：
     * {"code":1000,"message":"参数错误","data":null,
     *  "errors":{"email":["邮箱已被注册"]}}
     */
    public function demoThrowWithErrors(): JsonResponse
    {
        throw BusinessException::fromCode(
            ApiCode::PARAM_ERROR,
            ['email' => ['邮箱已被注册']]
        );
    }

    /**
     * 表单校验失败：注入 DemoRequest，Laravel 的 ValidationException
     * 会被 Handler 统一转成 { code:422, message, errors }
     * POST /api/v1/demo/validate
     *
     * 真实返回（HTTP 422，不传参时）：
     * {"code":422,"message":"提交的数据校验失败",
     *  "errors":{"name":["名称不能为空"],"email":["邮箱不能为空"]}}
     */
    public function demoValidate(DemoRequest $request): JsonResponse
    {
        return $this->resource(new DataResource($request->validated()), '校验通过');
    }
}
