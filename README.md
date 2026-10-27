# 青岚谷景区售票系统

基于 **Laravel 12 + Vue 3** 前后端分离实现的景区售票与票务管理系统，覆盖「游客在线购票 → 票务员检票核销 / 窗口售票 → 管理员额度与退票审核 → 统计看板」的完整业务闭环（需求数据说明见 `docs/`）。

- **游客端**：注册登录、景区与票种浏览、在线购票（模拟支付出票、二维码门票）、园内项目购买（缆车 / 玻璃桥 / 游船等项目票，需持当日门票）、我的订单与门票、在线退票申请（含撤回）、个人中心
- **票务员端**：票务工作台、窗口代客售票（登记现金 / 扫码）、检票核销（一个入口通吃入园门票码与园内项目核销码，防重复使用）、我的售票记录
- **管理员端**：工作台指标卡 + 统计图表看板、景区与票种管理、园内项目管理、每日售票额度管理、订单 / 门票检索、退票审核（通过自动回补额度）、用户管理

---

## 一、运行环境

| 组件 | 版本要求 | 说明 |
| --- | --- | --- |
| PHP | ≥ 8.2 | 需扩展：pdo_mysql、mbstring、openssl、curl、session |
| Composer | 2.x | PHP 依赖安装 |
| Node.js | ≥ 20（建议 22 LTS） | 前端构建与开发服务器 |
| npm | ≥ 10 | 与 Node 配套 |
| MySQL | 8.x | 字符集 utf8mb4 |

> 数据库表结构由 Laravel 迁移（migrations）生成，无需手工导入 SQL，全部数据来源见「四、数据库初始化」。

## 二、目录结构

```
laravel/
├─ back-end/                 # Laravel 12 后端（MVC 分层）
│  ├─ app/Http/Controllers/  # 控制器：Auth / Area / Order / Ticket / Refund / Admin
│  ├─ app/Support/           # 业务支撑：TicketingService(库存事务) / Formatter(响应映射) / ApiException
│  ├─ app/Models/            # Eloquent 模型（含 $fillable 批量赋值保护与字段类型转换）
│  ├─ database/migrations/   # 数据库迁移（主数据表 + 业务表）
│  ├─ database/seeders/      # DatabaseSeeder：演示数据（景区 / 票种 / 额度 / 账号 / 订单）
│  ├─ routes/api.php         # API 路由（分组 + 中间件）
│  └─ storage/logs           # 运行日志
├─ front-end/                # Vue 3 前端（组合式 API）
│  ├─ src/api/index.js       # 统一 API 封装（统一响应体 {code,message,data} 处理）
│  ├─ src/pages/             # 页面：游客端 + operator/ + admin/ 子目录
│  ├─ src/components/        # 组件（含手写 SVG 图表 Line/Bar/DonutChart、分页 Pager）
│  ├─ src/router/index.js    # 路由（meta.roles 角色守卫）
│  └─ src/stores/            # Pinia：auth（登录态）/ ticketing（游客端公开数据）
└─ docs/                     # 需求文档、设计文档、课程任务书
```

## 三、安装步骤

### 1. 后端（back-end）

```bash
cd back-end

# 1) 安装依赖
composer install

# 2) 准备环境配置（可复制示例后按需修改）
cp .env.example .env
php artisan key:generate

# 3) 编辑 .env 中的数据库连接
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=scenic_ticket
#    DB_USERNAME=<你的数据库账号>
#    DB_PASSWORD=<你的数据库密码>

# 4) 建库（若无权限可交由 DBA 执行）
#    CREATE DATABASE scenic_ticket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 5) 初始化数据（迁移 + 演示种子数据，见下章）
php artisan migrate --seed

# 6) 启动（演示模式，端口 8000）
php artisan serve --host=127.0.0.1 --port=8000
```

### 2. 前端（front-end）

```bash
cd front-end

# 1) 安装依赖
npm install

# 2) 启动开发服务器（端口 5173，/api 自动代理到后端 8000）
npm run dev

# 发布生产构建
npm run build
```

> 前端通过 Vite 快捷代理访问 `/api`（见 `vite.config.js`），开发时无需处理跨域 Cookie；生产部署时由 Nginx 等将前端静态资源与 `/api` 反代到同一域名。

