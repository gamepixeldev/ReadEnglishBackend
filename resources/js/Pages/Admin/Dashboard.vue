<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '../../Layouts/AdminLayout.vue'

const props = defineProps({
  articleCount:Number, publishedArticles:Number, wordCount:Number, publishedWords:Number,
  seriesCount:Number, publishedSeries:Number, chapterCount:Number, publishedChapters:Number,
  grammarCount:Number, publishedGrammar:Number, puzzleCount:Number, publishedPuzzles:Number,
  dailyPlanCount:Number, publishedDailyPlans:Number, userCount:Number, newUsersThisMonth:Number,
  activeUsersToday:Number, readingCompletions:Number, readingCompletionsToday:Number,
  wordLearningEvents:Number, wordLearningEventsToday:Number, puzzlesCompleted:Number,
  puzzlesInProgress:Number, latestArticles:Array, latestWords:Array,
})

const contentMetrics = computed(() => [
  { label:'精读文章', icon:'▤', total:props.articleCount, published:props.publishedArticles, href:'/admin/articles' },
  { label:'外刊小说', icon:'▣', total:props.seriesCount, published:props.publishedSeries, href:'/admin/series' },
  { label:'外刊章节', icon:'▥', total:props.chapterCount, published:props.publishedChapters, href:'/admin/series' },
  { label:'单词', icon:'Aa', total:props.wordCount, published:props.publishedWords, href:'/admin/words' },
  { label:'语法课程', icon:'文', total:props.grammarCount, published:props.publishedGrammar, href:'/admin/grammar' },
  { label:'拼句文章', icon:'拼', total:props.puzzleCount, published:props.publishedPuzzles, href:'/admin/puzzles' },
  { label:'每日计划', icon:'日', total:props.dailyPlanCount, published:props.publishedDailyPlans, href:'/admin/daily-words' },
])

const activityMetrics = computed(() => [
  { label:'普通用户', value:props.userCount, note:`本月新增 ${props.newUsersThisMonth} 人`, icon:'◉', tone:'blue' },
  { label:'今日活跃用户', value:props.activeUsersToday, note:'按今日学习记录统计', icon:'↗', tone:'green' },
  { label:'阅读完成', value:props.readingCompletionsToday, note:`累计 ${props.readingCompletions} 次`, icon:'✓', tone:'purple' },
  { label:'单词学习', value:props.wordLearningEventsToday, note:`累计 ${props.wordLearningEvents} 次`, icon:'Aa', tone:'orange' },
  { label:'拼句进度', value:props.puzzlesInProgress, note:`已完成 ${props.puzzlesCompleted} 项`, icon:'拼', tone:'teal' },
])

function publishRate(item) { return item.total ? Math.round(item.published / item.total * 100) : 0 }
</script>

<template>
  <AdminLayout title="概览" section="工作台" description="内容供给、发布进度与学习活跃情况。">
    <section class="overview-section">
      <div class="section-heading"><div><h2>内容概览</h2><p>查看各模块的内容规模与发布进度</p></div><Link href="/admin/articles/create" class="overview-primary-action">＋ 新建文章</Link></div>
      <div class="content-metrics">
        <Link v-for="item in contentMetrics" :key="item.label" :href="item.href" class="overview-card">
          <div class="overview-card-top"><span class="overview-icon">{{ item.icon }}</span><span class="overview-arrow">↗</span></div>
          <div class="overview-label">{{ item.label }}</div>
          <div class="overview-value">{{ item.total }}</div>
          <div class="overview-meta"><span>已发布 {{ item.published }}</span><span>{{ publishRate(item) }}%</span></div>
          <div class="overview-track"><span :style="{width: `${publishRate(item)}%`}"></span></div>
        </Link>
      </div>
    </section>

    <section class="overview-section activity-section">
      <div class="section-heading"><div><h2>学习概况</h2><p>用户与学习记录统计</p></div></div>
      <div class="activity-metrics">
        <div v-for="item in activityMetrics" :key="item.label" class="activity-card">
          <span class="activity-icon" :class="`tone-${item.tone}`">{{ item.icon }}</span>
          <div class="activity-copy"><div class="activity-label">{{ item.label }}</div><div class="activity-value">{{ item.value }}</div><div class="activity-note">{{ item.note }}</div></div>
        </div>
      </div>
    </section>

    <div class="dashboard-grid">
      <section class="card"><div class="card-header"><h2>最近编辑的文章</h2><Link class="text-link" href="/admin/articles">查看全部 →</Link></div><div v-if="!latestArticles.length" class="empty-state">暂无文章</div><el-table v-else :data="latestArticles"><el-table-column label="文章" min-width="220"><template #default="s"><div class="table-primary">{{s.row.translation||s.row.title}}</div><div class="table-secondary">{{s.row.title}}</div></template></el-table-column><el-table-column label="状态" width="100"><template #default="s"><el-tag :type="s.row.status==='published'?'success':'info'">{{s.row.status==='published'?'已发布':'草稿'}}</el-tag></template></el-table-column><el-table-column label="" width="80"><template #default="s"><Link :href="`/admin/articles/${s.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link></template></el-table-column></el-table></section>
      <section class="card"><div class="card-header"><h2>最近编辑的单词</h2><Link class="text-link" href="/admin/words">查看全部 →</Link></div><div v-if="!latestWords.length" class="empty-state">暂无单词</div><el-table v-else :data="latestWords"><el-table-column label="单词" min-width="180"><template #default="s"><div class="table-primary">{{s.row.headword}}</div><div class="table-secondary">{{s.row.meaning}}</div></template></el-table-column><el-table-column label="状态" width="100"><template #default="s"><el-tag :type="s.row.status==='published'?'success':'info'">{{s.row.status==='published'?'已发布':'草稿'}}</el-tag></template></el-table-column><el-table-column label="" width="80"><template #default="s"><Link :href="`/admin/words/${s.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link></template></el-table-column></el-table></section>
    </div>
  </AdminLayout>
