# API 契约 v1（第一阶段）

配套模型见 [data-model-v1.md](./data-model-v1.md)。后端已在本项目开始分阶段实现；精读文章列表、详情、重点词汇、本文语法解析及阅读理解判题已接入测试内容，其余前端模块仍使用固定数据和 `uni` 本地存储。未实现的接口不能视为可用。

## 通用规则

- 路径前缀 `/api/v1`，JSON 使用 `camelCase`，ID 为不透明字符串。任何 API 都不接收前端数组下标作为资源标识。
- 列表统一 `?limit=20&cursor=...`，响应 `{ "items": [], "nextCursor": null }`；`nextCursor=null` 表示到底。`limit` 有上限，后端保证排序稳定。
- 成功的单资源响应直接返回资源对象；错误统一 `{ "error": { "code": "...", "message": "..." } }`。错误码至少覆盖 `UNAUTHORIZED`、`NOT_FOUND`、`VALIDATION_ERROR`、`CONFLICT`、`CONTENT_NOT_PUBLISHED`。
- 公开内容接口只返回已发布内容，不混入用户状态，便于缓存。`/me` 和写接口需要登录；身份由服务端会话确定，客户端不能传 `userId`。开发预览可用本地测试会话，不把测试身份带入生产。
- 写接口以服务器时间为准。完成阅读、收藏、每日完成使用唯一约束保证重复请求安全；单词学习事件使用 `requestId` 保证网络重试幂等，但用户重新发起一次学习可以产生新的事件。
- 题目的正确答案、拼句的正确词序不在初次获取题目的响应里返回；提交检查后才返回判定与解析。阅读/语法做题暂不持久化成绩，沿用静态版行为。

## 登录与首页

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `POST /auth/session` | 用平台登录凭证换应用会话 | 请求含 `provider`、平台凭证；后端解析为内部 `userId`，不直接信任前端提供的身份 ID。具体平台凭证格式待后端技术确定。 |
| `GET /me/overview` | 首页“最近学习”预览 | 返回最近若干条记录；四个主模块仍不要求先完成另一模块。 |

## 精读与小说章节

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `GET /reading-items?kind=article&level=...&cursor=...` | 轻松阅读文章列表 | 卡片字段、词数/预计时长、`nextCursor`。 |
| `GET /series?cursor=...` | 外刊/小说书架 | 系列封面、标题、简介和章节数。 |
| `GET /series/{seriesId}` | 目录页 | 系列详情及按 `chapterNo` 排列的章节摘要和稳定 `readingItemId`。 |
| `GET /reading-items/{readingItemId}` | 共用阅读详情 | `kind`、标题、来源、`body` 段落数组、`wordCount`、`questionCount`，不返回整套配套内容。 |
| `GET /reading-items/{readingItemId}/words` | 本文重点词汇列表 | 按 `sortOrder` 排列，含 `wordId`；词条详情走统一单词接口。 |
| `GET /reading-items/{readingItemId}/grammar-analysis` | 本文语法解析 | 返回该篇独立的 `contentSource` 富文本/公众号链接，不跳转独立语法课程列表。 |
| `GET /reading-items/{readingItemId}/questions` | 阅读理解题 | 返回题干、选项、题型；不返回答案和解析。 |
| `POST /reading-questions/{questionId}/check` | 检查单题 | 请求 `{ "answer": 1 }` 或填空字符串；返回 `correct`、`correctAnswer`、`explanation`；不新增学习记录。 |
| `GET /me/reading-items/{readingItemId}/status` | 阅读完成状态 | `{ "completed": true, "completedAt": "..." }`。 |
| `PUT /me/reading-items/{readingItemId}/completion` | 点击“阅读完成” | 幂等；只此操作写阅读完成与最近学习。返回完成状态。 |

`GET /reading-items/{id}` 的 `body` 形状与静态版段落一致：

```json
{
  "id": "umbrella",
  "kind": "article",
  "title": "The Blue Umbrella",
  "translation": "蓝色雨伞",
  "body": [
    {
      "sentences": ["On a rainy afternoon, Lily found a blue umbrella."],
      "translation": "一个下雨的午后，莉莉发现了一把蓝色雨伞。"
    }
  ],
  "wordCount": 8,
  "questionCount": 4
}
```

章节同样用这个详情接口；`kind=chapter` 时额外返回 `seriesId`、`chapterNo` 和相邻章节 ID。前端不需要两套阅读详情接口或两套完成逻辑。

## 单词

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `GET /me/words/today` | 今日指定单词 | 服务端按中国时区确定 `planDate`，返回有序 `wordIds`、完成数及每词状态。没发布计划时返回明确的 `notReady` 状态，不随机抽全库单词。 |
| `GET /words/{wordId}` | 统一单词详情 | 音标、发音资源、词性、释义、例句/详解 `contentSource`；精读与每日单词都用此接口。 |
| `GET /me/words/{wordId}/status` | 已学与收藏状态 | 用于详情页星标和学习标签。 |
| `POST /me/words/{wordId}/completions` | 详情→拼写成功后学完 | 请求含 `requestId`、`sourceKind` (`daily`/`reading`)、相应的 `planDate` 或 `readingItemId`；服务端校验来源并写单词事件、最近学习。仅 `daily` 同时计入当天计划。 |
| `PUT /me/words/{wordId}/favorite` / `DELETE ...` | 加/取消收藏 | 幂等；收藏进入详情为只读，不允许自动产生完成记录。 |
| `GET /me/favorite-words?cursor=...` | 我的收藏 | 分页，只展示用户收藏词。 |

