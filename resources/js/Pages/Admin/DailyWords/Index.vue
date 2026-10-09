<script setup>
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
defineProps({ plans: Object })
function toggle(row) { router.post(`/admin/daily-words/${row.id}/toggle`) }
</script>

<template>
  <AdminLayout title="每日单词" section="内容管理 / 每日单词" description="按日期安排当天的学习单词；没有手动计划的日期仍会使用当前自动计划。">
    <template #actions><Link href="/admin/daily-words/create"><el-button type="primary">＋ 安排一天</el-button></Link></template>
    <section class="card">
      <div v-if="!plans.data.length" class="empty-state"><strong>暂无每日计划</strong><span>选择日期与单词后保存，再发布。</span></div>
      <div v-else class="table-wrap"><el-table :data="plans.data" stripe>
        <el-table-column label="日期" min-width="180"><template #default="scope"><div class="table-primary">{{ scope.row.plan_date }}</div></template></el-table-column>
        <el-table-column label="单词数" prop="word_count" width="110" />
        <el-table-column label="状态" width="120"><template #default="scope"><el-tag :type="scope.row.status === 'published' ? 'success' : 'info'">{{ scope.row.status === 'published' ? '已发布' : '草稿' }}</el-tag></template></el-table-column>
        <el-table-column label="最后更新" prop="updated_at" min-width="170" />
        <el-table-column label="操作" width="170" fixed="right"><template #default="scope"><Link :href="`/admin/daily-words/${scope.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link><el-button link :type="scope.row.status === 'published' ? 'danger' : 'success'" @click="toggle(scope.row)">{{ scope.row.status === 'published' ? '下架' : '发布' }}</el-button></template></el-table-column>
      </el-table></div>
      <div v-if="plans.data.length" class="table-footer"><span>共 {{ plans.total }} 天</span><el-pagination background layout="prev, pager, next" :current-page="plans.current_page" :page-count="plans.last_page" @current-change="page => router.get('/admin/daily-words', { page }, { preserveState: true, replace: true })" /></div>
    </section>
  </AdminLayout>
</template>
