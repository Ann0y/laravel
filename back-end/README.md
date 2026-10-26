# back-end · 青岚谷售票系统（Laravel 12）

景区售票系统后端：提供统一响应体 `{code, message, data}` 的 REST API，Session（Cookie `_session`，30 分钟滑动过期）承载登录态，MySQL 存储数据。

## 技术要点（课程设计考核映射）

| 考核点 | 本项目实现位置 |
| --- | --- |
| 控制器与路由 | `app/Http/Controllers/`（Auth / Area / Order / Ticket / Refund / Admin）；`routes/api.php` 分组注册 |
| 数据验证 | 控制器内 `$request->validate([...], 中文提示表)`，422 返回结构化字段错误 |
| Eloquent 数据操作 | `app/Models/`（`$fillable` 批量赋值保护 + `$casts` 类型转换；关联 `with` 预载） |
| 库存防超卖 | `app/Support/TicketingService.php`：事务 + `lockForUpdate` 行级锁定 |
| 演示数据 | `database/seeders/DatabaseSeeder.php` |

## 常用命令

```bash
composer install               # 安装依赖
php artisan key:generate       # 生成应用密钥
php artisan migrate --seed     # 建表并写入演示数据
php artisan migrate:fresh --seed  # 清空重建（重置演示数据）
php artisan serve --host=127.0.0.1 --port=8000  # 本地启动
php artisan tinker             # 交互式调试
```

数据库连接配置见 `.env`（示例参照 `.env.example`）。项目整体运行说明见仓库根目录 `README.md`。
