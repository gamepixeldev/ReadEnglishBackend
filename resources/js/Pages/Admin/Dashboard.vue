<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
defineProps({ articleCount:Number,publishedArticles:Number,wordCount:Number,publishedWords:Number,userCount:Number,latestArticles:Array,latestWords:Array });
</script>
<template><AdminLayout title="概览" section="工作台" description="查看内容状态和最近更新。">
  <div class="metrics">
    <div class="card metric"><span class="metric-icon">▤</span><div class="metric-label">精读文章</div><div class="metric-value">{{ articleCount }}</div><div class="metric-note">已发布 {{ publishedArticles }} 篇</div></div>
    <div class="card metric"><span class="metric-icon">Aa</span><div class="metric-label">单词</div><div class="metric-value">{{ wordCount }}</div><div class="metric-note">已发布 {{ publishedWords }} 个</div></div>
    <div class="card metric"><span class="metric-icon">◉</span><div class="metric-label">普通用户</div><div class="metric-value">{{ userCount }}</div><div class="metric-note">不含管理员</div></div>
    <div class="card metric"><span class="metric-icon">✎</span><div class="metric-label">草稿内容</div><div class="metric-value">{{ (articleCount-publishedArticles)+(wordCount-publishedWords) }}</div><div class="metric-note">文章和单词待发布</div></div>
  </div>
  <div class="dashboard-grid">
    <section class="card"><div class="card-header"><h2>最近编辑的文章</h2><Link class="text-link" href="/admin/articles">查看全部 →</Link></div><div v-if="!latestArticles.length" class="empty-state">暂无文章</div><el-table v-else :data="latestArticles"><el-table-column label="文章" min-width="220"><template #default="s"><div class="table-primary">{{s.row.translation||s.row.title}}</div><div class="table-secondary">{{s.row.title}}</div></template></el-table-column><el-table-column label="状态" width="100"><template #default="s"><el-tag :type="s.row.status==='published'?'success':'info'">{{s.row.status==='published'?'已发布':'草稿'}}</el-tag></template></el-table-column><el-table-column label="" width="80"><template #default="s"><Link :href="`/admin/articles/${s.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link></template></el-table-column></el-table></section>
    <section class="card"><div class="card-header"><h2>最近编辑的单词</h2><Link class="text-link" href="/admin/words">查看全部 →</Link></div><div v-if="!latestWords.length" class="empty-state">暂无单词</div><el-table v-else :data="latestWords"><el-table-column label="单词" min-width="180"><template #default="s"><div class="table-primary">{{s.row.headword}}</div><div class="table-secondary">{{s.row.meaning}}</div></template></el-table-column><el-table-column label="状态" width="100"><template #default="s"><el-tag :type="s.row.status==='published'?'success':'info'">{{s.row.status==='published'?'已发布':'草稿'}}</el-tag></template></el-table-column><el-table-column label="" width="80"><template #default="s"><Link :href="`/admin/words/${s.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link></template></el-table-column></el-table></section>
  </div>
</AdminLayout></template>