用户没有“浏览单词库”接口入口。今日单词与精读词汇即使重复，也各自可进入学习流程：从精读学过不自动把今日单词标为完成；今日学完会新增一次单词学习事件。“直接拼写”只在客户端练习，不调用完成接口。

`contentSource` 统一形状：

```json
{ "type": "richtext", "html": "<h3>词义与用法</h3><p>...</p>" }
```

或 `{ "type": "officialAccount", "url": "https://...", "title": "...", "summary": "..." }`。词条、独立语法课程和文章语法解析各自拥有内容来源，但前端可复用富文本显示组件。

## 独立语法

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `GET /grammar-lessons?level=...&cursor=...` | 语法课程列表 | 按分类筛选、分页。 |
| `GET /grammar-lessons/{lessonId}` | 语法课程详情 | 课程的 `contentSource`、摘要及练习数。与文章语法解析无强关联。 |
| `POST /me/grammar-lessons/{lessonId}/visit` | 进入详情时更新最近学习 | 幂等更新该课程的最近访问时间；不代表做题正确。 |
| `GET /grammar-lessons/{lessonId}/questions` | 课程练习题 | 不返回正确答案。 |
| `POST /grammar-questions/{questionId}/check` | 判题 | 返回正确与否、答案和解析；v1 不保存成绩。 |

## 拼句

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `GET /puzzle-sets?source=all\|independent\|reading&cursor=...` | 拼句文章列表 | 返回来源标签、题数等；`sourceReadingItemId` 可为空。 |
| `GET /puzzle-sets/{setId}` | 拼句题目 | 返回按顺序排列的中文提示和打散词块（每个词块有独立 ID），不返回正确顺序。 |
| `GET /me/puzzle-sets/{setId}/progress` | 继续练习的位置 | `cursorNext` 为下一句的零基序号，另有 `total`、`completed`。 |
| `POST /me/puzzle-sets/{setId}/start` | 开始或继续 | 返回当前 `cursorNext`，更新最近学习；不因为再次进入而重置。 |
| `POST /me/puzzle-sets/{setId}/sentences/{sentenceId}/check` | 检查拼句 | 请求有序 `tokenIds`；返回 `correct`、每个位置的 `correct`/`wrong` 状态。正确时服务端原子地推进 `cursorNext`。 |
| `POST /me/puzzle-sets/{setId}/restart` | 从头开始 | 清除该集合完成标记，设 `cursorNext=0`，不删除其他模块记录。 |

进度语义：完成第 1、2 句后 `cursorNext=2`，列表显示 `2/3`，再次点击进入第 3 句。`cursorNext=total` 且 `completed=true` 表示整组完成；再次练习需要显式从头开始。

## 最近学习

| 方法与路径 | 用途 | 关键返回/说明 |
| --- | --- | --- |
| `GET /me/recent?category=reading\|grammar\|word\|puzzle&cursor=...` | 分模块记录列表 | 按最近时间倒序；返回目标稳定 ID、标题快照、状态/进度和 `nextCursor`。阅读含普通文章与小说章节。 |
| `DELETE /me/recent` | 清空展示记录 | 只清空最近学习列表，不重置阅读完成、单词完成、收藏或拼句进度。 |

## 管理端（第二阶段实现）

后台需要草稿、预览、发布和下架能力。管理接口与公开接口分离，按资源提供列表/创建/更新/发布：`series`、`reading-items`、`words`、`grammar-lessons`、`puzzle-sets`、`daily-word-plans`；阅读题、重点词关联、拼句句子在所属资源的编辑界面维护。发布时校验内容完整性、关联 ID、顺序、每日计划词数及版权字段。后台必须鉴权并限制为管理员。

## 前端替换顺序与验收

1. 建统一 API 客户端及开发环境配置，保留当前固定数据作为开发预览夹具；接口错误、空数据和加载状态统一处理。
2. 替换精读/书架/目录/详情和配套内容。核对普通文章与章节共用详情，阅读完成只在用户点击后记录。
3. 替换每日单词、精读词汇、词条详情、收藏与拼写完成。核对重复单词、只读入口和“直接拼写不记进度”。
4. 替换独立语法、文章语法解析、阅读/语法判题。核对两种语法内容没有串联。
5. 替换拼句列表、题目与进度，再接最近学习分页。重点验收 `2/3 → 第 3 句`、从头开始、短列表加载与清空记录不清空进度。

广告提示是后续独立任务：当前广告位尚未配置。正式启用时，提示答案应在广告完成且服务端验证后下发；不要仅凭前端的“已看完”状态解锁。这不阻塞基础内容与学习 API 的开发。

已确定 Laravel 13 / PHP 8.4 / MySQL 8.4 LTS / 自有云服务器。用户登录方式和是否迁移测试期本地记录仍待确认。后端已完成第一批迁移与公开接口；种子数据、其余接口及前端对接继续按本契约推进。