> 一键复跑演示数据：`php artisan migrate:fresh --seed`（会清空并重建全部业务数据，慎用于生产）。

## 四、数据库初始化与默认账号

`php artisan migrate --seed` 会自动完成：

1. **迁移建表**：用户 / 会话（框架表）、主数据表（景区、票种、员工）、业务表（开放日、每日额度、订单、门票、退款记录）、园内项目三表（项目主数据、项目订单、项目票，2026_01_01_000004 迁移）
2. **种子数据**（`DatabaseSeeder`，与需求文档口径一致）
   - 景区 1 家：青岚谷风景区（默认园区日容量 5000）
   - 票种 7 种：成人票 ¥110 / 学生票 ¥80 / 长者票 ¥70 / 儿童票 ¥60 / 家庭套票 ¥260（2 大 1 小）/ 团体票 ¥90（10 张起）/ 军人优待票 ¥40（各含适用说明）
   - 园内项目 5 种：索道缆车 ¥80 / 高空玻璃桥 ¥50 / 峡谷游船 ¥60 / 人工讲解 ¥30 / 观光车 ¥40（不限量、需持当日门票、售出不退），并附 1 张今日未核销的缆车项目票（PI 前缀码，可直接演示项目口核销）
   - 开放日：未来 7 天（每天 4 票种额度 quota 已设置，并预置部分已售量模拟真实余量）+ 昨日 1 天
   - 演示账号：见下表
   - 演示订单：1 笔「已支付」（明日学生票 × 2，含 2 张未检票门票）、1 笔「已使用」（昨日成人票 × 2，2 张门票均已核销）、1 笔今日园内项目订单（未核销缆车票）

**默认账号**（均可登录验证）：

| 角色 | 用户名 | 密码 | 用途 |
| --- | --- | --- | --- |
| 系统管理员 | `admin` | `admin123` | 管理端全部功能与统计看板 |
| 票务员 | `window01` | `window123456` | 工作台 / 窗口售票 / 检票核销 |
| 游客 | `demo` | `demo123456` | 线上购票 / 退票（也可以在游客端自行注册新账号） |
| 窗口散客 | （无需登录） | （无需登录） | 票务员售票时会自动为其创建档案（walkin_*），不提供登录 |

> 种子数据使用「运行当时」的相对日期（今日 / 昨日 / 明日等），全部账号与数据均可随时通过 `migrate:fresh --seed` 重置复现。

## 五、主要功能入口

前端启动后访问 http://localhost:5173（后端 http://127.0.0.1:8000）。

| 端 | 页面路径 | 登录角色 | 主要功能 |
| --- | --- | --- | --- |
| 游客端 | `/` 首页 | 无需登录 | 景区宣传、票种与余量、购票流程、游园公告 |
| 游客端 | `/buy` | 游客 | 选日期 / 票种 / 数量 → 提交订单 → 模拟支付出票 |
| 游客端 | `/extras` | 游客 | 园内项目购买：选项目 / 游玩日期 / 数量 → 模拟支付出票（PI 核销码）；我的项目票列表 |
| 游客端 | `/orders` | 游客 | 订单列表（状态筛选、分页）、门票二维码、退票申请 / 撤回、购票概要统计 |
| 游客端 | `/account` | 已登录 | 个人信息、修改密码 |
| 登录 | `/login` | — | 登录 / 注册（自助注册默认游客角色） |
| 票务员端 | `/operator` | 票务员 | 工作台（今日售量 / 交易额 / 待核销） |
| 票务员端 | `/operator/sell` | 票务员 | 窗口代客售票（游客姓名 + 现金 / 扫码） |
| 票务员端 | `/operator/check` | 票务员 | 输入核销码检票（存在 / 当日 / 未检票校验，防重复）；双查询：未命中门票时自动按园内项目票（PI 前缀）核销 |
| 票务员端 | `/operator/orders` | 票务员 | 本人售票记录 |
| 管理端 | `/admin` | 管理员 | 工作台指标卡 + 统计图表看板（售票趋势 / 入园量 / 票种占比 / 渠道收入 / 退款） |
| 管理端 | `/admin/manage` | 管理员 | 票种增删改查、**园内项目增删改查**、开放日与每日额度调整（含停售） |
| 管理端 | `/admin/orders` `/admin/refunds` `/admin/users` | 管理员 | 订单 / 门票多条件检索与分页；退票审核（驳回必填意见）；用户增删改查与启停用 |

