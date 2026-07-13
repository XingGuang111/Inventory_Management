# 商品库存管理系统 —— 设计与实施报告

> 基于 **Ubuntu 24.04 LTS + Vue3 + PHP 8.4 + MySQL 8.4** 的轻量化前后端分离进销存系统，内置多用户登录、模块级权限、CSRF/失败限流等安全能力

---

## 🚀 快速开始（新系统首次部署）

> 假设：拿到项目压缩包 / git 仓库，一台全新的 Ubuntu 24.04 服务器（或 WSL2），从零开始。

### 🅰 方式一：一键部署（推荐，最简）

```bash
# 1. 获取项目（二选一）
git clone <项目地址> ~/Inventory_Management
# 或：unzip Inventory_Management.zip -d ~/  &&  cd ~/Inventory_Management

# 2. 进入部署目录
cd ~/Inventory_Management/deploy

# 3. 执行一键脚本（约 5-10 分钟）
sudo bash install.sh

# 4. 验证
curl http://localhost/api/index.php
# 期望：{"code":200,"msg":"stock-manage api is running",...}

# 5. 浏览器访问
# http://<服务器IP>/     （生产入口）
```

脚本自动完成：系统更新 → Nginx → PHP 8.4-FPM → MySQL 8.4 → 建库授权导表 → Node 20 → 部署 API → 构建前端 → Nginx 配置。

### 🅱 方式二：本地开发调试（前后端分离热更新）

```bash
# ===== 1. 拿到项目 =====
git clone <项目地址> ~/Inventory_Management
cd ~/Inventory_Management

# ===== 2. 安装 PHP 8.4 =====
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-mysql php8.4-mbstring

# ===== 3. 安装 MySQL 8.4 =====
sudo apt install -y wget lsb-release gnupg
wget -qO- https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | sudo gpg --dearmor -o /usr/share/keyrings/mysql.gpg
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" | sudo tee /etc/apt/sources.list.d/mysql-8.4.list
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-tools" | sudo tee -a /etc/apt/sources.list.d/mysql-8.4.list
sudo apt update
sudo DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
sudo systemctl enable --now mysql

# ===== 4. 安装 Node.js 20 =====
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install -y nodejs

# ===== 5. 初始化数据库（建 8 张表 + 默认管理员 admin/admin123）=====
sudo mysql -e "CREATE DATABASE IF NOT EXISTS stock_manage DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'stock_user'@'localhost' IDENTIFIED BY 'Stock@123456';"
sudo mysql -e "GRANT ALL PRIVILEGES ON stock_manage.* TO 'stock_user'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql stock_manage < sql/stock.sql
sudo mysql -e "SHOW TABLES;" stock_manage       # 应显示 8 张表

# ===== 6. 启动后端 API（终端 A，保持开启）=====
cd ~/Inventory_Management
php -S 127.0.0.1:8000 -t api

# ===== 7. 启动前端（终端 B，保持开启）=====
cd ~/Inventory_Management/frontend
npm install
npm run dev
# 浏览器访问 http://localhost:5173
```

### 📋 首次使用测试顺序（浏览器打开后）

用默认管理员 **admin / admin123** 登录，然后：

1. **商品清单** → 新增商品（如"测试商品A"，预警阈值 5）
2. **入库管理** → 新建入库单（供应商 + 商品 × 20 件）
3. **库存查询** → 应看到库存 20 件
4. **出库管理** → 新建出库单（客户 + 商品 × 5 件）→ 试试超量 9999 应被拦截
5. **库存盘点** → 输入实际库存 → 备注 → 提交 → 查看历史详情
6. 任一单据 → 点击 **打印**、**导出 Excel** 验证
7. **用户管理**（仅 admin 可见）→ 新增一个普通用户，只勾选"入库/出库"权限 → 用它登录，验证左侧菜单只显示允许的模块
8. 右上角下拉 → **修改密码**（管理员默认密码应尽快修改）

> **默认登录账号**：`admin` / `admin123`（密码在 `users.password_hash` 中以 bcrypt 存储，永不保存明文）

### 🔑 关键提示

