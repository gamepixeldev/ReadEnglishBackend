# 精读英语 API

Laravel 13 + PHP 8.4。本地开发使用独立的 MySQL 8.0 数据库，首次上线目标为 MySQL 8.4 LTS。后端与 uni-app 前端分目录，接口前缀为 `/api/v1`。

## 当前完成情况

- 已建立内容与学习记录表的迁移，并已在本地项目专用 MySQL 8.0 数据库运行。
- 已实现文章/章节、系列、重点词汇、阅读题、单词、语法课程及拼句的公开接口。
- 本文语法解析接口已接通；可用 `php artisan db:seed --class=SampleReadingSeeder` 幂等录入一篇原创测试文章、8 个重点词汇与 4 道阅读题。此命令只用于开发测试，不要在正式内容库运行。
- 业务 ID 已改为整数自增；单词详解、文章解析、独立语法课程内容分别存于 `word_details`、`reading_analyses`、`grammar_lesson_contents`。本地开发库曾仅有可重建测试内容，因此已按新结构重建；有真实数据的环境不能照做 `migrate:fresh`，必须单独编写保留数据的升级迁移。
- 阅读题和语法题在获取题目时不返回答案；答案提交到练习尝试接口后保存作答记录，并返回判定与解析。
- 小程序微信登录、学习进度、练习记录、最近学习、收藏及反馈已接入后端；语法、拼句和每日单词也可在管理后台维护。仍需正式域名、真机联调和上线前安全/兼容验证，不要把当前工程当作可上线版本。
- 首次管理员通过 `php artisan content-admin:provision` 创建，命令仅在创建时显示一次随机账号密码；建议首次登录后立即更改密码。`users.is_admin` 默认 false，只有管理员可访问后台。
- 阅读理解题、外刊系列/章节、独立语法、拼句和每日单词计划已接入可视化后台。

完整接口边界见 `docs/api-contract-v1.md`。
本地开发、首次导入和后续上线的分界见 `docs/local-first-deployment.md`。

## 本地运行

要求 PHP 8.4、Composer 2，启用 `openssl`、`mbstring`、`fileinfo`、`pdo_mysql`（测试另需 `pdo_sqlite`）等扩展。

本机 Windows 可在后端项目根目录运行 `powershell -ExecutionPolicy Bypass -File .\scripts\start-backend.ps1`。启动后访问 `http://127.0.0.1:8000/` 或 `http://127.0.0.1:8000/api/v1/reading-items`，按 Ctrl+C 停止。当前本机 `.env` 已连接项目专用的 `english_study_dev` 数据库，尚未导入内容时接口列表为空。

1. 在后端项目根目录执行 `composer install`。
2. 新环境复制 `.env.example` 为 `.env`，填写独立开发库连接；本机已创建 `english_study_dev` 库和仅对该库授权的 `english_study_app` 本地账号。不要填写现有业务数据库或线上数据库账号，不要提交 `.env`。
3. 执行 `php artisan key:generate`、`php artisan migrate`。
4. 执行 `php artisan serve`，通过 `/api/v1/reading-items` 等路径访问。新数据库尚未导入内容时，列表为空。
5. 执行 `php artisan test` 运行 SQLite 内存库测试；本机 MySQL 8.0 迁移已验证，后续仍需完整的 MySQL 集成测试。

小程序 API 根地址配置在前端 `config/api.js`，本地 H5 开发默认使用 `http://127.0.0.1:8000`。微信开发者工具、真机和上线环境需配置客户端可访问的 API 域名；正式小程序使用 HTTPS。

当前开发机在忽略的 `.local-tools/` 中保存了临时 PHP 扩展配置。它不属于部署配置，也不会提交到 Git。

## 上线前必须完成

完成正式 HTTPS API 域名与小程序域名配置、真机微信登录联调、生产数据库兼容验证、内容来源/授权审核、异机备份与恢复演练，以及上线前安全检查。