### 核心 API（统一响应体 `{code, message, data}`）

- 公开：`GET /api/area`（景区+票种+每日余量）、`GET /api/park/items`（在售园内项目）、`GET /api/enums`（枚举字典）、`POST /api/auth/register|login|logout`
- 登录：`POST /api/orders`（游客线上购票 / 票务员窗口代客，按角色自动分流）、`GET /api/orders/my|mine`、`POST /api/orders/{id}/pay|cancel`、`GET /api/tickets/order/{orderId}`、`POST /api/refunds`、`POST /api/park/orders`（园内项目购买）+ `GET /api/park/orders/my`、`GET /api/tickets/check`（双查询核销）
- 管理员：`GET /api/users` / `…/ticket-types` / `…/park-items` / `…/open-days` / `GET /api/statistics/overview`，以及管理端 CRUD / 审核 / 额度调整接口

> 登录态使用 Session（Cookie `_session`，30 分钟滑动过期）；后端对受限接口通过中间件校验登录态与角色（`auth.session` / `auth.role:admin|operator|visitor`）。

## 六、测试数据与演示脚本（建议的验证顺序）

1. **管理员**：登录 → 「订单门票」看种子演示订单 → 「票种管理」新增并删除一个票种 → 「园内项目」新增 / 编辑 / 删除一个项目 → 「额度管理」调整次日成人票额度并停售某票种 → 「退票审核」处理待审核记录（驳回时必填意见） → 工作台看板看图表更新
2. **游客**：注册新账号（或用 demo）→ 首页选票 → 支付出票 → 我的订单看二维码 → 对未检票门票申请退票 → 「园内项目」选缆车 / 游船等，凭当日门票完成购买并看到 PI 核销码
3. **票务员**：登录 → 工作台 → 「现场售票」为游客购买当日票（现金 / 扫码）→ 「检票核销」输入核销码（重复输入会被拒绝） → 用种子缆车项目票的 PI 核销码演示项目口核销（同一入口） → 查看售票记录
4. **库存口径**：游客退票审核通过后，/admin「额度管理」或 /api/area 可见对应日期票种余量自动回补；园内项目不限量（无额度），但需持当日有效门票才能购买

## 七、研发说明

- 后端业务规则集中在 `app/Support/TicketingService.php`：下单库存校验（事务 + `lockForUpdate` 防超卖）、支付出票、取消回补、检票防重、退票审核回补；园内项目规则在 `app/Support/ParkItemService.php`（持当日报票校验、限购 5 份、项目核销、订单自动「已使用」）
- 前端登录态由后端 Session 决定（`stores/auth` 启动探测 `GET /api/auth/me`）；路由守卫按 `meta.roles` 控制各端页面访问
- 数据可视化不依赖第三方图表库，图表为手写 SVG 组件（LineChart / BarChart / DonutChart）

## 八、常见问题（FAQ）

- **页面打开 419 / 401 提示**：Session 过期（30 分钟）或 DB 中 `sessions` 表未就绪，重新登录即可
- **Composer 安装缺驱动**：确认 PHP 启用 `pdo_mysql`（`php -m | grep pdo_mysql`）
- **前端 405/跨域**：确认后端已启动在 127.0.0.1:8000，且使用 `npm run dev`（Vite 代理）访问，而不是直开 dist 文件
- **头像 / 图片加载失败**：首页为示意图（picsum 占位），演示环境可忽略

## 九、文档索引

- 需求规格说明书：`docs/景区售票系统需求文档.md`（v2.0 精简版，含园内项目 F17；第 9 章为课程任务书对照与提交物清单）
- 数据库设计：`docs/景区售票系统数据库设计.md`（v1.1，14 张表逐表结构）
- 概要与详细设计：`docs/景区售票系统课程设计-概要与详细设计.md`
- 课程设计任务书原文：`docs/课程设计任务书.txt`
