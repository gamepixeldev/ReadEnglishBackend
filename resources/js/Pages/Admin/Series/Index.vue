<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
const props=defineProps({seriesItems:Object,search:String,status:String});
const filters=reactive({q:props.search||'',status:props.status||''});
function filter(){router.get('/admin/series',filters,{preserveState:true,replace:true})}
function toggle(item){router.post(`/admin/series/${item.id}/${item.status==='published'?'unpublish':'publish'}`)}
</script>
<template><AdminLayout title="外刊小说" section="内容管理 / 外刊小说" description="维护小说信息和章节目录；章节可独立编辑、排序与上下架。"><template #actions><Link href="/admin/series/create"><el-button type="primary">＋ 新建小说</el-button></Link></template>
  <section class="card"><form class="filter-bar" @submit.prevent="filter"><el-input v-model="filters.q" class="search" clearable placeholder="搜索小说中英文标题"/><el-select v-model="filters.status"><el-option label="全部状态" value=""/><el-option label="草稿" value="draft"/><el-option label="已发布" value="published"/></el-select><el-button type="primary" native-type="submit">筛选</el-button></form>
  <div v-if="!seriesItems.data.length" class="empty-state"><strong>{{filters.q||filters.status?'没有匹配的小说':'还没有外刊小说'}}</strong><span>创建小说后，可继续添加章节和阅读内容。</span></div>
  <div v-else class="table-wrap"><el-table :data="seriesItems.data" stripe><el-table-column label="小说" min-width="280"><template #default="scope"><div class="table-primary">{{scope.row.translation||scope.row.title}}</div><div class="table-secondary">{{scope.row.title}}</div></template></el-table-column><el-table-column prop="author" label="作者" min-width="130"><template #default="scope">{{scope.row.author||'—'}}</template></el-table-column><el-table-column label="章节" width="100"><template #default="scope">{{scope.row.chapter_count}} 章</template></el-table-column><el-table-column label="状态" width="110"><template #default="scope"><el-tag :type="scope.row.status==='published'?'success':'info'">{{scope.row.status==='published'?'已上架':'草稿'}}</el-tag></template></el-table-column><el-table-column label="操作" width="210" fixed="right"><template #default="scope"><div class="table-actions"><Link :href="`/admin/series/${scope.row.id}/edit`"><el-button link type="primary">编辑目录</el-button></Link><el-button size="small" :type="scope.row.status==='published'?'danger':'primary'" :plain="scope.row.status==='published'" @click="toggle(scope.row)">{{scope.row.status==='published'?'下架':'发布'}}</el-button></div></template></el-table-column></el-table></div>
  <div v-if="seriesItems.data.length" class="table-footer"><span>共 {{seriesItems.total}} 部</span><el-pagination background layout="prev, pager, next" :current-page="seriesItems.current_page" :page-count="seriesItems.last_page" @current-change="page=>router.get('/admin/series',{...filters,page},{preserveState:true,replace:true})"/></div>
  </section>
</AdminLayout></template>
