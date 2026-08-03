<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompetitionMenuSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $menus = (new DatabaseSeeder)->seedMenus();
        $permissions = $menus->pluck('permission')->all();
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        Role::findByName('管理员', 'api')->syncPermissions($permissions);
        Role::findByName('战队队长', 'api')->syncPermissions([
            'menu:home', 'menu:teamCompetition', 'menu:teamMemberManage',
            'button:teamMemberManage:review', 'button:teamMemberManage:setManager',
        ]);
        Role::findByName('战队管理', 'api')->syncPermissions([
            'menu:home', 'menu:teamCompetition', 'menu:teamMemberManage',
            'button:teamMemberManage:review',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
