<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ lessons: Object, search: String, status: String })
const filters = reactive({ q: props.search || '', status: props.status || '' })
function filter(page = 1) { router.get('/admin/grammar', { ...filters, page }, { preserveState: true, replace: true }) }
function toggle(row) { router.post(`/admin/grammar/${row.id}/toggle`) }
</script>

<template>
  <AdminLayout title="语法课程" section="内容管理 / 语法课程" description="维护独立语法讲解与随堂题目。文章专属解析仍在文章编辑页管理。">
    <template #actions><Link href="/admin/grammar/create"><el-button type="primary">＋ 新建课程</el-button></Link></template>
    <section class="card">
      <form class="filter-bar" @submit.prevent="filter()"><el-input v-model="filters.q" class="search" clearable placeholder="搜索课程标题" /><el-select v-model="filters.status"><el-option label="全部状态" value="" /><el-option label="草稿" value="draft" /><el-option label="已发布" value="published" /></el-select><el-button type="primary" native-type="submit">筛选</el-button></form>
      <div v-if="!lessons.data.length" class="empty-state"><strong>暂无语法课程</strong></div>
      <div v-else class="table-wrap"><el-table :data="lessons.data" stripe>
        <el-table-column label="顺序" prop="sort_order" width="80" />
        <el-table-column label="课程" min-width="240"><template #default="scope"><div class="table-primary">{{ scope.row.title }}</div><div class="table-secondary">#{{ scope.row.id }}</div></template></el-table-column>
        <el-table-column label="级别" prop="level" width="140" />
        <el-table-column label="题目" prop="question_count" width="90" />
        <el-table-column label="状态" width="110"><template #default="scope"><el-tag :type="scope.row.status === 'published' ? 'success' : 'info'">{{ scope.row.status === 'published' ? '已发布' : '草稿' }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="170" fixed="right"><template #default="scope"><Link :href="`/admin/grammar/${scope.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link><el-button link :type="scope.row.status === 'published' ? 'danger' : 'success'" @click="toggle(scope.row)">{{ scope.row.status === 'published' ? '下架' : '发布' }}</el-button></template></el-table-column>
      </el-table></div>
      <div v-if="lessons.data.length" class="table-footer"><span>共 {{ lessons.total }} 课</span><el-pagination background layout="prev, pager, next" :current-page="lessons.current_page" :page-count="lessons.last_page" @current-change="filter" /></div>
    </section>
  </AdminLayout>
</template>
