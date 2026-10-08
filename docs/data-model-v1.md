# 数据模型 v1（静态版 → API 版）

状态：第一阶段设计稿。基线为 Git 标签 `static-ui-final-2026-10-08`。本文是数据库无关的逻辑模型；选定数据库后再落地 DDL 和迁移脚本。

## 边界与统一约定

- 四个入口（精读、单词、语法、拼句）可以独立使用。文章只通过关联关系引用词条；文章语法解析是该阅读内容独有的富文本，不等于独立语法课程；拼句集合可以选择一篇阅读内容作为来源，但不依赖它才能存在。
- 普通精读文章与小说/外刊章节共用 `reading_items`，前端复用同一阅读详情页。章节另有 `series_id` 和 `chapter_no`。
- 对外一律使用稳定的字符串 `id`，不能把当前数组下标当作 API ID。列表排序使用单独的 `sort_order`。所有时间为带时区的时间戳；每日计划按 `Asia/Shanghai` 的自然日计算。
- 内容只有 `published` 状态可出现在公开接口。后台保存草稿不影响线上内容。阅读、词条、语法、拼句内容要记录原创/公版/授权来源及证明，未确认使用权的内容不得发布。
- API 返回富文本前由服务端清理危险标签、事件属性和不可信链接；公众号链接作为独立的内容来源类型，不把外部页面 HTML 抓进数据库。

## 内容表

| 表 | 核心字段 | 关系与约束 |
| --- | --- | --- |
| `content_sources` | `id`, `type` (`richtext`/`official_account`), `html`, `url`, `title`, `summary`, `created_at`, `updated_at` | `richtext` 需要 `html`；`official_account` 需要合规 URL。词条、独立语法和文章语法解析可各自引用一条。 |
| `series` | `id`, `title`, `translation`, `author`, `level`, `description`, `cover_asset`, `status`, `sort_order`, `rights_type`, `rights_note` | 小说/外刊目录；`id` 稳定。 |
| `reading_items` | `id`, `kind` (`article`/`chapter`), `series_id?`, `chapter_no?`, `title`, `translation`, `level`, `minutes`, `cover_asset`, `body_json`, `grammar_source_id?`, `status`, `sort_order`, `rights_type`, `rights_note` | `chapter` 必须有 `series_id`、`chapter_no`；`article` 不需要。`body_json` 按段保存英文句子及对应中文段译。`(series_id, chapter_no)` 唯一。 |
| `words` | `id`, `headword`, `ipa`, `part_of_speech`, `meaning`, `frequency_label`, `content_source_id?`, `status` | 后台单词库；同拼写不同词义允许不同 ID，不能只用 `headword` 做外键。用户侧不提供浏览整个库的入口。 |
| `reading_words` | `reading_item_id`, `word_id`, `sort_order` | 一篇阅读内容的重点词汇；`(reading_item_id, word_id)` 唯一，排序决定详情页的 `3/8`。 |
| `reading_questions` | `id`, `reading_item_id`, `sort_order`, `question_type`, `kind` (`choice`/`fill`), `prompt`, `prompt_cn`, `options_json`, `answer`, `explanation` | 阅读理解配套题。`answer` 只用于服务端判题，不在初始题目响应中返回。 |
| `grammar_lessons` | `id`, `title`, `summary`, `level`, `content_source_id`, `status`, `sort_order` | 独立语法模块；不充当文章语法解析。 |
| `grammar_questions` | `id`, `grammar_lesson_id`, `sort_order`, `kind`, `prompt`, `options_json`, `answer`, `explanation` | 独立语法练习，可从目前一题扩充为多题。 |
| `puzzle_sets` | `id`, `title`, `subtitle`, `icon_asset`, `source_reading_item_id?`, `status`, `sort_order` | `source_reading_item_id` 可为空；即使有关联，也由拼句模块独立管理、发布和排序。 |
| `puzzle_sentences` | `id`, `puzzle_set_id`, `sort_order`, `prompt_cn`, `answer_text`, `tokens_json`, `answer_token_ids_json` | `tokens_json` 给每个词块稳定标识，支持重复单词；正确顺序单独存 `answer_token_ids_json`，仅留服务端，不随题目返回。`(puzzle_set_id, sort_order)` 唯一。 |
| `daily_word_plans` | `plan_date`, `status`, `published_at` | 每日一份计划；后一天直接切换新计划，前一天未学完也不顺延。 |
| `daily_word_plan_items` | `plan_date`, `word_id`, `sort_order` | 指向同一单词库；`(plan_date, word_id)` 和 `(plan_date, sort_order)` 唯一。 |

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
| `users` / `user_identities` | `users.id`; `user_identities.user_id`, `provider`, `provider_subject` | 同一内部用户可逐步关联微信小程序、未来 iOS 等身份；`(provider, provider_subject)` 唯一。客户端不能自己指定 `user_id`。 |
| `reading_completions` | `user_id`, `reading_item_id`, `completed_at` | 仅点“阅读完成”才写入；`(user_id, reading_item_id)` 唯一。再次进入显示“已完成阅读”。 |
| `word_learning_events` | `id`, `user_id`, `word_id`, `source_kind` (`daily`/`reading`), `reading_item_id?`, `plan_date?`, `completed_at`, `request_id` | 从单词详情完成拼写时写入；精读重点词汇与每日单词共用此表。`(user_id, request_id)` 防重试重复写入；再次主动学习允许新事件。 |
| `daily_word_completions` | `user_id`, `plan_date`, `word_id`, `completed_at` | 只有每日单词路径完成详情→拼写才更新；在精读中先学过同词，不自动抵消当天任务。三列联合唯一。 |
| `word_favorites` | `user_id`, `word_id`, `created_at` | 收藏页只读进入单词详情，不触发学习流程。两列联合唯一。 |
| `puzzle_progress` | `user_id`, `puzzle_set_id`, `cursor_next`, `completed_at?`, `updated_at` | `cursor_next` 为下一句的零基序号：完成前两句后显示 `2/3`，再次进入第三句；从头开始重置为 0。两列联合唯一。 |
| `recent_activity` | `user_id`, `category` (`reading`/`grammar`/`word`/`puzzle`), `target_id`, `last_active_at` | 每个目标保留最近一次时间，用游标分页展示。阅读完成、单词学完、进入独立语法、开始/推进拼句时更新。清空最近学习只删此表，不删除完成状态和拼句进度。 |

