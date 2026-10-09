<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ plan: Object, selectedWords: Array })
const editing = Boolean(props.plan)
const form = useForm({ planDate: props.plan?.plan_date || '', wordIds: (props.selectedWords || []).map(word => word.id) })
const wordMap = ref(new Map((props.selectedWords || []).map(word => [word.id, word])))
const search = ref('')
const results = ref([])
const loading = ref(false)
const error = ref('')
const selected = computed(() => form.wordIds.map(id => wordMap.value.get(id)).filter(Boolean))
const availableResults = computed(() => results.value.filter(word => !form.wordIds.includes(word.id)))
let timer
let requestNumber = 0
watch(search, query => {
  clearTimeout(timer)
  const term = query.trim()
  requestNumber += 1
  results.value = []
  error.value = ''
  if (!term) { loading.value = false; return }
  const current = requestNumber
  loading.value = true
  timer = setTimeout(async () => {
    try {
      const response = await fetch(`/admin/daily-words/word-options?q=${encodeURIComponent(term)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      if (!response.ok) throw new Error('搜索失败，请重试')
      const data = await response.json()
      if (current === requestNumber) results.value = data.items || []
    } catch (cause) {
      if (current === requestNumber) error.value = cause.message
    } finally {
      if (current === requestNumber) loading.value = false
    }
  }, 260)
})
onBeforeUnmount(() => { clearTimeout(timer); requestNumber += 1 })
function add(word) {
  if (form.wordIds.includes(word.id) || form.wordIds.length >= 30) return
  wordMap.value = new Map(wordMap.value).set(word.id, word)
  form.wordIds = [...form.wordIds, word.id]
  search.value = ''
}
function remove(id) { form.wordIds = form.wordIds.filter(wordId => wordId !== id) }
function move(index, direction) {
  const target = index + direction
  if (target < 0 || target >= form.wordIds.length) return
  const ids = [...form.wordIds]
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  form.wordIds = ids
}
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/daily-words/${props.plan.id}` : '/admin/daily-words') }
function toggle() { router.post(`/admin/daily-words/${props.plan.id}/toggle`) }
</script>

<template>
  <AdminLayout :title="editing ? '编辑每日单词' : '安排每日单词'" section="内容管理 / 每日单词" description="为指定日期挑选并排序单词。">
    <template #actions><Link href="/admin/daily-words"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel">
      <div class="form-stack"><section class="card">
        <div class="card-header"><div><h2>计划内容</h2><p>选择已发布单词，按下方顺序展示。</p></div></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-field"><label>计划日期 <b class="required-mark">*</b></label><input v-model="form.planDate" class="admin-native-input" type="date" :disabled="editing" /><small v-if="form.errors.planDate" class="form-error">{{ form.errors.planDate }}</small></div>
            <div class="form-field wide"><label>搜索单词</label><el-input v-model="search" clearable placeholder="输入英文单词或中文释义后搜索" /><p class="helper">只显示搜索结果；已选单词在下方管理，最多 30 个。</p>
              <div v-if="search.trim()" class="word-search-results">
                <p v-if="loading" class="helper">搜索中…</p>
                <p v-else-if="error" class="form-error">{{ error }}</p>
                <template v-else-if="availableResults.length"><button v-for="word in availableResults" :key="word.id" type="button" class="word-search-option" @click="add(word)"><strong>{{ word.headword }}</strong><span>{{ word.meaning }}</span><span>＋ 添加</span></button></template>
                <p v-else class="helper">没有可添加的匹配单词</p>
              </div>
            </div>
          </div>
          <div v-if="selected.length" class="daily-word-order"><div v-for="(word, index) in selected" :key="word.id" class="daily-word-row"><strong>{{ index + 1 }}. {{ word.headword }}</strong><span>{{ word.meaning }}</span><el-button link :disabled="index === 0" @click="move(index, -1)">上移</el-button><el-button link :disabled="index === selected.length - 1" @click="move(index, 1)">下移</el-button><el-button link type="danger" @click="remove(word.id)">移除</el-button></div></div>
          <small v-if="form.errors.wordIds" class="form-error">{{ form.errors.wordIds }}</small>
        </div>
      </section></div>
      <aside class="form-stack editor-actions-panel"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body">
        <template v-if="editing"><p><el-tag :type="plan.status === 'published' ? 'success' : 'info'">{{ plan.status === 'published' ? '已发布' : '草稿' }}</el-tag></p><p class="helper">已有用户学过当天单词时，不能再调整词表和顺序。</p></template>
        <p v-else class="helper">创建后默认为草稿。</p>
        <el-button type="primary" :loading="form.processing" @click="save">保存计划</el-button>
        <el-button v-if="editing" :type="plan.status === 'published' ? 'danger' : 'default'" :plain="plan.status === 'published'" @click="toggle">{{ plan.status === 'published' ? '下架计划' : '发布计划' }}</el-button>
      </div></section></aside>
    </div>
  </AdminLayout>
</template>

<style scoped>
.admin-native-input{width:100%;box-sizing:border-box;height:34px;border:1px solid #dcdfe6;border-radius:4px;padding:0 11px;color:#303133;background:white}.admin-native-input:disabled{background:#f5f7fa;color:#909399}
.daily-word-order{margin:22px 0;border:1px solid #ebeef5;border-radius:8px;overflow:hidden}.daily-word-row{display:flex;align-items:center;gap:14px;padding:10px 14px;border-bottom:1px solid #ebeef5}.daily-word-row:last-child{border-bottom:0}.daily-word-row strong{min-width:140px}.daily-word-row span{flex:1;color:#909399}
.word-search-results{margin-top:8px;border:1px solid #dce2eb;border-radius:8px;max-height:260px;overflow:auto}.word-search-results>p{padding:10px 14px;margin:0}.word-search-option{width:100%;border:0;border-bottom:1px solid #edf0f5;background:#fff;padding:10px 14px;display:flex;align-items:center;gap:14px;text-align:left;cursor:pointer;color:#344157}.word-search-option:last-child{border-bottom:0}.word-search-option:hover{background:#f5f7ff}.word-search-option strong{min-width:130px}.word-search-option span:nth-child(2){flex:1}.word-search-option span:last-child{color:#4568e9}
</style>