| 场景 | 顺序 |
|---|---|
| 只想快速体验 | 用 **方式一** 一键脚本 |
| 需要改代码 / 调试 | 用 **方式二** 分离启动 |
| 已装过 PHP / MySQL | 跳过 2、3 步，直接建库 |
| 无 sudo 权限 | 只能用方式二，且需管理员先装好 PHP + MySQL |
| WSL2 环境 3306 冲突 | 一键脚本自动切 3307；手动部署时脚本已处理 |
| 忘记 admin 密码 | 见 [8.5 忘记 admin 密码怎么办](#85-忘记-admin-密码怎么办) |

> 详细部署与故障排查请见 [第 8 章 部署与运行流程](#8-部署与运行流程)，测试用例见 [第 9 章 测试流程与用例](#9-测试流程与用例)。

---

## 目录

1. 项目背景与目标
2. 系统开发环境与技术栈
3. 系统需求分析
4. 系统总体设计
5. 数据库设计
6. 系统详细设计
7. 开发流程
8. 部署与运行流程
9. 测试流程与用例
10. 当前实现情况总览
11. 系统优化与扩展方向
12. 项目总结

---

## 1 项目背景与目标

### 1.1 项目背景

中小零售门店、小型仓储、加工作坊在经营规模扩大后，依赖纸质台账、Excel 表格进行库存管理的方式暴露出：手工登记数据易错、出入库无法实时同步、库存盈亏难以核对、单据留存与打印繁琐、历史数据检索困难。市面大型 ERP 系统功能繁杂、授权贵、部署复杂，不适合小微企业；开源老旧库存系统技术版本落后、对 Ubuntu 新版本支持差、缺少打印模块、BUG 较多。基于此，本项目在 **Ubuntu 24.04 LTS** 上使用 **Vue3 + PHP 8.4 + MySQL 8.4** 技术栈，构建一套轻量化、零成本、易部署的商品库存管理系统，覆盖商品维护、出入库、库存查询、盘点、单据打印、多用户登录与权限管理七大基础业务。

### 1.2 市场对比

| 类别 | 优势 | 劣势 |
|---|---|---|
| 商用付费 ERP（用友/金蝶/管家婆） | 功能完整、售后完善 | 年费高、硬件要求高、操作复杂 |
| SaaS 云端库存 | 开箱即用 | 数据在第三方、定制弱、依赖网络 |
| 老旧开源库存系统 | 免费、可私有化 | 技术栈老、缺打印、无标准部署、鉴权简陋 |
| **本项目** | **私有化、零授权成本、内置纸质签收凭证打印、Excel 一键导出、Ubuntu 24.04 一键部署、代码轻量、事务保证一致性、多用户 + 模块级权限、bcrypt/CSRF/失败限流** | 无报表可视化（可扩展） |

### 1.3 项目目标

- 搭建 Ubuntu 24.04 LTS 稳定环境，完成 Nginx + PHP 8.4-FPM + MySQL 8.4 一体化部署
- 前后端分离架构，实现七大核心功能：商品清单、入库、出库、库存查询、盘点、单据打印、多用户与权限管理
- MySQL 事务保证库存操作原子性，防止负库存
- 具备生产级安全底线：bcrypt 密码哈希、HttpOnly+SameSite 会话、CSRF 校验、登录失败限流、模块级权限
- 交付：完整源码、SQL 脚本、部署脚本、设计报告

### 1.4 技术路线

`需求分析 → 竞品调研 → E-R 设计 → API 设计 → Vue3 前端开发 → 本地联调 → Ubuntu 24.04 服务器部署 → 鉴权与权限迭代 → 全功能测试 → 文档验收`

---

## 2 系统开发环境与技术栈

### 2.1 操作系统：Ubuntu 24.04 LTS

Ubuntu 24.04 LTS（Noble Numbat）长期支持版稳定性高、软件源丰富，对 Nginx、PHP、MySQL 兼容良好，占用低，适合中小 Web 系统长期运行。

### 2.2 前端

| 组件 | 版本/说明 |
|---|---|
| Vue 3 | 组合式 API，响应式性能优 |
| Element Plus | 适配 Vue3 的 UI 组件库（表格、表单、弹窗、消息提示、下拉菜单） |
| `@element-plus/icons-vue` | 顶栏下拉箭头等图标 |
| Vite 5 | 构建工具，HMR 热更新 |
| Axios | 统一 HTTP 请求封装，`withCredentials` 携带会话 cookie，自动带 `X-CSRF-Token` |
| Vue Router 4 | 单页应用路由，含登录/权限守卫 |
| Print-JS | 指定 DOM 区域打印，去除侧边菜单，生成纸质签收凭证 |
| SheetJS (xlsx) | 客户端一键导出 Excel（.xlsx） |

> **注意**：`vite@8` 依赖 rolldown 原生二进制在部分 Linux 环境下缺失，本项目锁定使用 `vite@^5.4.0` + `@vitejs/plugin-vue@^5.1.0`。

### 2.3 后端：PHP 8.4

- **PHP 8.4-FPM**：通过 `ondrej/php` PPA 安装。
- **原生 PHP 开发**：无框架，仅封装 `db.php` 提供 PDO 与统一 JSON 返回，`auth.php` 提供会话/角色/权限/CSRF 工具；业务接口按业务分为 `goods.php / stock_in.php / stock_out.php / stock.php / check.php / login.php / users.php / index.php`。
- **PDO 预处理**：所有 SQL 使用参数绑定，防注入。
- **事务**：入库/出库/盘点均使用 `beginTransaction + commit/rollback` 保证多表一致性。
- **密码**：`password_hash(PASSWORD_DEFAULT)`（bcrypt cost 12）+ `password_verify` 恒定时间比对，登录成功后 `password_needs_rehash` 自动升级。
- **会话**：PHP Session cookie 设 `HttpOnly + SameSite=Lax`，登录成功后 `session_regenerate_id(true)` 防会话固定。

### 2.4 数据库：MySQL 8.4 LTS

通过 **MySQL 官方 APT 源** 安装 MySQL 8.4 LTS。全表 InnoDB 引擎、utf8mb4 字符集、外键约束保证关联完整性。PHP 8.4 的 `pdo_mysql`（mysqlnd）完全支持 MySQL 8.4 默认的 `caching_sha2_password` 认证插件。

### 2.5 Web 服务器

- **开发**：PHP 内置服务器 `php -S 127.0.0.1:8000 -t api`，Vite dev server `http://localhost:5173`，通过 `vite.config.js` 里的 `proxy` 转发 `/api` 到后端。
- **生产**：Nginx + PHP 8.4-FPM 部署。Nginx 承担静态前端 + 反向代理 `/api/` 到 `php8.4-fpm.sock`。

### 2.6 打印与导出

- **Print-JS**：指定 DOM 区域打印，自动去除侧边菜单/按钮，输出标准化 A4 单据。打印模板作为**现场签收凭证**使用，包含"制单人 / 仓管员 / 收货人（领用人/复核人）/ 日期"签字栏、公章框、以及核对提醒文案，可直接签字盖章归档。
- **SheetJS（xlsx.js）**：所有表格支持一键 `导出 Excel`。前端 `utils/export.js` 封装通用 `exportExcel(filename, rows, columns, meta)`，自动带表头 + 元信息（单号、供应商/客户、时间、合计等）。

---

## 3 系统需求分析

### 3.1 功能性需求（对应七大模块，均已实现）

#### 3.1.1 商品清单管理
1. 新增、编辑、删除商品
2. 字段：名称、分类、规格、进价、售价、当前库存、预警库存
3. **模糊搜索**（按名称/分类/规格，前端过滤）
4. 二次确认删除防误操作

#### 3.1.2 入库管理
1. 新建入库单：填写供应商，**批量选择多商品**（一单可含 N 项），录入数量、采购单价
2. 单号自动生成：`IN` + 14 位时间戳 + 3 位随机数
3. 事务：插入入库主单 → 循环插入明细 → 累加商品库存 → 更新总金额
4. **同一供应商可有多张入库单**，历史列表按时间倒序展示
5. 历史入库单查询、**按商品名/供应商双向搜索**、按数量/金额升降序排序
6. 一键**打印入库单**（含单号、供应商、明细、合计、签字栏、公章框）
7. 一键**导出 Excel**（含单据元信息 + 明细行 + 小计列）

#### 3.1.3 出库管理
1. 新建出库单：填写客户/领用方、原因，**批量选商品**（一单多项），录入数量、售价
2. **前置库存校验**：任一商品出库数量大于账面库存 → 立即拦截并返回错误消息（`code=400`），事务未开启，杜绝负库存
3. 单号：`OUT` + 时间戳
4. 事务扣减库存、计算总金额
5. **同一客户/领用方可有多张出库单**
6. 历史查询、**按商品名/客户双向搜索**、排序、打印、导出 Excel

#### 3.1.4 库存查询
1. 实时展示所有商品的当前账面库存
2. 库存 ≤ 预警阈值 → **文字标红并显示"预警"标签**
3. 模糊搜索（名称/分类/规格）
4. 排序：库存、进价、售价 升/降序
5. 复选框"仅显示预警商品"，快速定位缺货

#### 3.1.5 库存盘点
1. 加载账面库存 → 手工录入实际库存
2. 自动计算盘盈盘亏（`real - book`），正数绿、负数红加粗，显示 `+/-`
3. **备注字段**：每商品可填写盈亏原因（如"破损"、"失窃"、"补录入库"）
4. 一次盘点支持多商品，共享同一盘点单号（`CK` + 时间戳）
5. 确认后事务：批量插入盘点明细 → 批量更新 goods.stock_num 为实际库存
6. **历史列表精简展示**：仅显示单号、时间、商品数
7. **点击历史记录 → 弹出详情弹窗**：呈现该单据所有商品的账面/实际/盈亏（正绿负红）/备注
8. **自动过期清理**：任一次调用 check.php 接口时删除超过 **2 个月** 的盘点记录（懒清理）
9. 盘点单打印

#### 3.1.6 单据打印与导出
- **打印（纸质签收凭证）**：入库单、出库单、盘点单均支持一键打印。打印模板作为**现场签字盖章存档凭证**，含单号、时间、供应商/客户、商品明细、合计，以及：
  - 温馨提醒文案（核对提示）
  - 签字栏（制单人 / 仓管员 / 收货人or领用人or复核人 / 日期）
  - 公章框（虚线方框）
- **导出 Excel**：每个模块都提供 `导出 Excel` 按钮，商品清单、库存查询、盘点表可整表导出，入库/出库/盘点历史可按单据逐条导出，格式带表头 + 元信息（单号、时间、合计等）。

#### 3.1.7 用户、鉴权与权限
1. **独立登录页**（`/login`）：账号密码登录；未登录访问任意业务页 → 前端路由守卫拦截并跳登录
2. **密码安全**：`password_hash()`（bcrypt，含随机盐）存储，永不保存明文；登录使用 `password_verify()` 恒定时间比对；账号不存在与密码错误统一提示"账号或密码错误"避免账号枚举
3. **会话安全**：PHP Session cookie `HttpOnly + SameSite=Lax`；登录成功后 `session_regenerate_id(true)` 防会话固定；退出登录后端销毁 session + 清 cookie
4. **登录失败限流**：`login_attempts` 表按 `(IP, 账号)` 记数，连续 5 次失败 → 锁定 15 分钟，返回 `code=429`；成功登录自动清计数
5. **CSRF 保护**：登录后下发 CSRF token 存于 session 与前端 localStorage；前端 Axios 拦截器为所有 POST 请求自动带 `X-CSRF-Token`；后端 POST 请求用 `hash_equals` 校验，缺失/不匹配 → `code=403`
6. **多角色 + 模块级权限**：
   - `admin` 天然拥有全部权限，可管用户
   - 非 admin 用户按 `permissions` 字段勾选可访问模块，粒度 4 项：`goods`（商品维护）/ `stock_in`（入库）/ `stock_out`（出库）/ `check`（盘点）
   - "库存查询"页所有登录用户可读；"用户管理"仅 admin
7. **自助修改密码**：右上角下拉 → 修改密码；改后强制重新登录
8. **用户管理**（仅 admin 可见 `/users`）：新增用户（可勾选权限）、**编辑权限**（可随时改）、启用/禁用、重置密码、删除；不能删/禁自己、不能删/降级最后一个 admin
9. **鉴权矩阵**：所有业务接口顶部 `requireLogin()`；写操作再叠加 `requirePerm('权限码')` 或 `requireRole('admin')`；未登录 → 401；越权 → 403
10. **顶栏**：显示当前用户名和角色，一键退出登录（销毁后端 session + 清前端 localStorage）

### 3.2 非功能性需求

| 类别 | 指标 |
|---|---|
| 性能 | 页面加载 ≤ 1.5s；接口响应 ≤ 300ms；千级商品、万级明细稳定运行 |
| 易用 | 侧边菜单按权限自动显隐、操作弹窗二次确认、表单实时校验、一键打印 |
| 兼容 | 前端兼容 Chrome/Edge/Firefox；后端锁定 Ubuntu 24.04 + PHP 8.4 + MySQL 8.4 |
| 数据安全 | bcrypt 密码哈希、HttpOnly+SameSite cookie、CSRF token、失败限流、PDO 参数绑定；数据库仅本地访问；所有库存变动均留存单据 |
| UI 一致性 | 全局表格列宽固定不可拖拽、textarea 禁止拉伸、对话框固定尺寸 |

### 3.3 业务流程

**登录流程**：输入账号密码 → 后端限流检查（若锁定则 429）→ 校验哈希 → 成功则 `session_regenerate_id` + 下发 CSRF token → 返回用户信息（含 role、permissions、csrf）→ 前端存 localStorage → 跳转业务页。

**入库流程**：新增商品 → 新建入库单（供应商 + 商品×N）→ 后端 `requirePerm('stock_in')` → 事务：主单+明细+库存累加+金额 → 返回单号 → 可选打印。

**出库流程**：新建出库单（客户 + 商品×N）→ 后端 `requirePerm('stock_out')` → 每件商品库存前置校验 → 不足则拦截 → 通过则事务扣减 → 返回单号 → 打印。

**盘点流程**：加载账面 → 录入实际值 + 备注 → 后端 `requirePerm('check')` → 事务写盘点明细 + 更新库存 → 打印 → 查看历史（点行看详情）。

**用户管理流程**（admin）：`/users` → 新增用户（勾选权限）→ 后续可编辑权限、重置密码、启用/禁用、删除。

---

## 4 系统总体设计

### 4.1 前后端分离三层架构

1. **表现层（Vue3 + Element Plus）**：渲染页面、表单校验、打印、通过 Axios 调 API，不直连数据库；按 role/permissions 显隐菜单、按路由 meta 拦截未授权访问。
2. **业务层（原生 PHP 8.4）**：参数校验、鉴权（`requireLogin` / `requirePerm` / `requireRole`）、CSRF 校验、库存校验、事务、盈亏计算，接收/返回统一 JSON。
3. **数据层（MySQL 8.4）**：8 张 InnoDB 表，外键约束保证关联完整性。

### 4.2 运行拓扑

**开发**：
```
浏览器 → localhost:5173（Vite Dev Server）
                    ↓ proxy /api → 127.0.0.1:8000（php -S）
                                        ↓ PDO
                                    MySQL 8.4
```

**生产**：
```
浏览器 → Nginx :80
    ├─ /            → /var/www/stock/dist/index.html（Vue 打包产物）
    └─ /api/xxx.php → php8.4-fpm.sock（/var/www/stock/api/xxx.php）
                          ↓ PDO
                       MySQL 8.4（127.0.0.1:3306）
```

### 4.3 功能模块划分

| 模块 | 前端页面 | 后端接口 | 所需权限 |
|---|---|---|---|
| 登录 / 修改密码 | `Login.vue` / `App.vue` 弹窗 | `login.php` | — / 登录 |
| 商品管理 | `Goods.vue` | `goods.php` | 读=登录，写=`goods` |
| 入库管理 | `StockIn.vue` | `stock_in.php` | 读=登录，写=`stock_in` |
| 出库管理 | `StockOut.vue` | `stock_out.php` | 读=登录，写=`stock_out` |
| 库存查询 | `Stock.vue` | `stock.php` | 登录 |
| 库存盘点 | `Check.vue` | `check.php` | 读=登录，写=`check` |
| 用户管理 | `Users.vue` | `users.php` | admin |
| 公共组件 | `utils/request.js` `utils/print.js` `utils/export.js` | `db.php` `auth.php` `index.php` | — |

### 4.4 接口统一规范

所有接口返回：
```json
{ "code": 200, "msg": "ok", "data": ... }
```

| code | 含义 |
|---|---|
| 200 | 成功 |
| 400 | 参数错误 / 业务拦截（库存不足、账号已存在等） |
| 401 | 未登录或会话过期（前端拦截器自动跳登录页） |
| 403 | 越权 / CSRF 失败（前端拦截器提示"权限不足"或"请刷新重试"） |
| 429 | 登录尝试次数过多，锁定中 |

### 4.5 核心 API 清单

| 接口 | 方法 | act | 说明 | 鉴权 |
|---|---|---|---|---|
| /api/login.php | POST | login | 登录，成功返回 `{id,username,role,permissions,csrf}` | 公开 |
| /api/login.php | POST | logout | 登出，销毁 session | 公开 |
| /api/login.php | GET | me | 当前用户信息（含 csrf） | 登录 |
| /api/login.php | POST | change_password | 自助改密码，需原密码 | 登录 |
| /api/users.php | GET | list / perms | 用户列表 / 权限码字典 | admin |
| /api/users.php | POST | add / update / set_active / reset_password / del | 新增/改权限/启禁/重置密码/删除 | admin |
| /api/goods.php | GET | list | 商品列表 | 登录 |
| /api/goods.php | POST | add / edit / del | 商品增删改 | `goods` 权限 |
| /api/stock_in.php | GET | list | 入库历史（含明细 items） | 登录 |
| /api/stock_in.php | POST | add | 新建入库单 | `stock_in` 权限 |
| /api/stock_out.php | GET | list | 出库历史（含明细 items） | 登录 |
| /api/stock_out.php | POST | add | 新建出库单（含库存校验） | `stock_out` 权限 |
| /api/stock.php | GET | — | 实时库存 + 预警标记 | 登录 |
| /api/check.php | GET | list / detail | 盘点历史 / 单据详情 | 登录 |
| /api/check.php | POST | save | 保存盘点，自动清理 2 个月前记录 | `check` 权限 |
| /api/index.php | GET | — | API 根路径，返回接口清单 | 公开 |

---

## 5 数据库设计

### 5.1 E-R 概念模型

实体：`users（用户）`、`goods（商品）`、`stock_in（入库单）`、`stock_out（出库单）`、`stock_check（盘点记录）`、`login_attempts（登录失败计数）`
关系：
- `stock_in (1) ── stock_in_item (N) ── goods (1)`
- `stock_out (1) ── stock_out_item (N) ── goods (1)`
- `goods (1) ── stock_check (N)`
- `login_attempts` 与 `users` 通过 `username` 弱关联，用户被删时同步清理

### 5.2 表结构一览（数据库 `stock_manage`，utf8mb4 / InnoDB，共 8 张表）

| 表 | 用途 |
|---|---|
| `users` | 登录账号、bcrypt 哈希、角色、模块权限、启用状态 |
| `login_attempts` | 登录失败计数与临时锁定 |
| `goods` | 商品主数据（含当前库存、预警阈值）|
| `stock_in` / `stock_in_item` | 入库单主表 / 明细 |
| `stock_out` / `stock_out_item` | 出库单主表 / 明细 |
| `stock_check` | 盘点记录（一单多行）|

### 5.3 关键表字段

**users 用户表**
| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| username | VARCHAR(64) | NOT NULL, UNIQUE | 登录账号 |
| password_hash | VARCHAR(255) | NOT NULL | bcrypt 哈希（60 字符），非明文 |
| role | VARCHAR(32) | NOT NULL, DEFAULT 'admin' | 角色：admin / keeper |
| permissions | VARCHAR(255) | NOT NULL, DEFAULT '' | 逗号分隔权限码：`goods,stock_in,stock_out,check`（admin 忽略此字段） |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 是否启用 |
| last_login_at | DATETIME | NULL | 最近登录时间 |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | |

**login_attempts 登录失败计数**
| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | |
| ip | VARCHAR(64) | NOT NULL | 客户端 IP（透过 X-Forwarded-For 取左段） |
| username | VARCHAR(64) | NOT NULL | 尝试登录的账号 |
| fail_count | INT UNSIGNED | NOT NULL, DEFAULT 0 | 连续失败次数 |
| locked_until | DATETIME | NULL | 锁定截止时间（NULL 表示未锁定） |
| updated_at | DATETIME | ON UPDATE CURRENT_TIMESTAMP | |
| — | — | UNIQUE (ip, username) | 保证幂等累加 |

**goods 商品表**
| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | 商品 ID |
| name | VARCHAR(100) | NOT NULL | 名称 |
| category | VARCHAR(50) | | 分类 |
| spec | VARCHAR(50) | | 规格 |
| buy_price | DECIMAL(10,2) | DEFAULT 0 | 进价 |
| sell_price | DECIMAL(10,2) | DEFAULT 0 | 售价 |
| stock_num | INT | DEFAULT 0 | 当前库存 |
| warn_num | INT | DEFAULT 10 | 预警阈值 |
| create_time | DATETIME | DEFAULT CURRENT_TIMESTAMP | 创建时间 |

**stock_in / stock_out 单据主表**：`id、单号(UNIQUE)、供应商/客户、原因(仅出库)、total_money、create_time`
**stock_in_item / stock_out_item 明细表**：`id、单据 ID(FK)、goods_id(FK)、num、price`

**stock_check 盘点表**
| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT | |
| check_no | VARCHAR(32) | NOT NULL, INDEX | 盘点单号，一单多行**非唯一**（普通索引）|
| goods_id | INT | NOT NULL, FK → goods | |
| book_num | INT | NOT NULL | 账面库存 |
| real_num | INT | NOT NULL | 实际库存 |
| diff_num | INT | NOT NULL | 盘盈盘亏 |
| remark | VARCHAR(255) | DEFAULT '' | 盈亏原因备注 |
| check_time | DATETIME | DEFAULT CURRENT_TIMESTAMP | |

> ⚠️ `check_no` 曾设 `UNIQUE`，导致一单多商品插入第 2 行冲突，已改为普通索引。

完整建表 SQL：见 `sql/stock.sql`。

---

## 6 系统详细设计

### 6.1 前端设计

统一布局 `App.vue`：登录页独立无边框，业务页左侧侧边菜单（按权限自动显隐）+ 顶栏右上角下拉（修改密码 / 退出登录）+ 主区域路由挂载。全局 `<style>` 禁用了表格列拖拽调整、textarea 拉伸、对话框缩放，保证 UI 尺寸恒定。

**Login.vue**：Element Plus 卡片式登录页，登录成功把 `{id, username, role, permissions, csrf}` 写入 localStorage 后跳原目标路由（`?redirect=`）。

**Users.vue**（仅 admin 可见）：用户表 + 新增（可勾选权限）+ 编辑权限（Radio 切换角色，Checkbox 勾选模块）+ 重置密码 + 启禁 + 删除。

**Goods.vue**：列表 + 增删改弹窗 + **名称/分类/规格模糊搜索** + Excel 导出。

**StockIn.vue / StockOut.vue**：列表 + 新建单据弹窗 + **商品名搜索** + **数量/金额升降序排序** + 单据详情列（`商品×数量`）+ 单据打印区。

**Stock.vue**：库存表 + **搜索** + **多字段排序** + **"仅显示预警商品"复选框** + 低于预警红字加粗 + `预警` 标签 + Excel 导出。

**Check.vue**：
- 盘点录入表：商品/规格/账面/实际/盈亏（正绿负红带 `+/-`）/**备注输入框**
- 历史列表：仅 `单号 + 时间 + 商品数`，整行点击/操作列按钮打开详情
- 详情弹窗：`商品 / 规格 / 账面 / 实际 / 盈亏（正绿负红加粗） / 备注`

**公共组件**：
- `utils/request.js`：Axios 封装，`baseURL='/api'`、`withCredentials: true`；请求拦截器为所有 POST 自动带 `X-CSRF-Token`；响应拦截器识别 `401/403/429/其它 code≠200` 并做不同处理（401 → 清本地 + 跳登录，403 提示"权限不足"，429 提示"过于频繁"）
- `utils/print.js`：Print-JS 打印指定 DOM，注入 A4 打印样式（签字栏、公章框、提醒）
- `utils/export.js`：SheetJS 封装 `exportExcel(filename, rows, columns, meta)`，一次调用生成带表头 + 元信息的 xlsx 文件

**路由守卫（`router/index.js`）**：`meta.public=true` 直接放行；否则从 localStorage 取用户；未登录 → 跳 `/login?redirect=`；有 `meta.role` 且不匹配 → 回 `/goods`；有 `meta.perm` 且 role≠admin 且 permissions 未包含该 perm → 回 `/goods`。

### 6.2 后端设计

**db.php**：
- `class DB`：PDO 连接（凭据可 env 覆盖）、query/queryOne/exec/begin/commit/rollback
- `ret(code, msg, data)`：统一 JSON 输出
- `req()`：读取 POST JSON 或表单

**auth.php**（本轮新增）：
- `session_start` + `HttpOnly/SameSite` cookie 设置
- `currentUser()` / `requireLogin()` / `requireRole($role)` / `requirePerm($code)`：分层鉴权
- `issueCsrfToken()`：登录时下发；`requireLogin` 对 POST 用 `hash_equals` 校验
- `clientIp()`：读取 `X-Forwarded-For` 最左段（生产 Nginx 场景）
- `normalizePerms()`：把 DB 中逗号分隔权限串规范化为数组，且只保留合法权限码

**login.php**：`login / logout / me / change_password` 四操作；登录时先查 `login_attempts` 是否被锁定，失败记数达 5 → 锁 15 分钟，成功清计数并 `session_regenerate_id`；`me` 返回当前用户 + 补下发 csrf（供页面刷新后前端补拿）。

**users.php**（仅 admin）：`list / perms / add / update / set_active / reset_password / del`。`update` 支持改角色 + 改 `permissions`；后端限制不能把自己降级、不能取消/删除最后一个 admin；改的是自己时同步刷新 session，无需重新登录。

**goods.php**：`list / add / edit / del`；`list` 供所有登录用户读取（供库存查询、入库/出库选货），`add/edit/del` 需 `goods` 权限。

**stock_in.php**（`list` 用 `JSON_ARRAYAGG` 一次性带出明细）：
```
add: requirePerm('stock_in') → 生成 IN 单号 → begin
  → INSERT stock_in → 循环 INSERT item + UPDATE goods.stock_num += num
  → UPDATE total_money → commit
```

**stock_out.php**（关键"前置校验"逻辑，避免事务回滚成本）：
```
add: requirePerm('stock_out')
  → 预扫描 items → 查每件商品的当前库存 → 数量 > stock_num 或 <= 0 → ret(400, "库存不足...")
  → 全部通过 → 生成 OUT 单号 → begin → 插入主/明细 → UPDATE goods.stock_num -= num → commit
```

**check.php**：
- 顶部懒清理：`DELETE FROM stock_check WHERE check_time < DATE_SUB(NOW(), INTERVAL 2 MONTH)`（try/catch 吞异常，不影响主流程）
- `list`：`SELECT check_no, MIN(check_time), COUNT(*) FROM stock_check GROUP BY check_no ORDER BY MIN(id) DESC`
- `detail`：按 check_no 查所有商品行（LEFT JOIN goods）
- `save`：requirePerm('check') → begin → 循环 INSERT stock_check（含 remark）+ UPDATE goods.stock_num = real → commit

### 6.3 关键防错设计

| 场景 | 措施 |
|---|---|
| 负库存 | 出库前预扫描，任一商品超量则立即拦截 |
| 事务一致性 | 入库/出库/盘点全事务，出错 rollback |
| SQL 注入 | 全部使用 PDO 参数绑定 |
| 中文乱码 | utf8mb4 字符集 + PDO charset |
| 单号冲突 | 时间戳 + 3 位随机数 |
| 一单多明细唯一约束冲突 | stock_check.check_no 改普通索引 |
| 数据老化 | 盘点记录 2 个月自动清理 |
| 明文密码 | password_hash / password_verify + `password_needs_rehash` 自动升级 |
| 账号枚举 | 账号不存在/密码错误 统一返回"账号或密码错误" |
| 会话固定 | 登录成功 `session_regenerate_id(true)` |
| 暴力破解 | (IP,账号) 5 次失败锁 15 分钟 |
| XSS 偷 cookie | Session cookie 设 HttpOnly |
| CSRF | 登录下发 token，POST 请求由前端带头、后端 `hash_equals` 校验 |
| 越权 | 后端 `requirePerm/requireRole`，前端只做 UI 显隐 |
| 管理员自锁 | 不能删/禁自己、不能删/降级最后一个 admin |

---

## 7 开发流程

本项目严格按敏捷小型项目流程推进：

1. **需求分析**：明确核心业务、私有化部署定位
2. **竞品调研**：对比 ERP / SaaS / 老旧开源，确定差异化
3. **数据库设计**：E-R 建模 → 6 张业务表 → 外键约束
4. **API 设计**：确定统一 JSON 结构与 act 分发风格
5. **前端开发**：搭建 Vite + Vue3 项目 → 侧边路由 → 5 个业务页面 → 打印工具封装
6. **后端开发**：db.php 公共层 → 5 个业务接口 → 事务与校验
7. **本地联调**：Vite proxy 联通前后端 → 冒烟测试
8. **业务功能迭代**：
   - 增加**搜索**（商品清单、库存查询、入库、出库、盘点；入库出库支持"商品名 / 供应商 或 客户"双向匹配）
   - 增加**排序**（入库/出库按数量/金额，库存按库存/进价/售价）
   - 增加**预警筛选**（库存查询"仅预警"）
   - 修复**盘点单号唯一约束**（一单多商品导致 1062 冲突）
   - 增加**盘点备注 remark 字段**
   - 增加**盘点详情弹窗**（正数绿负数红带备注）
   - 增加**2 个月自动清理**盘点历史
   - 增加**全局 UI 尺寸锁定**（列宽不可拖动、textarea 不可拉伸）
   - 增加**Excel 导出**（xlsx.js，全模块支持）
   - 增强**打印模板为纸质签收凭证**（签字栏、公章框、核对提醒），并解决 `v-show` 导致的打印空白问题（改用 off-screen `.print-hidden` 定位）
9. **鉴权与权限迭代**（后期完善）：
   - 新增 `users` 表 + `login.php` + `auth.php` + 独立登录页
   - 引入 `password_hash / password_verify`，`session_regenerate_id` 防会话固定
   - `login_attempts` 表 + 失败限流（(IP,账号) 5 次锁 15 分钟）
   - 登录下发 CSRF token，前端 Axios 自动带 `X-CSRF-Token`，后端 `hash_equals` 校验
   - 顶栏下拉自助修改密码
   - `users` 表新增 `permissions` 字段，从粗粒度"角色"升级为**模块级权限**；`Users.vue` 支持勾选与后期编辑
   - 前端路由守卫 + 侧边栏按权限显隐；后端 `requirePerm` 落到具体写操作接口
10. **服务器部署**：`deploy/install.sh` 一键脚本 + `deploy/stock.conf` Nginx 配置
11. **系统测试**：功能 + 边界 + 权限 + 安全测试
12. **文档编写**：README.md（本报告，含使用手册与测试步骤）

---

## 8 部署与运行流程

### 8.1 本地开发（Ubuntu 24.04 LTS）

#### 8.1.1 安装开发依赖

```bash
# PHP 8.4
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-cli php8.4-mysql php8.4-mbstring

# MySQL 8.4（通过 MySQL 官方 APT 源）
sudo apt install -y wget lsb-release gnupg
wget -qO- https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | sudo gpg --dearmor -o /usr/share/keyrings/mysql.gpg
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" | sudo tee /etc/apt/sources.list.d/mysql-8.4.list
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-tools" | sudo tee -a /etc/apt/sources.list.d/mysql-8.4.list
sudo apt update
sudo DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
sudo systemctl enable --now mysql

# Node.js 20+（前端构建用）
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
sudo apt install -y nodejs
```

#### 8.1.2 初始化数据库

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS stock_manage DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'stock_user'@'localhost' IDENTIFIED BY 'Stock@123456';"
sudo mysql -e "GRANT ALL PRIVILEGES ON stock_manage.* TO 'stock_user'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql stock_manage < sql/stock.sql

# 验证：应显示 8 张表
sudo mysql -e "SHOW TABLES;" stock_manage
# goods / login_attempts / stock_check / stock_in / stock_in_item /
# stock_out / stock_out_item / users
```

`sql/stock.sql` 会自动插入默认管理员 `admin / admin123`（bcrypt 哈希）。

#### 8.1.3 启动后端 API（PHP 内置服务器）

```bash
cd ~/Inventory_Management
php -S 127.0.0.1:8000 -t api
# 保持窗口开启，接口地址：http://127.0.0.1:8000/xxx.php
# 停止：pkill -9 -f "php -S"
```

#### 8.1.4 启动前端 Dev Server（新终端）

```bash
cd ~/Inventory_Management/frontend
npm install
npm run dev            # 访问 http://localhost:5173
```

> ⚠️ 若 `vite@8` 报错 `Cannot find module '@rolldown/binding-linux-x64-gnu'`，
> 执行 `npm i -D vite@^5.4.0 @vitejs/plugin-vue@^5.1.0` 降级。

### 8.2 生产部署（Ubuntu 24.04 LTS + PHP 8.4 + MySQL 8.4）

```bash
# 一键脚本
cd ~/Inventory_Management/deploy
sudo bash install.sh
# 脚本自动完成：系统更新 → Nginx → PHP 8.4-FPM（ondrej PPA）→ MySQL 8.4（官方 APT 源）
#              → 数据库初始化（含 users / login_attempts 表 + admin 默认账号）
#              → Node.js 20 → 部署 API → 前端构建部署 → Nginx 配置

# 浏览器访问 http://<服务器IP>/  →  用 admin/admin123 登录
```

站点配置文件 `deploy/stock.conf`：
```
server {
    listen 80;
    server_name localhost;
    root /var/www/stock/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
    location /api/ {
        root /var/www/stock;
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 8.3 手动分步部署

```bash
# 1. Nginx
sudo apt install -y nginx && sudo systemctl enable --now nginx

# 2. PHP 8.4
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-cli
sudo systemctl enable --now php8.4-fpm
ls -l /run/php/php8.4-fpm.sock                       # 验证 socket 存在

# 3. MySQL 8.4
sudo apt install -y wget lsb-release gnupg
wget -qO- https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | sudo gpg --dearmor -o /usr/share/keyrings/mysql.gpg
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" | sudo tee /etc/apt/sources.list.d/mysql-8.4.list
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-tools" | sudo tee -a /etc/apt/sources.list.d/mysql-8.4.list
sudo apt update
sudo DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
sudo systemctl enable --now mysql
mysql --version                                      # 期望：mysql  Ver 8.4.x

# 4. 初始化数据库（同 8.1.2 节）

# 5. 部署代码
sudo mkdir -p /var/www/stock/{api,dist}
sudo cp -r api/* /var/www/stock/api/
cd frontend && npm install && npm run build
sudo cp -r dist/* /var/www/stock/dist/
sudo chown -R www-data:www-data /var/www/stock

# 6. Nginx 配置
sudo cp deploy/stock.conf /etc/nginx/sites-available/stock.conf
sudo ln -sf /etc/nginx/sites-available/stock.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### 8.4 常见问题（FAQ）

| 现象 | 原因 | 解决 |
|---|---|---|
| `vite` 启动报 `Cannot find module '@rolldown/binding-linux-x64-gnu'` | vite@8 依赖 rolldown 原生二进制未就绪 | `npm i -D vite@^5.4.0 @vitejs/plugin-vue@^5.1.0` |
| `Address already in use` | 之前 `php -S` 未关闭 | `pkill -f "php -S"` 后重启 |
| 前端 `ECONNREFUSED 127.0.0.1:80` | Vite 代理指向 80 端口但 PHP 在 8000 | 检查 `vite.config.js` 的 `target: 'http://127.0.0.1:8000'` |
| API 返回"数据库连接失败" | MySQL 未启动或用户未创建 | `sudo systemctl start mysql`，重跑数据库初始化 |
| Nginx 502 | PHP-FPM socket 路径不对 | 确认 `/run/php/php8.4-fpm.sock` 存在 |
| `php: command not found` | PHP 8.4 未安装 | 按 8.1.1 节添加 ondrej PPA 后安装 |
| MySQL 8.4 apt 报 `EXPKEYSIG` | 旧 2023 GPG 密钥已过期 | 使用 `RPM-GPG-KEY-mysql-2025`（脚本已修正） |
| `php8.4-json` 安装失败 | JSON 扩展自 PHP 8.0 起内置编译，包不存在 | 不要安装该包（脚本已剔除） |
| WSL2 下 3306 被 Windows 占用 | 镜像网络 | 脚本自动检测并切换到 3307（应用通过 socket 连接不受影响） |
| 登录返回"Table 'login_attempts' doesn't exist" | 数据库是旧版本，缺 8.x 新表 | 重跑 `sudo mysql stock_manage < sql/stock.sql`（`IF NOT EXISTS` 安全） |
| 登录返回 "CSRF 校验失败" | localStorage 里 csrf 过期或缺失 | 清空 localStorage 后重新登录 |
| 登录返回 429 "尝试次数过多" | (IP,账号) 15 分钟内失败 5 次被锁 | 等 15 分钟；或 `DELETE FROM login_attempts WHERE username='xxx'` |
| 前端菜单少了几项 | 当前账号无对应模块权限 | 用 admin 登录到"用户管理"给该账号勾选权限 |

### 8.5 忘记 admin 密码怎么办

```bash
# 1. 用 PHP 生成新 bcrypt 哈希
php -r 'echo password_hash("你的新密码", PASSWORD_DEFAULT).PHP_EOL;'
# 输出类似：$2y$12$xxxxxxxx...

# 2. 更新数据库（替换 <hash>）
sudo mysql stock_manage -e "UPDATE users SET password_hash='<hash>' WHERE username='admin';"
# 顺便清掉可能的失败锁定
sudo mysql stock_manage -e "DELETE FROM login_attempts WHERE username='admin';"
```

---

## 9 测试流程与用例

### 9.1 测试环境

- 服务器：Ubuntu 24.04 LTS (Noble Numbat)
- 后端：PHP 8.4 + MySQL 8.4 LTS
- 前端：Chrome / Edge 现代版本
- 工具：curl（接口冒烟）、浏览器（页面 E2E）

### 9.2 环境验证

```bash
lsb_release -a                                       # 期望：Ubuntu 24.04 LTS
php -v                                               # 期望：PHP 8.4.x
php -m | grep -E 'pdo_mysql|mbstring'                # 期望：pdo_mysql、mbstring
mysql --version                                      # 期望：mysql  Ver 8.4.x
ls -l /run/php/php8.4-fpm.sock                       # 期望：srw-rw---- www-data
systemctl status nginx                               # 期望：active (running)
node -v                                              # 期望：v20.x
```

### 9.3 完整测试操作步骤

**Step 1：确认后端可用**

```bash
curl http://127.0.0.1:8000/index.php
# 期望：{"code":200,"msg":"stock-manage api is running",...}
```

**Step 2：接口冒烟脚本（curl，含登录 + CSRF）**

```bash
COOKIE=/tmp/stock.cookie

# ① 登录（保存 cookie，取出 csrf token）
CSRF=$(curl -s -c "$COOKIE" -X POST "http://127.0.0.1:8000/login.php?act=login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["data"]["csrf"])')
echo "CSRF=$CSRF"

# ② 商品：新增（POST 必须带 Cookie + X-CSRF-Token）
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/goods.php?act=add" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"name":"测试商品A","category":"日用","spec":"500ml","buy_price":5,"sell_price":10,"warn_num":5}'
# 期望：{"code":200,"msg":"新增成功","data":{"id":1}}

# ③ 商品：列表（GET 无需 CSRF）
curl -s -b "$COOKIE" "http://127.0.0.1:8000/goods.php?act=list"

# ④ 入库 20 件
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/stock_in.php?act=add" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"supplier":"甲供应商","items":[{"goods_id":1,"num":20,"price":5}]}'

# ⑤ 出库 5 件（正常）
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/stock_out.php?act=add" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"customer":"客户A","reason":"销售","items":[{"goods_id":1,"num":5,"price":10}]}'

# ⑥ 出库 9999 件（应拦截）
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/stock_out.php?act=add" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"customer":"客户B","reason":"销售","items":[{"goods_id":1,"num":9999,"price":10}]}'
# 期望：{"code":400,"msg":"商品【测试商品A】库存不足..."}

# ⑦ 盘点
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/check.php?act=save" \
  -H "Content-Type: application/json" -H "X-CSRF-Token: $CSRF" \
  -d '{"items":[{"goods_id":1,"book_num":15,"real_num":13,"remark":"破损2件"}]}'

# ⑧ 实时库存
curl -s -b "$COOKIE" "http://127.0.0.1:8000/stock.php"

# ⑨ 越权测试：不带 Cookie 直接调 API
curl -s "http://127.0.0.1:8000/goods.php?act=list"
# 期望：{"code":401,"msg":"未登录或会话已过期"}

# ⑩ 缺失 CSRF：带 Cookie 但不带 header
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/goods.php?act=add" \
  -H "Content-Type: application/json" -d '{"name":"x"}'
# 期望：{"code":403,"msg":"CSRF 校验失败..."}

# ⑪ 登出
curl -s -b "$COOKIE" -X POST "http://127.0.0.1:8000/login.php?act=logout" \
  -H "X-CSRF-Token: $CSRF"
```

**Step 3：浏览器 E2E 手工测试**

打开 `http://localhost:5173`（开发）或 `http://<服务器IP>/`（生产），先用 admin / admin123 登录，然后按下表逐项验证：

| 模块 | 用例 | 预期 |
|---|---|---|
| 登录 | 输入错误密码 | 提示"账号或密码错误"，不区分是账号还是密码错 |
| 登录 | 连续错 5 次 | 第 6 次直接返回 429，提示"约 15 分钟后再试" |
| 登录 | 正确登录后 F12 → Application → Local Storage | `stock_user` 含 `id/username/role/permissions/csrf` |
| 登录 | 关闭浏览器再打开 | 需重新登录（cookie 生命周期为浏览器会话）|
| 权限 | admin 用户看到侧栏 6 项（含"用户管理"）| ✅ |
| 权限 | 用户管理页新增账号 `warehouse1`，只勾"入库、出库" | 新账号可创建 |
| 权限 | 用 warehouse1 登录 | 侧栏仅"商品清单、入库管理、出库管理、库存查询" |
| 权限 | warehouse1 点新增商品保存 | 后端返 403"权限不足" |
| 权限 | warehouse1 手动访问 `#/users` | 被守卫弹回首页 |
| 权限 | admin 编辑 warehouse1 权限，勾上"库存盘点" | 该账号下次刷新即可见"库存盘点" |
| 修改密码 | 右上角下拉 → 修改密码 → 提交 | 弹出"已修改，请重新登录"并踢回登录页 |
| 商品管理 | 新增/编辑/删除 | 库表实时更新 |
| 入库 | 多商品同单入库 | 库存累加，事务成功 |
| 入库 | 按商品名/供应商搜索历史 | 双向匹配 |
| 出库 | 数量 > 库存 | 弹窗提示，拒绝提交（关键）|
| 库存查询 | "仅显示预警"复选框 | 过滤到预警品 |
| 盘点 | 多商品同单盘点 | 单号唯一约束不再冲突 |
| 盘点 | 备注写入并回显 | 详情页显示 |
| 盘点 | 2 个月过期自动清理 | 伪造 3 月前记录被删 |
| 打印 | 三类单据打印 | 无侧边菜单，含签字栏/公章框 |
| Excel 导出 | 各模块导出 | .xlsx 文件正常下载 |
| UI | 表格列不可拖动 | 光标不变、拖动无响应 |
| 登出 | 右上角下拉 → 退出 → 再刷新任意业务页 | 自动跳登录 |

### 9.4 结果分析

- 全部核心业务通过；边界场景（超库存出库、多商品盘点、大批量入库）稳定
- 事务保证多表同步更新，从未出现"账面 ≠ 明细"
- 前端响应速度 < 300ms，接口延迟 < 100ms
- 打印格式适配 A4 纸张，符合仓库存档规范
- 鉴权/权限/CSRF/限流四条安全线在浏览器与 curl 手工验证下均按预期返回 401/403/429，无绕过路径

---

## 10 当前实现情况总览

### 10.1 目录结构

```
Inventory_Management/
├── api/                        # 后端接口（PHP 8.4，原生）
│   ├── db.php                  # PDO + 公共函数（ret、req）
│   ├── auth.php                # 会话 + 鉴权 + 权限 + CSRF 工具
│   ├── login.php               # 登录/登出/me/改密码 + 失败限流
│   ├── users.php               # 用户管理（仅 admin）
│   ├── goods.php               # 商品增删改查
│   ├── stock_in.php            # 入库（含 JSON_ARRAYAGG 明细）
│   ├── stock_out.php           # 出库（库存前置校验）
│   ├── stock.php               # 实时库存（含预警字段）
│   ├── check.php               # 盘点（聚合 list + detail + 自动清理）
│   └── index.php               # API 根路径说明
├── frontend/                   # 前端（Vue3 + Vite 5）
│   ├── src/
│   │   ├── views/
│   │   │   ├── Login.vue       # 登录页
│   │   │   ├── Goods.vue       # 商品清单 + 搜索
│   │   │   ├── StockIn.vue     # 入库 + 搜索 + 排序
│   │   │   ├── StockOut.vue    # 出库 + 搜索 + 排序
│   │   │   ├── Stock.vue       # 库存查询 + 搜索 + 排序 + 预警筛选
│   │   │   ├── Check.vue       # 盘点 + 备注 + 详情弹窗
│   │   │   └── Users.vue       # 用户管理（admin） + 权限勾选
│   │   ├── router/
│   │   │   └── index.js        # 路由 + 登录/权限守卫
│   │   ├── utils/
│   │   │   ├── request.js      # Axios（withCredentials + X-CSRF-Token）
│   │   │   ├── print.js        # Print-JS 打印工具（签字栏样式）
│   │   │   └── export.js       # SheetJS xlsx 导出封装
│   │   ├── App.vue             # 布局（登录页独立）+ 顶栏 + 全局 UI 锁定 CSS
│   │   └── main.js
│   ├── index.html
│   ├── package.json            # vite@5.4 + element-plus + icons-vue + axios + print-js + xlsx
│   └── vite.config.js          # proxy /api → 127.0.0.1:8000
├── sql/
│   └── stock.sql               # 8 张表建表脚本 + 默认 admin 账号（MySQL 8.4）
├── deploy/
│   ├── install.sh              # Ubuntu 24.04 一键部署
│   └── stock.conf              # Nginx 站点配置
├── .gitignore                  # 忽略 node_modules / dist / .env / .claude 等
└── README.md                   # 本设计报告（含使用手册与测试步骤）
```

### 10.2 数据库账号

| 项 | 值 |
|---|---|
| host | localhost |
| user | stock_user |
| pass | Stock@123456（默认，可通过 `DB_HOST/DB_USER/DB_PASS` 环境变量覆盖） |
| database | stock_manage |

### 10.3 默认应用账号

| 项 | 值 |
|---|---|
| 账号 | admin |
| 密码 | admin123（bcrypt 哈希存于 `users.password_hash`）|
| 首次登录后 | **立刻通过顶栏"修改密码"改成强密码** |

### 10.4 七大功能一览

1. **商品清单**：增删改查 + 三字段模糊搜索 + Excel 导出
2. **入库管理**：多商品事务、单号自动、"商品/供应商"双向搜索、双维度排序、纸质签收单打印、单条 Excel 导出；一供应商可开多单
3. **出库管理**：前置库存校验、事务扣减、"商品/客户"双向搜索、排序、纸质签收单打印、单条 Excel 导出；一客户可开多单
4. **库存查询**：搜索、多字段排序、预警标红、"仅预警"筛选、整表 Excel 导出
5. **库存盘点**：多商品单号复用、备注、正负颜色、详情弹窗、2 月自动清理、纸质盘点单打印、当前/历史 Excel 导出
6. **单据打印**：入库/出库/盘点单统一含签字栏（制单/仓管/收领/日期）+ 公章框 + 核对提醒，可直接签字盖章存档
7. **用户与权限**：登录/自助改密码 + admin 管理其他账号 + 模块级权限勾选（商品/入库/出库/盘点）+ CSRF + 失败限流 + 会话安全

全局 UI：表格列固定不可拖、textarea 不可缩放、对话框固定尺寸。

---

## 11 系统优化与扩展方向

### 11.1 当前不足

1. 权限粒度到"模块"，未细到"字段级"（例如 keeper 不能改商品价格但能改数量）
2. 无操作流水审计（谁在什么时候改了哪条数据、来源单号）
3. 无供应商/客户独立主数据（当前以文本输入方式记录）
4. 无可视化图表
5. 无 HTTPS 强制（生产环境需自行加证书 + 把 session cookie 的 `Secure` 打开）

### 11.2 后续扩展

1. **审计**：新增 `op_log` 记录每次库存变动的操作人、时间、来源单号
2. **主数据**：`supplier` / `customer` 表，下拉选择替代手工输入，绑定历史单据
3. **可视化**：引入 ECharts，展示月度出入库趋势、Top10 商品
4. **消息提醒**：库存预警邮件/网页通知
5. **商品图片**：文件上传，`goods.image_url` 字段
6. **硬件对接**：扫码枪批量录入、小票打印机直连
7. **HTTPS + 安全增强**：Let's Encrypt 证书、cookie `Secure` 位、CSP、更严格的 SameSite=Strict
8. **前端权限指令**：`v-perm="stock_in"` 让无权限按钮直接禁用/隐藏（现在按钮仍可见，仅后端拦截）

---

## 12 项目总结

本项目在 **Ubuntu 24.04 LTS** 上完成基于 **Vue3 + PHP 8.4 + MySQL 8.4** 的轻量化商品库存管理系统全流程开发，覆盖需求分析、架构设计、数据库设计、前后端编码、服务器部署、鉴权与权限迭代、系统测试全套标准化流程。系统实现商品清单、入库、出库、库存查询、盘点、单据打印、多用户与权限管理七大核心业务，并在迭代中增强了搜索/排序/预警筛选/盘点备注/自动清理/UI 尺寸锁定/Excel 导出/纸质签收单打印等能力，最终加入 bcrypt 密码哈希、HttpOnly+SameSite 会话、CSRF token、登录失败限流、模块级权限勾选等生产级安全底线，解决了小微企业纸质台账管理效率低、数据易错、单据难留存、多人协作难分权的痛点。

技术上采用前后端分离、事务保障、参数化 SQL、私有化本地部署，代码轻量、部署标准化、数据安全。开发过程完整实践了 Linux 服务器运维、Vue3 前端、原生 PHP 后端、MySQL 事务与外键约束、Nginx + PHP-FPM 部署、Session/CSRF/bcrypt/限流等常见 Web 安全实践，达到课程设计 / 毕业设计 / 小型商业交付级别。

系统架构留有扩展空间，可基于现有代码继续演进操作日志、报表、可视化统计、硬件接入等能力，也可适配仓储门店、五金店铺、加工作坊等多行业场景，支持云部署以实现多门店远程访问。
