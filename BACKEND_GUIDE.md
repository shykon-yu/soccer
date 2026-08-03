# soccer_php 后端开发规范

业务接口固定采用以下调用方向：

```text
Route
  -> Controller
  -> Service
  -> Model / Database
  -> Resource
  -> ApiResponse
```

## 强制规则

1. 控制器只接收请求、调用 Service，并通过 `ApiResponse` 返回统一格式。
2. 有输入参数的接口必须在 `app/Http/Requests/Api/V1/` 建立 FormRequest，控制器只使用验证后的数据。
3. 业务查询、写入和业务判断必须放在 `app/Services/`，控制器不得直接查询 Model。
4. 可预期业务错误在 Service 中抛出 `BusinessException`，禁止返回错误数组或状态布尔值。
5. Service 不使用无意义的 `try/catch`。数据库和系统异常由全局异常处理器统一处理；只有能够补偿、转换或补充明确上下文时才捕获。
6. 业务响应数据必须经过 `app/Http/Resources/Api/V1/` 下的 Resource 包装。
7. 接口统一返回 `{ code, message, data, errors? }`，禁止控制器自行拼接 JSON。

## 注释规范

- Migration 中除主键、语义明确的外键 ID、`timestamps` 等字段外，业务字段必须使用中文 `comment()` 说明用途；状态、类型字段必须写明可选值含义。
- 已有数据库补充或修改字段注释时必须新增专用迁移并保留现有字段定义，禁止通过 `migrate:refresh` 清空业务数据；可参考 `2026_07_14_070000_add_business_column_comments.php`。
- Model 的关系、访问器、作用域和业务方法必须使用中文注释说明关系方向及业务含义。
- Service 的公开和私有方法必须说明业务职责；涉及权限、事务、数量限制、级联更新等规则时必须在注释中明确。
- Controller 方法必须说明接口职责，复杂接口还应说明调用 Service 后返回的主要资源。
- 注释用于解释业务规则、设计原因和副作用，禁止逐行翻译代码、重复方法名或添加“查询数据”“返回结果”等无信息量注释。
- 修改业务规则时必须同步更新相关代码注释和本文档，避免注释与实现不一致。

## 当前模块

- 登录与当前用户：`AuthController`、`AuthService`。
- 用户管理：`UserController`、`UserService`。
- 角色管理：`RoleController`、`RoleService`。
- 菜单管理：`MenuController`、`MenuService`。
- 后台首页统计：`DashboardController`、`DashboardService`。

## JWT 刷新规则

- JWT 有效期使用 `JWT_TTL`，默认 60 分钟；接口通过 `expires_in` 返回秒数。
- 可刷新窗口使用 `JWT_REFRESH_TTL`，默认 20160 分钟。
- refresh 路由固定为 `POST /api/v1/auth/refresh`，必须放在 `auth:api` 中间件外，保证已过期 token 可以进入刷新逻辑。
- refresh 必须携带当前 Bearer Token，成功后通过 `AuthTokenResource` 返回新 token、有效期和用户数据。
- 已刷新、已注销或进入黑名单的旧 token 不允许再次换取新 token，必须返回 401。
- 前端负责对并发刷新加锁；后端保持 JWT 黑名单开启，确保旧 token 在刷新后立即失效。

## 比赛域模型