</template>

<style scoped>
.overview-section{margin-bottom:25px}.section-heading{display:flex;justify-content:space-between;align-items:center;margin:0 1px 13px}.section-heading h2{margin:0;color:#2d3a50;font-size:16px;font-weight:700}.section-heading p{margin:5px 0 0;color:#8a96a7;font-size:12px}.overview-primary-action{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:0 14px;border-radius:7px;background:#4568e9;color:white;font-size:12px;font-weight:650}.overview-primary-action:hover{background:#3758d9}.content-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.overview-card{min-width:0;padding:17px 18px 15px;background:white;border:1px solid #e6eaf1;border-radius:10px;box-shadow:0 2px 10px #172c4a05;transition:transform .16s ease,border-color .16s ease}.overview-card:hover{transform:translateY(-2px);border-color:#cbd5f5}.overview-card-top{display:flex;align-items:center;justify-content:space-between}.overview-icon,.activity-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#edf1ff;color:#4568e9;font-size:14px;font-weight:750}.overview-arrow{color:#b3bdcb;font-size:15px}.overview-label{margin-top:13px;color:#758197;font-size:12px}.overview-value{margin-top:3px;color:#25344b;font-size:27px;line-height:1.2;font-weight:730;font-variant-numeric:tabular-nums}.overview-meta{display:flex;justify-content:space-between;gap:6px;margin-top:11px;color:#8793a4;font-size:11px}.overview-meta span:last-child{color:#657795;font-weight:650}.overview-track{height:4px;margin-top:8px;border-radius:5px;background:#eef1f6;overflow:hidden}.overview-track span{display:block;height:100%;border-radius:inherit;background:#6dbb97}.activity-metrics{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:13px}.activity-card{display:flex;align-items:flex-start;gap:12px;min-width:0;padding:16px;background:#fff;border:1px solid #e6eaf1;border-radius:10px}.activity-copy{min-width:0}.activity-icon{flex:none}.tone-blue{background:#edf1ff;color:#4568e9}.tone-green{background:#e9f7f0;color:#39956d}.tone-purple{background:#f1edff;color:#7359c9}.tone-orange{background:#fff3e5;color:#c17c2c}.tone-teal{background:#e8f6f6;color:#398b91}.activity-label{color:#758197;font-size:11px;white-space:nowrap}.activity-value{margin-top:5px;color:#25344b;font-size:23px;font-weight:730;line-height:1.15;font-variant-numeric:tabular-nums}.activity-note{margin-top:5px;color:#919cac;font-size:10px;white-space:nowrap}.dashboard-grid{margin-top:2px;align-items:start}.dashboard-grid>.card+.card{margin-top:0}@media(max-width:1200px){.activity-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:900px){.content-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-grid{grid-template-columns:1fr}}@media(max-width:580px){.activity-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.section-heading{align-items:flex-start;gap:10px}.overview-card{padding:14px}.activity-card{padding:13px 11px;gap:8px}.activity-note{white-space:normal}.overview-primary-action{white-space:nowrap}}
</style>
