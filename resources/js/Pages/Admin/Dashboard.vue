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
    <section class="card"><div class="card-header"><h2>最近编辑的文章</h2><Link class="text-link" href="/admin/articles">查看全部 →</Link></div><div v-if="!latestArticles.length" class="empty-state">暂无文章</div><div v-else class="table-wrap"><table class="data-table"><thead><tr><th>文章</th><th>状态</th><th></th></tr></thead><tbody><tr v-for="item in latestArticles" :key="item.id"><td><div class="table-primary">{{ item.translation || item.title }}</div><div class="table-secondary">{{ item.title }}</div></td><td><span class="status" :class="item.status">{{ item.status === 'published'?'已发布':'草稿' }}</span></td><td><Link class="text-link" :href="`/admin/articles/${item.id}/edit`">编辑</Link></td></tr></tbody></table></div></section>
    <section class="card"><div class="card-header"><h2>最近编辑的单词</h2><Link class="text-link" href="/admin/words">查看全部 →</Link></div><div v-if="!latestWords.length" class="empty-state">暂无单词</div><div v-else class="table-wrap"><table class="data-table"><thead><tr><th>单词</th><th>状态</th><th></th></tr></thead><tbody><tr v-for="item in latestWords" :key="item.id"><td><div class="table-primary">{{ item.headword }}</div><div class="table-secondary">{{ item.meaning }}</div></td><td><span class="status" :class="item.status">{{ item.status === 'published'?'已发布':'草稿' }}</span></td><td><Link class="text-link" :href="`/admin/words/${item.id}/edit`">编辑</Link></td></tr></tbody></table></div></section>
  </div>
</AdminLayout></template>