- 四种赛事不建立四套重复主表，统一使用 `competitions`，`type` 取 `team/cup/league/kof`。
- `organizer_type` 取 `league/team`，并分别关联 `league_id` 或 `team_id`；联盟级允许团体赛、个人杯赛、个人联赛，战队级允许杯赛、联赛、拳皇赛。
- `competition_entries` 保存报名对象，显式使用 `user_id/team_id/squad_id`，禁止使用无外键约束的通用多态 ID。
- 拳皇临时组团使用 `competition_squads` 和 `competition_squad_members`。
- 比赛阶段使用 `competition_stages`，小组使用 `competition_groups`，比分表使用 `competition_matches`；比分状态固定为 `pending/completed`。
- 比赛创建或修改模式时只生成阶段和小组容器。报名名单最终确认后才生成对阵和比分表，防止报名人数变化导致赛程失效。
- `competition_matches.competition_id` 必须保留，所有比分查询首先按比赛 ID 过滤。
- 结束比赛必须写入 `competition_honors` 的冠军、亚军、季军、殿军，并将赛事状态改为 `completed`。
- 已颁发荣誉通过赛事 `type` 分类展示；获奖名称保存快照，避免用户或战队改名后历史荣誉变化。
- 前台赛事列表和详情必须使用登录用户数据范围：联盟赛事按 `league_memberships.league_id`，战队赛事按正式战队与 `team_guests.team_id` 的并集过滤。
- 前台只展示杯赛、联赛和拳皇赛；拳皇赛仅属于战队范围。列表使用 `ongoing/completed` 分组，`ongoing` 包含除 `completed` 外的全部流程状态。
- 前台赛事详情必须再次使用相同数据范围查询，禁止只校验比赛 ID 后返回阶段和比分。

## 比赛报名

- 个人杯赛和个人联赛使用 `competition_entries.entry_type=user`，报名用户必须处于该赛事的前台可见数据范围。
- 联盟团体赛使用 `competition_entries.entry_type=team`，只有目标战队的 `captain/manager` 可以代表战队报名。
- 所有报名必须在事务内锁定赛事记录，并校验 `status=registration`、`registration_deadline`、`registration_limit` 和重复报名。
- 个人报名接口为 `/competition/front/register-user`，战队报名接口为 `/competition/front/register-team`，控制器必须使用独立 FormRequest。
- `competition_entries` 的赛事用户、赛事战队唯一索引是最终重复报名保护，不得删除。

## 杯赛赛程与签表

- 杯赛必须设置 `knockout_size`，仅允许 `8/16/32/64`；使用新增迁移维护字段，禁止刷新数据表。
- 上线前已有杯赛通过独立数据迁移回填为 16 人签表，回滚时不得清空管理员后续确认的设置。
- 杯赛从 `registration` 切换到比赛状态时锁定当前报名名单并生成赛程；至少需要 2 名报名选手。
- `knockout` 模式按种子顺位生成完整淘汰轮次，首轮使用高低种子对位，后续轮次保留固定胜者来源。
- `group_knockout` 模式使用蛇形分组并生成组内单循环比分表，同时生成淘汰赛占位签表。
- 小组积分按胜 3、平 1、负 0 计算，依次按积分、净胜球、进球数和报名 ID 排名。
- 从小组赛切换到淘汰赛前必须保证全部小组比赛完赛；晋级者按各组相同名次轮流进入签表。
- 赛事详情 Resource 必须返回小组 `standings` 和淘汰赛 `bracket.rounds`，前端不得自行推导签位来源。

## 联盟团体赛前台聚合

- 公开 `GET /api/v1/competition/team-overview?league_id={id}` 返回联盟当前团体赛、战队积分榜、个人成绩榜和历届赛事摘要。
- 公开 `GET /api/v1/competition/team-history-detail?id={honor_event_id}` 在用户点击历届赛事时按需返回单届积分和荣誉。
- 战队积分统一由 `TeamStandingCalculator` 计算，流程服务和赛事 Resource 禁止各自维护积分规则。
- 个人成绩榜按 `competition_team_fixture_matches` 的真实队员比分汇总，排名依次比较积分、净胜分、得分和用户 ID。
- 历届赛事以 `honor_events` 为档案入口，必须同时支持关联比赛的正常完赛记录和没有比赛关联的上线前手工荣誉；总览不预计算历史积分，手工荣誉不伪造积分榜。

比赛状态统一为：

```text
registration      报名中
in_progress       正在进行
knockout          小组赛结束 / 淘汰赛开始
awaiting_awards   淘汰赛结束，等待颁奖
completed         已结束并完成颁奖
```

## 后台首页统计口径

- 联盟个数：联盟目录表中启用的联盟数量。
- 战队个数：战队目录表中启用的战队数量。
- 用户个数：用户表全部账号数量。
- 联盟用户数：`league_memberships` 的成员关系数量；同一个用户加入不同联盟时分别计数。
- 战队用户分布：按 `league_memberships.team_id` 聚合，以全部联盟成员关系为分母。

