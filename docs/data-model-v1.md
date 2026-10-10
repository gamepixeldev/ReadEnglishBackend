# 数据模型 v1（静态版 → API 版）

状态：第一阶段数据模型，已在 Laravel / MySQL 迁移中落地；基线为 Git 标签 `static-ui-final-2026-10-08`。后续管理端、用户进度接口仍按阶段实现。

## 边界与统一约定

- 四个入口（精读、单词、语法、拼句）可以独立使用。文章只通过关联关系引用词条；文章语法解析是该阅读内容独有的富文本，不等于独立语法课程；拼句集合可以选择一篇阅读内容作为来源，但不依赖它才能存在。
- 普通精读文章与小说/外刊章节共用 `reading_items`，前端复用同一阅读详情页。章节另有 `series_id` 和 `chapter_no`。
- 业务实体主键使用 `INT UNSIGNED AUTO_INCREMENT`，外键使用同类型整数；API 返回数字 ID，不能把前端数组下标当作资源 ID。文章和单词另可保存唯一 `slug`，用于内容导入定位，不作为主键。关联表可用联合主键；会话、请求幂等键和拼句词块令牌不是业务实体 ID，按各自用途保留字符串。列表排序使用单独的 `sort_order`。每日计划按 `Asia/Shanghai` 的自然日计算。
- 内容只有 `published` 状态可出现在公开接口。后台保存草稿不影响线上内容。阅读、词条、语法、拼句内容要记录原创/公版/授权来源及证明，未确认使用权的内容不得发布。
- API 返回富文本前由服务端清理危险标签、事件属性和不可信链接；公众号链接作为独立的内容来源类型，不把外部页面 HTML 抓进数据库。

## 内容表

| 表 | 核心字段 | 关系与约束 |
| --- | --- | --- |
| `series` | `id`, `title`, `translation`, `author`, `level`, `description`, `cover_asset`, `status`, `sort_order`, `rights_type`, `rights_note` | 小说/外刊目录。 |
| `reading_items` | `id`, `slug?`, `kind` (`article`/`chapter`), `series_id?`, `chapter_no?`, `title`, `translation`, `level`, `minutes`, `cover_asset`, `detail_image_asset`, `body_json`, `status`, `sort_order`, `rights_type`, `rights_note` | `chapter` 必须有 `series_id`、`chapter_no`；`article` 不需要。`cover_asset` 是方形封面路径，`detail_image_asset` 是详情页横图路径；旧文章的 `cover_asset` 也可能是本地图标代号。`body_json` 按段保存英文句子及对应中文段译。`(series_id, chapter_no)` 唯一。 |
| `reading_analyses` | `id`, `reading_item_id`, `type`, `format`, `html?`, `url?`, `title?`, `summary?`, `status` | 文章/章节专属解析；`reading_item_id` 唯一。富文本可保存 HTML 或 Markdown 原文，前台接口把 Markdown 转为安全 HTML；只有已发布解析对外可见。 |
| `words` | `id`, `slug?`, `headword`, `ipa`, `part_of_speech`, `meaning`, `frequency_label`, `status` | 后台单词库；同拼写不同词义允许不同 ID，不能只用 `headword` 做外键。用户侧不提供浏览整个库的入口。 |
| `word_details` | `id`, `word_id`, `type`, `html?`, `url?`, `title?`, `summary?`, `status` | 单词专属详解；`word_id` 唯一，不与文章或语法课程共享。 |
| `reading_words` | `reading_item_id`, `word_id`, `sort_order` | 一篇阅读内容的重点词汇；`(reading_item_id, word_id)` 唯一，排序决定详情页的 `3/8`。 |
| `reading_questions` | `id`, `reading_item_id`, `sort_order`, `question_type`, `kind` (`choice`/`fill`), `prompt`, `prompt_cn`, `options_json`, `answer`, `explanation` | 阅读理解配套题。`answer` 只用于服务端判题，不在初始题目响应中返回。 |
| `grammar_lessons` | `id`, `title`, `summary`, `level`, `status`, `sort_order` | 独立语法模块；不充当文章语法解析。 |
| `grammar_lesson_contents` | `id`, `grammar_lesson_id`, `type`, `html?`, `url?`, `title?`, `summary?`, `status` | 独立语法课程的内容；`grammar_lesson_id` 唯一。 |
| `grammar_questions` | `id`, `grammar_lesson_id`, `sort_order`, `kind`, `prompt`, `options_json`, `answer`, `explanation` | 独立语法练习，可从目前一题扩充为多题。 |
| `puzzle_sets` | `id`, `title`, `subtitle`, `icon_asset`, `source_reading_item_id?`, `status`, `sort_order` | `source_reading_item_id` 可为空；即使有关联，也由拼句模块独立管理、发布和排序。 |
| `puzzle_sentences` | `id`, `puzzle_set_id`, `sort_order`, `prompt_cn`, `answer_text`, `tokens_json`, `answer_token_ids_json` | `tokens_json` 给每个词块稳定标识，支持重复单词；正确顺序单独存 `answer_token_ids_json`，仅留服务端，不随题目返回。`(puzzle_set_id, sort_order)` 唯一。 |
| `daily_word_plans` | `id`, `plan_date`, `status`, `published_at` | `plan_date` 唯一；后一天直接切换新计划，前一天未学完也不顺延。 |
| `daily_word_plan_items` | `plan_id`, `word_id`, `sort_order` | 指向同一单词库；`(plan_id, word_id)` 和 `(plan_id, sort_order)` 唯一。 |

