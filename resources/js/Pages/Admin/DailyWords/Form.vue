<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ plan: Object, selectedWords: Array })
const editing = Boolean(props.plan)
const form = useForm({ planDate: props.plan?.plan_date || '', wordIds: (props.selectedWords || []).map(word => word.id) })
const options = ref([...(props.selectedWords || [])])
const loading = ref(false)
const selected = computed(() => form.wordIds.map(id => options.value.find(word => word.id === id)).filter(Boolean))
async function searchWords(query) {
  loading.value = true
  try {
    const response = await fetch(`/admin/daily-words/word-options?q=${encodeURIComponent(query)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('搜索失败')
    const data = await response.json()
    const merged = new Map(options.value.map(word => [word.id, word]))
    for (const word of data.items || []) merged.set(word.id, word)
    options.value = [...merged.values()]
  } catch (_) { /* 保留已选择的单词；后台验证会阻止无效提交 */ }
  finally { loading.value = false }
}
function move(index, direction) {
  const target = index + direction
  if (target < 0 || target >= form.wordIds.length) return
  const ids = [...form.wordIds]
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  form.wordIds = ids
}
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/daily-words/${props.plan.id}` : '/admin/daily-words') }
function toggle() { router.post(`/admin/daily-words/${props.plan.id}/toggle`) }
searchWords('')
</script>

<template>
  <AdminLayout :title="editing ? '编辑每日单词' : '安排每日单词'" section="内容管理 / 每日单词" description="为指定日期挑选并排序单词；当天未完成的计划不会自动延续到第二天。">
    <template #actions><Link href="/admin/daily-words"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel"><div class="form-stack"><section class="card"><div class="card-header"><div><h2>计划内容</h2><p>先保存为草稿，确认顺序后再发布。</p></div></div><div class="card-body">
      <div class="form-grid"><div class="form-field"><label>计划日期 <b class="required-mark">*</b></label><input v-model="form.planDate" class="admin-native-input" type="date" :disabled="editing" /></div><div class="form-field wide"><label>选择已发布单词 <b class="required-mark">*</b></label><el-select v-model="form.wordIds" multiple filterable remote :remote-method="searchWords" :loading="loading" collapse-tags :max-collapse-tags="3" placeholder="输入单词或中文释义搜索"><el-option v-for="word in options" :key="word.id" :value="word.id" :label="`${word.headword} · ${word.meaning}`" :disabled="word.status === 'draft'" /></el-select><p class="helper">最多 30 个。修改选择后，可在下方调整展示顺序。</p></div></div>
      <div v-if="selected.length" class="daily-word-order"><div v-for="(word, index) in selected" :key="word.id" class="daily-word-row"><strong>{{ index + 1 }}. {{ word.headword }}</strong><span>{{ word.meaning }}</span><el-button link :disabled="index === 0" @click="move(index, -1)">上移</el-button><el-button link :disabled="index === selected.length - 1" @click="move(index, 1)">下移</el-button></div></div>
      <div class="section-actions"><el-button type="primary" :loading="form.processing" @click="save">保存计划</el-button></div>
    </div></section></div><aside class="form-stack"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body"><template v-if="editing"><p><el-tag :type="plan.status === 'published' ? 'success' : 'info'">{{ plan.status === 'published' ? '已发布' : '草稿' }}</el-tag></p><p class="helper">已有用户学过当天单词时，不允许改动词表和顺序。</p><el-button :type="plan.status === 'published' ? 'danger' : 'primary'" :plain="plan.status === 'published'" @click="toggle">{{ plan.status === 'published' ? '下架计划' : '发布计划' }}</el-button></template><p v-else class="helper">创建后默认为草稿。</p></div></section></aside></div>
  </AdminLayout>
</template>

<style scoped>
.admin-native-input{width:100%;box-sizing:border-box;height:34px;border:1px solid #dcdfe6;border-radius:4px;padding:0 11px;color:#303133;background:white}.admin-native-input:disabled{background:#f5f7fa;color:#909399}.daily-word-order{margin:22px 0;border:1px solid #ebeef5;border-radius:8px;overflow:hidden}.daily-word-row{display:flex;align-items:center;gap:14px;padding:10px 14px;border-bottom:1px solid #ebeef5}.daily-word-row:last-child{border-bottom:0}.daily-word-row strong{min-width:140px}.daily-word-row span{flex:1;color:#909399}
</style>