## 前台首页聚合

- 前台首页使用公开 `GET /api/v1/home`，未登录用户也可查看，不得调用受保护的后台统计接口。
- `FrontHomeController -> FrontHomeService -> FrontHomeResource` 统一返回联盟规模、进行中赛事、热门战队和最新冠军。
- 首页赛事只返回非 `completed` 的最新摘要；战队排行按正式成员关系数量计算；最新冠军只读取已完成赛事的第一名荣誉。

## 用户联盟关系

- 用户表不得保存 `league`、`team` 字段。
- `league_memberships` 表表示用户在某联盟所属的战队。
- `(user_id, league_id)` 必须建立联合唯一索引，保证一个用户在同一联盟只有一个战队。
- `(team_id, league_id)` 使用复合外键关联战队目录，保证战队属于对应联盟。
- 用户可以在不同联盟各有一条成员关系。

## 前台个人资料

- 普通登录用户修改资料必须使用 `POST /api/v1/auth/profile`，禁止复用后台用户编辑接口。
- 用户名实际发生变化时写入 `users.username_changed_at`，距离上次修改不足一年必须由 Service 抛出 `BusinessException`。
- 昵称、邮箱和手机不受用户名年度限制影响。
- 新密码为空时不修改密码；提交新密码时必须同时提交旧密码和确认密码，Service 使用 `Hash::check` 校验旧密码。
- 前端禁用用户名输入只用于体验提示，最终频率限制必须以后端数据库时间为准。

## 战队成员与嘉宾

- `league_memberships` 继续表示用户在某联盟的唯一主战队，`(user_id, league_id)` 联合唯一索引不得删除。
- 用户申请加入同联盟其他战队时按转队处理；审批通过后更新该联盟现有 `league_memberships.team_id`，不得新增第二条主战队关系。
- `team_guests` 独立保存嘉宾关系，同一个用户可以成为多个战队嘉宾，也可以跨联盟成为嘉宾。
- `team_applications` 保存加入/转队和嘉宾申请，状态固定为 `pending/approved/rejected/cancelled`，只有目标战队队长或管理可以审批。
- `team_staff` 保存战队级职务，`role` 固定为 `captain/manager`；后台数据权限必须查此表，不能仅凭全局 Spatie 角色查询所有战队。
- 一个战队最多 5 名 `manager`，由 Service 在事务内统计并限制；队长可在成员管理中逐个任免，系统管理员可在战队编辑中批量分配。
- 队长和管理必须是本队正式成员，且同一战队内两种职务互斥；系统管理的战队编辑接口必须同时返回当前队长和管理列表。
- 设置或取消职务时同步全局“战队队长”“战队管理”角色以获得后台菜单；用户仍在其他战队担任同类职务时不得移除对应全局角色。
- 战队管理只允许查看自己具有 `team_staff` 关系的战队成员、嘉宾和申请。

## 开发种子数据

- 联盟和战队目录由 `LeagueTeamSeeder` 维护，战队名称不包含“战队”后缀。
- 实况联盟成员清单位于 `database/seeders/data/shikuang_members.txt`，每行固定为 `战队-用户昵称`。
- `MemberSeeder` 使用名字作为用户名，使用完整的 `战队-名字` 作为昵称，并写入联盟成员关系表。
- 名字重复时，用户名从第二个开始依次追加 `2`、`3`、`4` 等数字。
- ID 1 固定为管理员账号 `admin`，开发密码为 `admin123456`。
- 其他名单账号开发密码统一为 `shikuang8`，角色为“队员”，默认没有后台菜单权限。
- Seeder 必须可以重复执行，不得重复创建联盟、战队或用户。
- 杯赛页面演示数据由 `DemoCupSeeder` 单独维护，使用 `php artisan db:seed --class=DemoCupSeeder` 可重复重建，不加入默认 `DatabaseSeeder`。
- 首页冠军和荣誉室四类展示数据由 `DemoHonorSeeder` 维护，使用 `php artisan db:seed --class=DemoHonorSeeder` 可重复重建。