三类详解表各自支持 `richtext` 或 `officialAccount`。发布时校验富文本或公众号链接二者必填其一，公开接口仅返回已发布内容；后台写入富文本前仍须做安全清理。

`body_json` 示例（只说明形状，不代表最终存储方言）：

```json
[
  {
    "sentences": ["On a rainy afternoon, Lily found a blue umbrella."],
    "translation": "一个下雨的午后，莉莉发现了一把蓝色雨伞。"
  }
]
```

## 用户与学习记录表

| 表 | 核心字段 | 规则 |
| --- | --- | --- |
| `users` / `user_identities` | `users.id`, `name?`, `avatar_url?`; `user_identities.user_id`, `provider`, `provider_app_id`, `provider_subject` | 同一内部用户可关联微信小程序、未来 iOS 等身份。微信登录时 `provider=wechat_miniapp`、`provider_app_id=微信 AppID`、`provider_subject=openid`；三列组合唯一。不同小程序的 OpenID 分开识别，客户端不能自己指定 `user_id`。 |
| `reading_completions` | `user_id`, `reading_item_id`, `completed_at` | 仅点“阅读完成”才写入；`(user_id, reading_item_id)` 唯一。再次进入显示“已完成阅读”。 |
| `word_learning_events` | `id`, `user_id`, `word_id`, `source_kind` (`daily`/`reading`), `reading_item_id?`, `plan_date?`, `completed_at`, `request_id` | 从单词详情完成拼写时写入；精读重点词汇与每日单词共用此表。`(user_id, request_id)` 防重试重复写入；再次主动学习允许新事件。 |
| `daily_word_completions` | `user_id`, `plan_id`, `word_id`, `completed_at` | 只有每日单词路径完成详情→拼写才更新；在精读中先学过同词，不自动抵消当天任务。三列联合唯一。 |
| `word_favorites` | `user_id`, `word_id`, `created_at` | 收藏页只读进入单词详情，不触发学习流程。两列联合唯一。 |
| `puzzle_progress` | `user_id`, `puzzle_set_id`, `cursor_next`, `completed_at?`, `updated_at` | `cursor_next` 为下一句的零基序号：完成前两句后显示 `2/3`，再次进入第三句；从头开始重置为 0。两列联合唯一。 |
| `recent_activity` | `user_id`, `category` (`reading`/`grammar`/`word`/`puzzle`), `target_id`, `last_active_at` | 每个目标保留最近一次时间，用游标分页展示。阅读完成、单词学完、进入独立语法、开始/推进拼句时更新。清空最近学习只删此表，不删除完成状态和拼句进度。 |

微信登录用户不要求邮箱或密码，因此 `users.email` 和 `users.password` 允许为空；后台管理员仍使用邮箱和密码登录。`users.name` 和 `users.avatar_url` 可空，等用户主动设置后再保存；资料不作为身份凭据。`provider_app_id` 在新增身份时必须填写，后端用自己配置的 AppID，不接受客户端自行指定。

阅读理解与独立语法练习通过练习尝试及答案表保存每次作答快照、判定结果和完成成绩。每日单词的“直接拼写”只是客户端练习，不写 `word_learning_events`、`daily_word_completions` 或最近学习。

## 发布与编辑约束

- 已产生用户进度的拼句集合，不能原地重排或删减句子；需要新版本或显式进度迁移。阅读和词条 ID 也不能因修改标题而变化。
- 删除正在被文章、每日计划或用户记录引用的内容应改为下架/归档，不做级联硬删除。
- 版权信息至少记录 `rights_type`、来源/作者、授权范围和证明位置；公版作品也要核对所用英文文本与译文各自的权利状态。

## 从当前静态数据迁移

| 当前来源 | 迁移目标 | 注意点 |
| --- | --- | --- |
| `articles` | `reading_items(kind=article)`、`reading_questions`、`reading_words` | 导入时按旧标识查找或生成 `slug`，数据库分配整数 ID。 |
| `externalSeries[].chapters` | `series`、`reading_items(kind=chapter)`、配套题/词 | 给每章新建稳定 ID，不能继续用章节数组下标。 |
| `words` | `words`、`word_details` | 当前只有单词拼写作标识；为每条词义分配整数 ID。 |
| `grammar` / `articleGrammarContent` | `grammar_lessons` + `grammar_lesson_contents` / `reading_analyses` | 两类内容保持分离，文章解析与文章一对一。 |
| `puzzleSets` | `puzzle_sets`、`puzzle_sentences` | `sourceArticleId` 映射为可空的阅读来源 ID；句子需补稳定 ID。 |
| `dailyWordIndexes()` | `daily_word_plans`、`daily_word_plan_items` | 生产环境由后台发布每日清单，不再按本地数组轮换。 |
| `uni` 本地学习记录 | 上述用户记录表 | 对接时单独决定是否一次性导入测试期本地记录；不能把数组下标原样上传。 |

后端使用 Laravel 13 + PHP 8.4；本地开发数据库为独立 MySQL 8.0，部署在用户自有云服务器。自动化接口测试使用 SQLite；线上 MySQL 版本兼容性需在部署目标环境中验证。上线后不可用本地整库覆盖线上用户数据。详见 [本地开发与上线流程](./local-first-deployment.md)。
