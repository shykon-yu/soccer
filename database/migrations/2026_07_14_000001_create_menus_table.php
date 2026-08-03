<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('type', 20)->default('menu')->comment('节点类型：menu 菜单，button 按钮权限');
            $table->string('title', 80)->comment('菜单或按钮显示名称');
            $table->string('name', 120)->nullable()->comment('前端路由名称');
            $table->string('path', 255)->nullable()->comment('前端访问路径');
            $table->string('component', 255)->nullable()->comment('前端页面组件路径');
            $table->string('redirect', 255)->nullable()->comment('菜单默认重定向路径');
            $table->string('icon', 80)->nullable()->comment('Element Plus 图标名称');
            $table->string('permission', 160)->unique()->comment('后端权限唯一标识');
            $table->string('button_code', 80)->nullable()->comment('页面按钮权限编码');
            $table->unsignedInteger('sort')->default(0)->comment('同级节点显示顺序');
            $table->unsignedTinyInteger('status')->default(1)->comment('启用状态：1 启用，0 禁用');
            $table->string('is_link', 255)->default('')->comment('外部链接地址，空字符串表示内部页面');
            $table->boolean('is_hide')->default(false)->comment('是否在侧边栏隐藏');
            $table->boolean('is_full')->default(false)->comment('是否使用全屏页面布局');
            $table->boolean('is_affix')->default(false)->comment('是否固定在标签栏');
            $table->boolean('is_keep_alive')->default(true)->comment('是否缓存页面组件');
            $table->timestamps();

            $table->index(['parent_id', 'type', 'sort']);
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
