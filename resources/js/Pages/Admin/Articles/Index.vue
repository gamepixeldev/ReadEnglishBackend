<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
const props=defineProps({articles:Object,search:String,status:String});
const filters=reactive({q:props.search||'',status:props.status||''});
function filter(){router.get('/admin/articles',filters,{preserveState:true,replace:true});}
function publish(item){router.post(`/admin/articles/${item.id}/${item.status==='published'?'unpublish':'publish'}`);}
</script>
<template><AdminLayout title="精读文章" section="内容管理 / 精读文章" description="管理文章正文、解析、重点词汇、阅读理解题与发布状态。"><template #actions><Link class="btn" href="/admin/articles/create">＋ 新建文章</Link></template>
  <section class="card"><form class="filter-bar" @submit.prevent="filter"><input v-model="filters.q" class="input search" type="search" placeholder="搜索中英文标题"><select v-model="filters.status" class="select"><option value="">全部状态</option><option value="draft">草稿</option><option value="published">已发布</option></select><button class="btn secondary" type="submit">筛选</button><button v-if="filters.q||filters.status" class="text-link filter-clear" type="button" @click="filters.q='';filters.status='';filter()">清空</button></form>
  <div v-if="!articles.data.length" class="empty-state"><strong>{{ filters.q||filters.status?'没有匹配的文章':'还没有文章' }}</strong><span>{{ filters.q||filters.status?'试试其他搜索词或状态':'从新建文章开始添加内容' }}</span></div>
  <div v-else class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>文章标题</th><th>级别</th><th>状态</th><th>最后更新</th><th>操作</th></tr></thead><tbody><tr v-for="item in articles.data" :key="item.id"><td><span class="badge-id">#{{ item.id }}</span></td><td><div class="table-primary">{{ item.translation||item.title }}</div><div v-if="item.translation" class="table-secondary">{{ item.title }}</div></td><td>{{ item.level||'—' }}</td><td><span class="status" :class="item.status">{{ item.status==='published'?'已发布':'草稿' }}</span></td><td>{{ item.updated_at||'—' }}</td><td class="row-actions"><Link class="text-link" :href="`/admin/articles/${item.id}/edit`">编辑</Link><button class="btn small" :class="item.status==='published'?'danger':'secondary'" @click="publish(item)">{{ item.status==='published'?'下架':'发布' }}</button></td></tr></tbody></table></div>
  <div v-if="articles.data.length" class="table-footer"><span>共 {{ articles.total }} 篇</span><div class="pager"><Link v-if="articles.prev_page_url" :href="articles.prev_page_url">上一页</Link><span v-else class="disabled">上一页</span><span>{{ articles.current_page }} / {{ articles.last_page }}</span><Link v-if="articles.next_page_url" :href="articles.next_page_url">下一页</Link><span v-else class="disabled">下一页</span></div></div>
  </section>
</AdminLayout></template>
