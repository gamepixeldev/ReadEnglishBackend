<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ users: Object, search: String })
const modes = reactive(Object.fromEntries((props.users?.data || []).map(user => [user.id, user.rewarded_ads_mode || 'inherit'])))
const modeLabels = { inherit: '跟随全局', enabled: '单独开启', disabled: '单独关闭' }
function doSearch(value) { router.get('/admin/users', value ? { q: value } : {}, { preserveState: true, replace: true }) }
function save(user) {
  router.put(`/admin/users/${user.id}/rewarded-ads`, { mode: modes[user.id] }, { preserveScroll: true })
}
function page(value) { router.get('/admin/users', { ...(props.search ? { q: props.search } : {}), page: value }, { preserveState: true, preserveScroll: true }) }
</script>

<template>
  <AdminLayout title="用户广告策略" section="用户服务 / 用户广告策略" description="单独设置某位用户是否观看激励广告；选择“跟随全局”可恢复默认策略。">
    <section class="card">
      <div class="card-header"><div><h2>小程序用户</h2><p>共 {{ users.total }} 位用户</p></div><el-input :model-value="search" clearable placeholder="按用户 ID 或昵称搜索" class="user-search" @input="doSearch" /></div>
      <el-table :data="users.data" stripe>
        <el-table-column label="用户" min-width="220"><template #default="scope"><div class="user-cell"><el-avatar :size="34" :src="scope.row.avatar_url || ''">{{ (scope.row.name || '用').slice(0,1) }}</el-avatar><div><strong>{{ scope.row.name || `用户 ${scope.row.id}` }}</strong><small>ID {{ scope.row.id }}</small></div></div></template></el-table-column>
        <el-table-column label="当前策略" min-width="170"><template #default="scope"><el-select v-model="modes[scope.row.id]" @change="save(scope.row)"><el-option v-for="(label,value) in modeLabels" :key="value" :label="label" :value="value" /></el-select></template></el-table-column>
        <el-table-column label="注册时间" min-width="180"><template #default="scope">{{ scope.row.created_at }}</template></el-table-column>
        <template #empty><el-empty description="没有找到用户" /></template>
      </el-table>
      <div class="pagination"><el-pagination v-if="users.last_page > 1" background layout="prev, pager, next" :current-page="users.current_page" :page-count="users.last_page" @current-change="page" /></div>
    </section>
  </AdminLayout>
</template>

<style scoped>
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.card-header h2{margin:0;color:#2d3a50;font-size:16px}.card-header p{margin:6px 0 0;color:#8995a7;font-size:13px}.user-search{width:260px}.user-cell{display:flex;align-items:center;gap:10px}.user-cell div{display:flex;flex-direction:column;gap:3px}.user-cell strong{color:#34445e;font-size:13px}.user-cell small{color:#8995a7;font-size:12px}.pagination{display:flex;justify-content:flex-end;margin-top:18px}
</style>
