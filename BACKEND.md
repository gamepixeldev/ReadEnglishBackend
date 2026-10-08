# 精读英语 API

Laravel 13 + PHP 8.4。本地开发使用独立的 MySQL 8.0 数据库，首次上线目标为 MySQL 8.4 LTS。后端与 uni-app 前端分目录，接口前缀为 `/api/v1`。

## 当前完成情况

- 已建立内容与学习记录表的迁移，并已在本地项目专用 MySQL 8.0 数据库运行。
- 已实现文章/章节、系列、重点词汇、阅读题、单词、语法题及拼句集合的部分公开接口。
- 阅读题和语法题在获取题目时不返回答案，提交后才返回判定。
- 用户登录、学习记录写入、富文本发布/清理、内容管理后台和前端 API 对接尚未实现。不要把当前工程当作可上线版本。

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

当前开发机在忽略的 `.local-tools/` 中保存了临时 PHP 扩展配置。它不属于部署配置，也不会提交到 Git。

## 上线前必须完成

实现微信登录与管理员鉴权、服务端进度写入、富文本安全清理、管理端发布校验、内容来源/授权审核、MySQL 8.0/8.4 集成测试、异机备份与恢复演练，以及小程序和未来 App 的 HTTPS API 域名配置。