当前静态版的阅读理解、独立语法练习不单独保存答题成绩；v1 不新增成绩表。每日单词的“直接拼写”只是练习，不写 `word_learning_events`、`daily_word_completions` 或最近学习。若以后需要错题本/成绩统计，再单独设计 `question_attempts`。

## 发布与编辑约束

- 已产生用户进度的拼句集合，不能原地重排或删减句子；需要新版本或显式进度迁移。阅读和词条 ID 也不能因修改标题而变化。
- 删除正在被文章、每日计划或用户记录引用的内容应改为下架/归档，不做级联硬删除。
- 版权信息至少记录 `rights_type`、来源/作者、授权范围和证明位置；公版作品也要核对所用英文文本与译文各自的权利状态。

## 从当前静态数据迁移

| 当前来源 | 迁移目标 | 注意点 |
| --- | --- | --- |
| `articles` | `reading_items(kind=article)`、`reading_questions`、`reading_words` | 现有 `article.id` 可作为初始稳定 ID。 |
| `externalSeries[].chapters` | `series`、`reading_items(kind=chapter)`、配套题/词 | 给每章新建稳定 ID，不能继续用章节数组下标。 |
| `words` | `words`、`content_sources` | 当前只有单词拼写作标识；为每条词义分配稳定 ID。 |
| `grammar` / `articleGrammarContent` | `grammar_lessons` / `reading_items.grammar_source_id` | 两类内容保持分离；现有语法课程需补 ID。 |
| `puzzleSets` | `puzzle_sets`、`puzzle_sentences` | `sourceArticleId` 映射为可空的阅读来源 ID；句子需补稳定 ID。 |
| `dailyWordIndexes()` | `daily_word_plans`、`daily_word_plan_items` | 生产环境由后台发布每日清单，不再按本地数组轮换。 |
| `uni` 本地学习记录 | 上述用户记录表 | 对接时单独决定是否一次性导入测试期本地记录；不能把数组下标原样上传。 |

已确定：后端使用 Laravel 13 + PHP 8.4；本地开发使用 MySQL 8.0 独立数据库，首次上线目标为 MySQL 8.4 LTS，部署在用户自有云服务器。首次可从干净内容库导出并导入线上空库；上线后不能再用本地整库覆盖线上用户数据。已在本项目的 `database/migrations/` 创建迁移，并在本地 MySQL 8.0 项目专用数据库中运行成功；完整 MySQL 集成测试仍待进行。正式用户登录方式和测试期本地记录是否迁移尚未确定。详见 [本地开发与上线流程](./local-first-deployment.md)。
