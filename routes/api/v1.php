<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\CompetitionTemplateController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DemoController;
use App\Http\Controllers\Api\V1\FrontHomeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HonorEventController;
use App\Http\Controllers\Api\V1\LeagueController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMembershipController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index'])->name('health');
Route::get('/honors', [HonorEventController::class, 'publicList'])->name('honors');
Route::get('/teams', [TeamMembershipController::class, 'directory'])->name('teams.directory');
Route::get('/home', [FrontHomeController::class, 'overview'])->name('home.overview');
Route::get('/competition/team-calendar', [CompetitionController::class, 'teamCalendar'])->name('competition.team-calendar');
Route::get('/competition/team-overview', [CompetitionController::class, 'teamOverview'])->name('competition.team-overview');
Route::get('/competition/team-history-detail', [CompetitionController::class, 'teamHistoryDetail'])->name('competition.team-history-detail');

// ===== 公开接口（无需 token）=====
Route::prefix('auth')->as('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login');
    // Route::post('/register', [AuthController::class, 'register'])->name('register');
    // refresh 必须在 auth 中间件外：token 过期时 auth 会直接拦截 401，永远到不了刷新逻辑
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh')->name('refresh');
});

// ===== 受保护接口：必须携带 Authorization: Bearer <token> =====
// 通过 auth:api 中间件（驱动为 jwt）校验 token，失败自动触发 Handler 的 401 映射。
Route::middleware('auth:api')->group(function () {
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])
        ->name('dashboard.statistics');
    Route::get('/league/options', [LeagueController::class, 'options'])
        ->name('league.options');

    Route::prefix('league')->as('league.')->group(function () {
        Route::post('/list', [LeagueController::class, 'list'])->name('list');
        Route::post('/add', [LeagueController::class, 'add'])->name('add');
        Route::post('/edit', [LeagueController::class, 'edit'])->name('edit');
        Route::post('/delete', [LeagueController::class, 'delete'])->name('delete');
    });

    Route::prefix('team')->as('team.')->group(function () {
        Route::post('/list', [TeamController::class, 'list'])->name('list');
        Route::post('/add', [TeamController::class, 'add'])->name('add');
        Route::post('/edit', [TeamController::class, 'edit'])->name('edit');
        Route::post('/delete', [TeamController::class, 'delete'])->name('delete');
        Route::post('/member_options', [TeamController::class, 'memberOptions'])->name('member-options');
        Route::get('/context', [TeamMembershipController::class, 'context'])->name('context');
        Route::post('/apply', [TeamMembershipController::class, 'apply'])->name('apply');
        Route::post('/application/cancel', [TeamMembershipController::class, 'cancel'])->name('application.cancel');
        Route::get('/managed', [TeamMembershipController::class, 'managedTeams'])->name('managed');
        Route::post('/manage/detail', [TeamMembershipController::class, 'manageDetail'])->name('manage.detail');
        Route::post('/application/review', [TeamMembershipController::class, 'review'])->name('application.review');
        Route::post('/manager/set', [TeamMembershipController::class, 'setManager'])->name('manager.set');
    });

    Route::prefix('competition')->as('competition.')->group(function () {
        Route::post('/front/list', [CompetitionController::class, 'frontList'])->name('front-list');
        Route::post('/front/detail', [CompetitionController::class, 'frontDetail'])->name('front-detail');
        Route::get('/front/team-registration', [CompetitionController::class, 'teamRegistrationOptions'])->name('team-registration-options');
        Route::post('/front/register-user', [CompetitionController::class, 'registerUser'])->name('register-user');
        Route::post('/front/register-team', [CompetitionController::class, 'registerTeam'])->name('register-team');
        Route::post('/front/report-score', [CompetitionController::class, 'reportScore'])->name('report-score');
        Route::post('/front/review-score', [CompetitionController::class, 'reviewScore'])->name('review-score');
        Route::post('/list', [CompetitionController::class, 'list'])->name('list');
        Route::post('/detail', [CompetitionController::class, 'detail'])->name('detail');
        Route::post('/add', [CompetitionController::class, 'add'])->name('add');
        Route::post('/edit', [CompetitionController::class, 'edit'])->name('edit');
        Route::post('/delete', [CompetitionController::class, 'delete'])->name('delete');
        Route::post('/finish', [CompetitionController::class, 'finish'])->name('finish');
        Route::post('/start-group', [CompetitionController::class, 'startGroup'])->name('start-group');
        Route::post('/start-knockout', [CompetitionController::class, 'startKnockout'])->name('start-knockout');
        Route::post('/team/start-league', [CompetitionController::class, 'startTeamLeague'])->name('team.start-league');
        Route::post('/team/start-knockout', [CompetitionController::class, 'startTeamKnockout'])->name('team.start-knockout');
        Route::post('/team/fixture-options', [CompetitionController::class, 'teamFixtureOptions'])->name('team.fixture-options');
        Route::post('/team/report-fixture', [CompetitionController::class, 'reportTeamFixture'])->name('team.report-fixture');
    });

    Route::prefix('competition-template')->as('competition-template.')->group(function () {
        Route::post('/list', [CompetitionTemplateController::class, 'list'])->name('list');
        Route::get('/options', [CompetitionTemplateController::class, 'options'])->name('options');
        Route::post('/add', [CompetitionTemplateController::class, 'add'])->name('add');
        Route::post('/edit', [CompetitionTemplateController::class, 'edit'])->name('edit');
        Route::post('/delete', [CompetitionTemplateController::class, 'delete'])->name('delete');
    });

    Route::prefix('honor-event')->as('honor-event.')->group(function () {
        Route::post('/list', [HonorEventController::class, 'list'])->name('list');
        Route::post('/add', [HonorEventController::class, 'add'])->name('add');
        Route::post('/edit', [HonorEventController::class, 'edit'])->name('edit');
        Route::post('/delete', [HonorEventController::class, 'delete'])->name('delete');
    });

    // 认证相关（需登录）
    Route::prefix('auth')->as('auth.')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('/menus', [MenuController::class, 'authMenus'])->name('menus');
        Route::get('/buttons', [MenuController::class, 'authButtons'])->name('buttons');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });

    Route::prefix('user')->as('user.')->group(function () {
        Route::post('/list', [UserController::class, 'list'])->name('list');
        Route::post('/tree/list', [UserController::class, 'treeList'])->name('tree-list');
        Route::post('/add', [UserController::class, 'add'])->name('add');
        Route::post('/edit', [UserController::class, 'edit'])->name('edit');
        Route::post('/delete', [UserController::class, 'delete'])->name('delete');
        Route::post('/change', [UserController::class, 'changeStatus'])->name('change');
        Route::post('/rest_password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/assign_roles', [UserController::class, 'assignRoles'])->name('assign-roles');
        Route::get('/status', [UserController::class, 'status'])->name('status');
        Route::get('/role', [UserController::class, 'roles'])->name('roles');
    });

    Route::prefix('role')->as('role.')->group(function () {
        Route::post('/list', [RoleController::class, 'list'])->name('list');
        Route::get('/all', [RoleController::class, 'all'])->name('all');
        Route::post('/add', [RoleController::class, 'add'])->name('add');
        Route::post('/edit', [RoleController::class, 'edit'])->name('edit');
        Route::post('/delete', [RoleController::class, 'delete'])->name('delete');
        Route::post('/assign_permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
        Route::get('/permission_tree', [RoleController::class, 'permissionTree'])->name('permission-tree');
    });

    Route::prefix('menu')->as('menu.')->group(function () {
        Route::get('/list', [MenuController::class, 'list'])->name('list');
        Route::post('/add', [MenuController::class, 'add'])->name('add');
        Route::post('/edit', [MenuController::class, 'edit'])->name('edit');
        Route::post('/delete', [MenuController::class, 'delete'])->name('delete');
    });

    // 返回格式参考路由（仅对照用，上线前可删除）
    Route::prefix('demo')->as('demo.')->group(function () {
        Route::get('/success', [DemoController::class, 'demoSuccess'])->name('success');
        Route::get('/success-empty', [DemoController::class, 'demoSuccessEmpty'])->name('success-empty');
        Route::post('/created', [DemoController::class, 'demoCreated'])->name('created');
        Route::put('/updated', [DemoController::class, 'demoUpdated'])->name('updated');
        Route::delete('/deleted', [DemoController::class, 'demoDeleted'])->name('deleted');
        Route::get('/paginate', [DemoController::class, 'demoPaginate'])->name('paginate');
        Route::get('/failed', [DemoController::class, 'demoFailed'])->name('failed');
        Route::get('/failed-with-errors', [DemoController::class, 'demoFailedWithErrors'])->name('failed-with-errors');
        Route::get('/throw-code', [DemoController::class, 'demoThrowByCode'])->name('throw-code');
        Route::get('/throw-with-errors', [DemoController::class, 'demoThrowWithErrors'])->name('throw-with-errors');
        Route::post('/validate', [DemoController::class, 'demoValidate'])->name('validate');
    });
});
