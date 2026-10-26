# front-end · 青岚谷售票系统（Vue 3）

景区售票系统前端：Vue 3（组合式 API）+ Vite 6 + Vue Router 4 + Pinia 2，不使用 UI 组件库；数据可视化图表为手写 SVG 组件（`src/components/charts/`）。

## 关键约定

| 模块 | 说明 |
| --- | --- |
| `src/api/index.js` | 统一 `api()` 封装：`/api` 前缀、Session Cookie 随请求携带、统一解析 `{code, message, data}`、异常抛出带 status 的 Error |
| `src/router/index.js` | 路由表含 `meta.roles`；`beforeEach` 守卫按登录角色限制各端页面 |
| `src/App.vue` | 顶部导航按登录角色渲染（游客 / 票务员 / 管理员三套菜单） |
| `src/stores/` | `auth`（登录态探测与镜像）、`ticketing`（游客端公开数据缓存） |
| `src/pages/` | 游客端页面 + `operator/`（票务员 4 页）+ `admin/`(管理端 5 页) |
| `vite.config.js` | 开发端口 5173，`/api` 代理到后端 `http://127.0.0.1:8000` |

## 常用命令

```bash
npm install     # 安装依赖
npm run dev     # 开发服务器（http://localhost:5173）
npm run build   # 生产构建（输出 dist/）
npm run preview # 预览构建产物
```

页面与功能入口清单见仓库根目录 `README.md`「主要功能入口」。
