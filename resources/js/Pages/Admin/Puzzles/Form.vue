<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ElMessage, ElMessageBox } from 'element-plus'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ set: Object, sentences: Array, readings: Array })
const editing = Boolean(props.set)
let nextKey = 0
function newSentence(source = {}) { return { key: ++nextKey, promptCn: source.promptCn || source.prompt_cn || '', answerText: source.answerText || source.answer_text || '' } }
const form = useForm({
  title: props.set?.title || '', subtitle: props.set?.subtitle || '',
  iconAsset: props.set?.icon_asset || '', sourceReadingItemId: props.set?.source_reading_item_id || null,
  sortOrder: props.set?.sort_order ?? 0,
  sentences: props.sentences?.length ? props.sentences.map(newSentence) : [newSentence()]
})
const readingOptions = ref([...(props.readings || [])])
const readingLoading = ref(false)
let previousSourceId = form.sourceReadingItemId
async function searchReadings(query) {
  readingLoading.value = true
  try {
    const response = await fetch(`/admin/puzzles/reading-options?q=${encodeURIComponent(query)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('搜索失败')
    const data = await response.json()
    const merged = new Map(readingOptions.value.map(item => [item.id, item]))
    for (const item of data.items || []) merged.set(item.id, item)
    readingOptions.value = [...merged.values()]
  } catch (_) { /* 保留已选文章 */ }
  finally { readingLoading.value = false }
}
async function sourceChanged(readingId) {
  if (!readingId) { previousSourceId = null; return }
  const hasContent = form.sentences.some(sentence => sentence.promptCn.trim() || sentence.answerText.trim())
  if (hasContent) {
    try {
      await ElMessageBox.confirm('从文章来源重新填充会替换当前全部句子，是否继续？', '替换现有句子', { type: 'warning', confirmButtonText: '替换句子', cancelButtonText: '保留现有内容' })
    } catch (_) {
      form.sourceReadingItemId = previousSourceId
      return
    }
  }

  readingLoading.value = true
  try {
    const response = await fetch(`/admin/puzzles/readings/${readingId}/sentences`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || '读取文章来源失败')
    if (!data.sentences?.length) throw new Error('这篇文章没有可导入的英文句子')
    if (data.sentences.length > 200) throw new Error('文章来源超过 200 句，暂时无法一次导入')
    form.sentences = data.sentences.map(newSentence)
    expanded.value = form.sentences.map(sentence => sentence.key)
    previousSourceId = readingId
    const missingTranslations = data.sentences.filter(sentence => !sentence.promptCn).length
    ElMessage.success(`已导入 ${data.sentences.length} 句${missingTranslations ? `；其中 ${missingTranslations} 句缺少逐句译文，请补充中文提示` : ''}`)
  } catch (error) {
    form.sourceReadingItemId = previousSourceId
    ElMessage.error(error.message || '导入文章来源失败')
  } finally {
    readingLoading.value = false
  }
}
const expanded = ref(form.sentences.map(sentence => sentence.key))
function addSentence() { const sentence = newSentence(); form.sentences.push(sentence); expanded.value.push(sentence.key) }
function removeSentence(index) { if (form.sentences.length > 1) form.sentences.splice(index, 1) }
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/puzzles/${props.set.id}` : '/admin/puzzles') }
function toggle() { router.post(`/admin/puzzles/${props.set.id}/toggle`) }
</script>

<template>
  <AdminLayout :title="editing ? '编辑拼句' : '新建拼句'" section="内容管理 / 拼句内容" description="每句填写中文提示和标准英文，词块由英文空格自动拆分。">
    <template #actions><Link href="/admin/puzzles"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel"><div class="form-stack">
      <section class="card"><div class="card-header"><div><h2>拼句集合</h2><p>不选来源文章就是独立练习；选择精读文章后会自动导入句子，可继续编辑。</p></div></div><div class="card-body"><div class="form-grid">
        <div class="form-field wide"><label>标题 <b class="required-mark">*</b></label><el-input v-model="form.title" /></div>
        <div class="form-field wide"><label>副标题</label><el-input v-model="form.subtitle" /></div>
        <div class="form-field"><label>图标字符</label><el-input v-model="form.iconAsset" maxlength="8" placeholder="例如：✦" /></div>
        <div class="form-field"><label>列表顺序</label><el-input v-model.number="form.sortOrder" type="number" min="0" /></div>
        <div class="form-field wide"><label>来源文章（选填）</label><el-select v-model="form.sourceReadingItemId" clearable filterable remote :remote-method="searchReadings" :loading="readingLoading" placeholder="搜索文章标题；不选则为独立练习" @change="sourceChanged"><el-option v-for="reading in readingOptions" :key="reading.id" :label="`${reading.translation || reading.title} · #${reading.id}`" :value="reading.id" /></el-select></div>
      </div></div></section>
      <section class="card"><div class="card-header"><div><h2>句子</h2><p>标点跟在单词后面，例如 “She opened the door.”。每句至少两个词块。</p></div><el-button @click="addSentence">＋ 添加句子</el-button></div><div class="card-body"><el-collapse v-model="expanded" class="editor-collapse"><el-collapse-item v-for="(sentence, index) in form.sentences" :key="sentence.key" :name="sentence.key"><template #title><strong>第 {{ index + 1 }} 句 · {{ sentence.answerText || '未填写英文' }}</strong></template><div class="form-grid"><div class="form-field wide"><label>中文提示 <b class="required-mark">*</b></label><el-input v-model="sentence.promptCn" type="textarea" :rows="2" /></div><div class="form-field wide"><label>标准英文 <b class="required-mark">*</b></label><el-input v-model="sentence.answerText" type="textarea" :rows="2" placeholder="She opened the door." /></div></div><div class="section-actions"><el-button v-if="form.sentences.length > 1" link type="danger" @click="removeSentence(index)">移除本句</el-button></div></el-collapse-item></el-collapse></div></section>
    </div><aside class="form-stack editor-actions-panel"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body"><template v-if="editing"><p><el-tag :type="set.status === 'published' ? 'success' : 'info'">{{ set.status === 'published' ? '已发布' : '草稿' }}</el-tag></p><p class="helper">用户开始练习后，不能再增减句子，以免旧进度错位。</p></template><p v-else class="helper">创建后默认为草稿。</p><el-button type="primary" :loading="form.processing" @click="save">保存拼句内容</el-button><el-button v-if="editing" :type="set.status === 'published' ? 'danger' : 'default'" :plain="set.status === 'published'" @click="toggle">{{ set.status === 'published' ? '下架拼句' : '发布拼句' }}</el-button></div></section></aside></div>
  </AdminLayout>
</template>
