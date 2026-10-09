<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ sets: Object, search: String, status: String })
const filters = reactive({ q: props.search || '', status: props.status || '' })
function filter(page = 1) { router.get('/admin/puzzles', { ...filters, page }, { preserveState: true, replace: true }) }
function toggle(row) { router.post(`/admin/puzzles/${row.id}/toggle`) }
</script>

<template>
  <AdminLayout title="拼句内容" section="内容管理 / 拼句内容" description="管理独立拼句与以精读文章为来源的拼句集合。">
    <template #actions><Link href="/admin/puzzles/create"><el-button type="primary">＋ 新建拼句</el-button></Link></template>
    <section class="card">
      <form class="filter-bar" @submit.prevent="filter()"><el-input v-model="filters.q" class="search" clearable placeholder="搜索拼句标题" /><el-select v-model="filters.status"><el-option label="全部状态" value="" /><el-option label="草稿" value="draft" /><el-option label="已发布" value="published" /></el-select><el-button type="primary" native-type="submit">筛选</el-button></form>
      <div v-if="!sets.data.length" class="empty-state"><strong>暂无拼句内容</strong></div>
      <div v-else class="table-wrap"><el-table :data="sets.data" stripe>
        <el-table-column label="顺序" prop="sort_order" width="80" />
        <el-table-column label="标题" min-width="230"><template #default="scope"><div class="table-primary">{{ scope.row.title }}</div><div class="table-secondary">{{ scope.row.subtitle || `#${scope.row.id}` }}</div></template></el-table-column>
        <el-table-column label="来源" width="130"><template #default="scope">{{ scope.row.source_reading_item_id ? `精读 #${scope.row.source_reading_item_id}` : '独立练习' }}</template></el-table-column>
        <el-table-column label="句子" prop="sentence_count" width="80" />
        <el-table-column label="状态" width="110"><template #default="scope"><el-tag :type="scope.row.status === 'published' ? 'success' : 'info'">{{ scope.row.status === 'published' ? '已发布' : '草稿' }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="170" fixed="right"><template #default="scope"><Link :href="`/admin/puzzles/${scope.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link><el-button link :type="scope.row.status === 'published' ? 'danger' : 'success'" @click="toggle(scope.row)">{{ scope.row.status === 'published' ? '下架' : '发布' }}</el-button></template></el-table-column>
      </el-table></div>
      <div v-if="sets.data.length" class="table-footer"><span>共 {{ sets.total }} 组</span><el-pagination background layout="prev, pager, next" :current-page="sets.current_page" :page-count="sets.last_page" @current-change="filter" /></div>
    </section>
  </AdminLayout>
</template>
