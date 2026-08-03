<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为现有数据表补充业务字段注释，不重建表或清空数据。
     */
    public function up(): void
    {
        $this->updateComments($this->comments());
    }

    /**
     * 回滚本迁移添加的字段注释，字段定义和数据保持不变。
     */
    public function down(): void
    {
        $comments = [];

        foreach ($this->comments() as $table => $columns) {
            $comments[$table] = array_fill_keys(array_keys($columns), '');
        }

        $this->updateComments($comments);
    }

    /**
     * 返回需要写入数据库的业务字段中文注释。
     *
     * 主键、语义明确的外键 ID、时间戳和纯关联表字段不重复添加注释。
     */
    private function comments(): array
    {
        return [
            'users' => [
                'nickname' => '用户显示昵称，成员账号通常使用战队-名字格式',
                'username' => '用户唯一登录名',
                'email' => '用户联系邮箱，可为空',
                'email_verified_at' => '邮箱验证完成时间',
                'phone' => '用户联系电话，可为空',
                'avatar' => '用户头像资源地址',
                'status' => '账号状态：1 启用，0 禁用',
                'password' => '加密后的登录密码',
                'remember_token' => 'Laravel 记住登录状态使用的凭证',
            ],
            'password_resets' => [
                'email' => '申请重置密码的用户邮箱',
                'token' => '一次性密码重置凭证',
                'created_at' => '重置凭证创建时间',
            ],
            'failed_jobs' => [
                'uuid' => '失败任务全局唯一标识',
                'connection' => '任务使用的队列连接',
                'queue' => '任务所在队列名称',
                'payload' => '失败任务原始载荷',
                'exception' => '任务失败异常堆栈',
                'failed_at' => '任务失败时间',
            ],
            'permissions' => [
                'name' => '权限标识名称',
                'guard_name' => '权限所属认证守卫',
            ],
            'roles' => [
                'name' => '角色名称',
                'guard_name' => '角色所属认证守卫',
            ],
            'model_has_permissions' => [
                'model_type' => '被授权模型类型',
            ],
            'model_has_roles' => [
                'model_type' => '被授予角色的模型类型',
            ],
            'menus' => [
                'type' => '节点类型：menu 菜单，button 按钮权限',
                'title' => '菜单或按钮显示名称',
                'name' => '前端路由名称',
                'path' => '前端访问路径',
                'component' => '前端页面组件路径',
                'redirect' => '菜单默认重定向路径',
                'icon' => 'Element Plus 图标名称',
                'permission' => '后端权限唯一标识',
                'button_code' => '页面按钮权限编码',
                'sort' => '同级节点显示顺序',
                'status' => '启用状态：1 启用，0 禁用',
                'is_link' => '外部链接地址，空字符串表示内部页面',
                'is_hide' => '是否在侧边栏隐藏',
                'is_full' => '是否使用全屏页面布局',
                'is_affix' => '是否固定在标签栏',
                'is_keep_alive' => '是否缓存页面组件',
            ],
            'leagues' => [
                'name' => '联盟名称',
                'status' => '联盟状态：1 启用，0 禁用',
            ],
            'teams' => [
                'name' => '战队名称，同一联盟内唯一',
                'status' => '战队状态：1 启用，0 禁用',
            ],
            'competitions' => [
                'organizer_type' => '赛事组织范围：league 联盟级，team 战队级',
                'type' => '赛事类型：team 团体赛，cup 杯赛，league 联赛，kof 拳皇赛',
                'name' => '赛事名称',
                'season' => '赛事届次或赛季名称',
                'format' => '比赛模式：group_knockout 小组加淘汰，knockout 直接淘汰，round_robin 循环赛',
                'status' => '赛事状态：registration 报名中，in_progress 进行中，knockout 淘汰赛，awaiting_awards 待颁奖，completed 已结束',
                'registration_deadline' => '报名截止时间',
                'registration_limit' => '最大报名名额',
                'group_count' => '小组赛分组数量',
                'starts_at' => '计划开始时间',
                'ended_at' => '比赛实际结束时间',
                'awarded_at' => '完成颁奖时间',
                'notes' => '赛事补充说明',
            ],
            'competition_squads' => [
                'name' => '拳皇赛临时组团名称',
            ],
            'competition_entries' => [
                'entry_type' => '参赛对象类型：user 用户，team 战队，squad 临时组团',
                'seed' => '抽签或编排使用的种子顺位',
                'status' => '报名状态：registered 已报名',
            ],
            'competition_stages' => [
                'type' => '阶段类型：group 小组赛，knockout 淘汰赛，league 联赛',
                'name' => '阶段显示名称',
                'sort' => '阶段执行和展示顺序',
                'status' => '阶段状态：pending 待开始，in_progress 进行中，completed 已结束',
            ],
            'competition_groups' => [
                'name' => '小组名称，如 A组',
                'sort' => '小组展示顺序',
            ],
            'competition_matches' => [
                'round_label' => '轮次显示名称，如半决赛或第 3 轮',
                'round_number' => '轮次排序编号',
                'sequence' => '同轮比赛排列顺序',
                'home_score' => '主队或主选手得分',
                'away_score' => '客队或客选手得分',
                'status' => '报分状态：pending 未完赛，completed 完赛',
                'scheduled_at' => '计划比赛时间',
            ],
            'competition_honors' => [
                'rank' => '最终名次：1 冠军，2 亚军，3 季军，4 殿军',
                'title' => '名次中文称号',
                'owner_name' => '获奖对象名称快照，避免改名影响历史',
            ],
            'team_staff' => [
                'role' => '战队职务：captain 队长，manager 管理',
            ],
            'team_applications' => [
                'type' => '申请类型：join 加入或转队，guest 申请嘉宾',
                'status' => '审批状态：pending 待审批，approved 通过，rejected 拒绝，cancelled 取消',
                'reviewed_at' => '审批完成时间',
                'review_note' => '队长或管理填写的审批备注',
            ],
        ];
    }

    /**
     * 根据当前数据库驱动更新注释；不存在的表或字段会安全跳过。
     */
    private function updateComments(array $comments): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->updateMysqlComments($comments);

            return;
        }

        if ($driver === 'pgsql') {
            $this->updatePostgresComments($comments);
        }
    }

    /**
     * 读取 MySQL 字段的现有完整定义，仅通过 MODIFY COLUMN 替换 COMMENT。
     */
    private function updateMysqlComments(array $comments): void
    {
        $pdo = DB::connection()->getPdo();

        foreach ($comments as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $tableName = $this->quoteMysqlIdentifier($table);
            $definitions = collect(DB::select("SHOW FULL COLUMNS FROM {$tableName}"))->keyBy('Field');
            $clauses = [];

            foreach ($columns as $column => $comment) {
                $definition = $definitions->get($column);

                if (! $definition) {
                    continue;
                }

                $clauses[] = $this->mysqlColumnClause($column, $definition, $pdo->quote($comment));
            }

            if ($clauses !== []) {
                DB::statement("ALTER TABLE {$tableName} ".implode(', ', $clauses));
            }
        }
    }

    /**
     * 在保留类型、字符集、空值、默认值和附加属性的前提下生成字段修改语句。
     */
    private function mysqlColumnClause(string $column, object $definition, string $quotedComment): string
    {
        $clause = 'MODIFY COLUMN '.$this->quoteMysqlIdentifier($column).' '.$definition->Type;

        if ($definition->Collation) {
            $clause .= ' COLLATE '.$this->quoteMysqlIdentifier($definition->Collation);
        }

        $clause .= $definition->Null === 'YES' ? ' NULL' : ' NOT NULL';

        if ($definition->Default !== null) {
            $default = (string) $definition->Default;
            $clause .= preg_match('/^(CURRENT_TIMESTAMP)(\(\d+\))?$/i', $default)
                ? ' DEFAULT '.$default
                : ' DEFAULT '.DB::connection()->getPdo()->quote($default);
        } elseif ($definition->Null === 'YES') {
            $clause .= ' DEFAULT NULL';
        }

        $extra = trim(str_ireplace('DEFAULT_GENERATED', '', (string) $definition->Extra));

        if ($extra !== '') {
            $clause .= ' '.$extra;
        }

        return $clause.' COMMENT '.$quotedComment;
    }

    /**
     * 使用 PostgreSQL 原生语法更新字段注释。
     */
    private function updatePostgresComments(array $comments): void
    {
        $pdo = DB::connection()->getPdo();

        foreach ($comments as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $comment) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $tableName = $this->quotePostgresIdentifier($table);
                $columnName = $this->quotePostgresIdentifier($column);
                DB::statement("COMMENT ON COLUMN {$tableName}.{$columnName} IS ".$pdo->quote($comment));
            }
        }
    }

    /**
     * 转义 MySQL 标识符，避免表名或字段名破坏 DDL。
     */
    private function quoteMysqlIdentifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    /**
     * 转义 PostgreSQL 标识符，避免表名或字段名破坏 DDL。
     */
    private function quotePostgresIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
};
