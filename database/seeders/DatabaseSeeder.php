<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->seedSystemData();

        $this->call([
            LeagueTeamSeeder::class,
            MemberSeeder::class,
            CompetitionTemplateSeeder::class,
        ]);
    }

    /** 初始化后台运行所需的管理员、角色、菜单和权限。 */
    public function seedSystemData(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::withTrashed()->firstOrCreate(
            ['username' => 'admin'],
            [
                'nickname' => '比安',
                'email' => 'admin@example.com',
                'phone' => null,
                'password' => Hash::make('admin123456'),
                'status' => 1,
            ]
        );
        if ($admin->trashed()) {
            $admin->restore();
        }

        $roles = [
            '管理员',
            '联盟主席',
            '联盟管理',
            '战队队长',
            '战队管理',
            '队员',
            '黑名单',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'api');
        }

        $menus = $this->seedMenus();
        $permissions = $menus->pluck('permission')->all();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        Role::findByName('管理员', 'api')->syncPermissions($permissions);

        $homePermission = ['menu:home'];
        foreach (array_slice($roles, 1, 2) as $roleName) {
            Role::findByName($roleName, 'api')->syncPermissions($homePermission);
        }
        Role::findByName('战队队长', 'api')->syncPermissions([
            'menu:home', 'menu:teamCompetition', 'menu:teamMemberManage',
            'button:teamMemberManage:review', 'button:teamMemberManage:setManager',
        ]);
        Role::findByName('战队管理', 'api')->syncPermissions([
            'menu:home', 'menu:teamCompetition', 'menu:teamMemberManage',
            'button:teamMemberManage:review',
        ]);
        Role::findByName('队员', 'api')->syncPermissions([]);
        Role::findByName('黑名单', 'api')->syncPermissions([]);

        $admin->syncRoles(['管理员']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function seedMenus()
    {
        $home = Menu::query()->updateOrCreate(
            ['permission' => 'menu:home'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '首页',
                'name' => 'home',
                'path' => '/football',
                'component' => '/home/index',
                'icon' => 'HomeFilled',
                'sort' => 10,
                'status' => 1,
                'is_affix' => true,
            ]
        );

        $system = Menu::query()->updateOrCreate(
            ['permission' => 'menu:system'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '系统管理',
                'name' => 'system',
                'path' => '/system',
                'redirect' => '/system/accountManage',
                'icon' => 'Tools',
                'sort' => 40,
                'status' => 1,
            ]
        );

        $leagueCompetition = Menu::query()->updateOrCreate(
            ['permission' => 'menu:leagueCompetition'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '联盟管理',
                'name' => 'leagueCompetition',
                'path' => '/leagueCompetition',
                'redirect' => '/leagueCompetition/team',
                'icon' => 'Flag',
                'sort' => 20,
                'status' => 1,
            ]
        );

        $competitionTemplate = Menu::query()->updateOrCreate(
            ['permission' => 'menu:competitionTemplate'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '比赛设置',
                'name' => 'competitionTemplate',
                'path' => '/competitionTemplate',
                'component' => '/system/competitionTemplate/index',
                'icon' => 'SetUp',
                'sort' => 15,
                'status' => 1,
            ]
        );

        $honorManage = Menu::query()->updateOrCreate(
            ['permission' => 'menu:honorManage'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '荣誉管理',
                'name' => 'honorManage',
                'path' => '/honorManage',
                'component' => '/system/honorManage/index',
                'icon' => 'Trophy',
                'sort' => 16,
                'status' => 1,
            ]
        );

        $leagueTeamCompetition = $this->seedMenu($leagueCompetition, [
            'permission' => 'menu:leagueTeamCompetition', 'title' => '团体赛', 'name' => 'leagueTeamCompetition',
            'path' => '/leagueCompetition/team', 'component' => '/competition/leagueTeam/index', 'icon' => 'Trophy', 'sort' => 10,
        ]);
        $leagueCupCompetition = $this->seedMenu($leagueCompetition, [
            'permission' => 'menu:leagueCupCompetition', 'title' => '个人杯赛', 'name' => 'leagueCupCompetition',
            'path' => '/leagueCompetition/cup', 'component' => '/competition/leagueCup/index', 'icon' => 'Medal', 'sort' => 20,
        ]);
        $leagueLeagueCompetition = $this->seedMenu($leagueCompetition, [
            'permission' => 'menu:leagueLeagueCompetition', 'title' => '个人联赛', 'name' => 'leagueLeagueCompetition',
            'path' => '/leagueCompetition/league', 'component' => '/competition/leagueLeague/index', 'icon' => 'DataLine', 'sort' => 30,
        ]);

        $teamCompetition = Menu::query()->updateOrCreate(
            ['permission' => 'menu:teamCompetition'],
            [
                'parent_id' => null,
                'type' => Menu::TYPE_MENU,
                'title' => '战队管理',
                'name' => 'teamCompetition',
                'path' => '/teamCompetition',
                'redirect' => '/teamCompetition/cup',
                'icon' => 'Grid',
                'sort' => 30,
                'status' => 1,
            ]
        );

        $teamCupCompetition = $this->seedMenu($teamCompetition, [
            'permission' => 'menu:teamCupCompetition', 'title' => '杯赛管理', 'name' => 'teamCupCompetition',
            'path' => '/teamCompetition/cup', 'component' => '/competition/teamCup/index', 'icon' => 'Medal', 'sort' => 10,
        ]);
        $teamLeagueCompetition = $this->seedMenu($teamCompetition, [
            'permission' => 'menu:teamLeagueCompetition', 'title' => '联赛管理', 'name' => 'teamLeagueCompetition',
            'path' => '/teamCompetition/league', 'component' => '/competition/teamLeague/index', 'icon' => 'DataLine', 'sort' => 20,
        ]);
        $teamKofCompetition = $this->seedMenu($teamCompetition, [
            'permission' => 'menu:teamKofCompetition', 'title' => '拳皇管理', 'name' => 'teamKofCompetition',
            'path' => '/teamCompetition/kof', 'component' => '/competition/teamKof/index', 'icon' => 'Aim', 'sort' => 30,
        ]);
        $teamMemberManage = $this->seedMenu($teamCompetition, [
            'permission' => 'menu:teamMemberManage', 'title' => '成员管理', 'name' => 'teamMemberManage',
            'path' => '/teamCompetition/members', 'component' => '/team/memberManage/index', 'icon' => 'UserFilled', 'sort' => 40,
        ]);

        $account = Menu::query()->updateOrCreate(
            ['permission' => 'menu:accountManage'],
            [
                'parent_id' => $system->id,
                'type' => Menu::TYPE_MENU,
                'title' => '用户管理',
                'name' => 'accountManage',
                'path' => '/system/accountManage',
                'component' => '/system/accountManage/index',
                'icon' => 'UserFilled',
                'sort' => 10,
                'status' => 1,
            ]
        );

        $role = Menu::query()->updateOrCreate(
            ['permission' => 'menu:roleManage'],
            [
                'parent_id' => $system->id,
                'type' => Menu::TYPE_MENU,
                'title' => '角色管理',
                'name' => 'roleManage',
                'path' => '/system/roleManage',
                'component' => '/system/roleManage/index',
                'icon' => 'Avatar',
                'sort' => 20,
                'status' => 1,
            ]
        );

        $menu = Menu::query()->updateOrCreate(
            ['permission' => 'menu:menuMange'],
            [
                'parent_id' => $system->id,
                'type' => Menu::TYPE_MENU,
                'title' => '菜单管理',
                'name' => 'menuMange',
                'path' => '/system/menuMange',
                'component' => '/system/menuMange/index',
                'icon' => 'Grid',
                'sort' => 30,
                'status' => 1,
            ]
        );

        $leagueManage = $this->seedMenu($system, [
            'permission' => 'menu:leagueManage', 'title' => '联盟管理', 'name' => 'leagueManage',
            'path' => '/system/leagueManage', 'component' => '/system/leagueManage/index', 'icon' => 'Flag', 'sort' => 40,
        ]);
        $teamManage = $this->seedMenu($system, [
            'permission' => 'menu:teamManage', 'title' => '战队管理', 'name' => 'teamManage',
            'path' => '/system/teamManage', 'component' => '/system/teamManage/index', 'icon' => 'Grid', 'sort' => 50,
        ]);

        $this->seedButtons($account, ['add' => '新增用户', 'edit' => '编辑用户', 'delete' => '删除用户', 'change' => '切换状态', 'reset' => '重置密码', 'assignRole' => '分配角色', 'import' => '导入', 'export' => '导出']);
        $this->seedButtons($role, ['add' => '新增角色', 'edit' => '编辑角色', 'delete' => '删除角色', 'assignPermission' => '分配权限']);
        $this->seedButtons($menu, ['add' => '新增菜单', 'edit' => '编辑菜单', 'delete' => '删除菜单']);
        $this->seedButtons($competitionTemplate, ['add' => '新增模板', 'edit' => '编辑模板', 'delete' => '删除模板']);
        $this->seedButtons($honorManage, ['add' => '录入历史荣誉', 'edit' => '编辑历史荣誉', 'delete' => '删除历史荣誉']);
        foreach ([$leagueTeamCompetition, $leagueCupCompetition, $leagueLeagueCompetition, $teamCupCompetition, $teamLeagueCompetition, $teamKofCompetition] as $competitionMenu) {
            $this->seedButtons($competitionMenu, [
                'detail' => '比赛详情', 'add' => '新增比赛', 'edit' => '编辑比赛', 'delete' => '删除比赛', 'finish' => '结束并颁奖',
            ]);
        }
        $this->seedButtons($teamMemberManage, ['review' => '审批申请', 'setManager' => '设置管理']);
        $this->seedButtons($leagueManage, ['add' => '新增联盟', 'edit' => '编辑联盟', 'delete' => '删除联盟']);
        $this->seedButtons($teamManage, ['add' => '新增战队', 'edit' => '编辑战队', 'delete' => '删除战队']);

        return Menu::query()->get();
    }

    private function seedMenu(Menu $parent, array $data): Menu
    {
        return Menu::query()->updateOrCreate(
            ['permission' => $data['permission']],
            [
                'parent_id' => $parent->id,
                'type' => Menu::TYPE_MENU,
                'title' => $data['title'],
                'name' => $data['name'],
                'path' => $data['path'],
                'component' => $data['component'],
                'icon' => $data['icon'],
                'sort' => $data['sort'],
                'status' => 1,
            ]
        );
    }

    private function seedButtons(Menu $parent, array $buttons): void
    {
        $sort = 10;
        foreach ($buttons as $code => $title) {
            Menu::query()->updateOrCreate(
                ['permission' => 'button:'.$parent->name.':'.$code],
                [
                    'parent_id' => $parent->id,
                    'type' => Menu::TYPE_BUTTON,
                    'title' => $title,
                    'name' => null,
                    'path' => null,
                    'component' => null,
                    'icon' => match ($code) {
                        'add' => 'CirclePlus',
                        'edit' => 'EditPen',
                        'delete' => 'Delete',
                        'detail' => 'View',
                        'finish' => 'Trophy',
                        'change' => 'Switch',
                        'reset' => 'RefreshLeft',
                        'assignRole', 'assignPermission' => 'Key',
                        'import' => 'Upload',
                        'export' => 'Download',
                        default => 'Operation',
                    },
                    'button_code' => $code,
                    'sort' => $sort,
                    'status' => 1,
                    'is_keep_alive' => false,
                ]
            );
            $sort += 10;
        }
    }
}
