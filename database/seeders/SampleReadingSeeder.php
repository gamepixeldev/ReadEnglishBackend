<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleReadingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $body = [
                ['sentences' => ['On a rainy afternoon, Lily found a blue umbrella beside the bus stop.', 'It was clean, and its handle had a little silver star.', 'Lily looked around, but nobody was there.'], 'translation' => '一个下雨的午后，莉莉在公交站旁发现了一把蓝色雨伞。伞很干净，伞柄上还有一颗小小的银色星星。莉莉四处看了看，但周围没有人。', 'sentenceTranslations' => ['一个下雨的午后，莉莉在公交站旁发现了一把蓝色雨伞。', '伞很干净，伞柄上还有一颗小小的银色星星。', '莉莉四处看了看，但周围没有人。']],
                ['sentences' => ['She carried the umbrella to the small bakery on the corner.', 'The baker smiled and said, “A girl left it here this morning.”', 'Just then, a wet girl ran inside. Lily handed her the umbrella.'], 'translation' => '她把伞带到街角的小面包店。面包师笑着说：“一个女孩今天早上把它落在这里了。”就在这时，一个浑身湿透的女孩跑进店里，莉莉把伞递给了她。', 'sentenceTranslations' => ['她把伞带到街角的小面包店。', '面包师笑着说：“一个女孩今天早上把它落在这里了。”', '就在这时，一个浑身湿透的女孩跑进店里，莉莉把伞递给了她。']],
                ['sentences' => ['“Thank you!” the girl said.', 'Lily felt warm, even though the rain was cold.', 'A small act of kindness can brighten someone’s day.'], 'translation' => '“谢谢你！”女孩说道。虽然雨很冷，莉莉心里却暖暖的。一个小小的善举，也能照亮别人的一天。', 'sentenceTranslations' => ['“谢谢你！”女孩说道。', '虽然雨很冷，莉莉心里却暖暖的。', '一个小小的善举，也能照亮别人的一天。']],
            ];

            DB::table('reading_items')->updateOrInsert(['slug' => 'sample-blue-umbrella'], [
                'kind' => 'article',
                'title' => 'The Blue Umbrella',
                'translation' => '蓝色雨伞',
                'level' => '初级',
                'minutes' => 6,
                'cover_asset' => 'umbrella',
                'body_json' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'status' => 'published',
                'sort_order' => 1,
                'rights_type' => 'original',
                'rights_note' => '项目原创测试内容；上线前由内容负责人复核。',
                'updated_at' => $now,
                'created_at' => $now,
            ]);
            $articleId = DB::table('reading_items')->where('slug', 'sample-blue-umbrella')->value('id');

            DB::table('reading_analyses')->updateOrInsert(['reading_item_id' => $articleId], [
                'type' => 'richtext',
                'title' => '蓝色雨伞 · 本文语法解析',
                'html' => '<h3>用一般过去时讲故事</h3><p>故事发生在过去，因此主要动作使用过去式。<strong>Lily found a blue umbrella beside the bus stop.</strong> 这里的 found 是 find 的过去式。</p><p><strong>She carried the umbrella to the small bakery.</strong> carry 以辅音字母加 y 结尾，变过去式时改 y 为 i，再加 -ed。</p><h3>用 even though 表达转折</h3><p><strong>Lily felt warm, even though the rain was cold.</strong> even though 表示“尽管”，把外面的冷与 Lily 心里的温暖放在一起对比。英语里通常不再同时加 but。</p>',
                'status' => 'published',
                'updated_at' => $now,
                'created_at' => $now,
            ]);

            $words = [
                ['kindness', '/ˈkaɪndnəs/', 'n.', '善意；友好', '表示友善或体贴他人的行为。<br>A small act of kindness can brighten someone’s day.'],
                ['umbrella', '/ʌmˈbrelə/', 'n.', '雨伞；伞', '可数名词；复数是 umbrellas。<br>She opened her umbrella in the rain.'],
                ['beside', '/bɪˈsaɪd/', 'prep.', '在……旁边', '表示位置，后接名词。不要与 besides（此外）混淆。'],
                ['handle', '/ˈhændl/', 'n.', '把手；柄', 'umbrella handle 表示伞柄；handle 也可以作动词，意为“处理”。'],
                ['carry', '/ˈkæri/', 'v.', '拿；搬；携带', '过去式 carried。<br>She carried the umbrella to the bakery.'],
                ['corner', '/ˈkɔːrnər/', 'n.', '拐角；角落', 'on the corner 表示在拐角处。<br>There is a bakery on the corner.'],
                ['hand', '/hænd/', 'v.', '递；交给', 'hand someone something 表示把某物递给某人。<br>Lily handed her the umbrella.'],
                ['brighten', '/ˈbraɪtn/', 'v.', '使明亮；使开心', 'brighten someone’s day 表示让某人开心起来。'],
            ];
            foreach ($words as $index => [$headword, $ipa, $partOfSpeech, $meaning, $explanation]) {
                DB::table('words')->updateOrInsert(['slug' => 'sample-word-'.$headword], [
                    'headword' => $headword,
                    'ipa' => $ipa,
                    'part_of_speech' => $partOfSpeech,
                    'meaning' => $meaning,
                    'frequency_label' => '常用词',
                    'status' => 'published',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
                $wordId = DB::table('words')->where('slug', 'sample-word-'.$headword)->value('id');
                DB::table('word_details')->updateOrInsert(['word_id' => $wordId], [
                    'type' => 'richtext',
                    'title' => $headword.' · 单词详解',
                    'html' => '<h3>词义与用法</h3><p>'.$explanation.'</p>',
                    'status' => 'published',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
                DB::table('reading_words')->updateOrInsert(
                    ['reading_item_id' => $articleId, 'word_id' => $wordId],
                    ['sort_order' => $index + 1],
                );
            }

            $questions = [
                ['细节理解', 'Where did Lily find the umbrella?', '莉莉在哪里发现了这把雨伞？', ['At the bakery', 'Beside the bus stop', 'In her classroom', 'On the bus'], 1, '第一段明确提到 Lily 在公交站旁发现雨伞。'],
                ['词义猜测', 'What does “handed” mean in the story?', '结合语境，文中的 handed 是什么意思？', ['写下', '递给', '购买', '修理'], 1, 'Lily 把伞交还给那个女孩，因此 handed 意为“递给”。'],
                ['推理判断', 'How did Lily probably feel at the end?', '莉莉最后可能是什么心情？', ['Warm and happy', 'Angry and tired', 'Afraid of the girl', 'Sorry about the rain'], 0, '文章说 Lily felt warm，表明她因帮助别人而感到温暖。'],
                ['主旨归纳', 'What is the main idea of the story?', '这篇文章主要想表达什么？', ['How to make an umbrella', 'A small act of kindness can warm someone', 'Why buses are late', 'Where to buy bread'], 1, '归还雨伞的故事说明小小的善举也能带来温暖。'],
            ];
            foreach ($questions as $index => [$type, $prompt, $promptCn, $options, $answer, $explanation]) {
                DB::table('reading_questions')->updateOrInsert(['reading_item_id' => $articleId, 'sort_order' => $index + 1], [
                    'question_type' => $type,
                    'kind' => 'choice',
                    'prompt' => $prompt,
                    'prompt_cn' => $promptCn,
                    'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
                    'answer' => (string) $answer,
                    'explanation' => $explanation,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }
        });
    }
}
