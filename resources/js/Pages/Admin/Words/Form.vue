<script setup>
import { ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ word: Object })
const editing = Boolean(props.word)
const detail = props.word?.detail || {}
const editor = ref(null)
let nextSenseKey = 0
function makeSense(sense = {}) {
  return { key: ++nextSenseKey, partOfSpeech: sense.part_of_speech || '', meaning: sense.meaning || '' }
}
const form = useForm({
  headword: props.word?.headword || '', ipa: props.word?.ipa || '',
  senses: props.word?.senses?.length ? props.word.senses.map(makeSense) : [makeSense({part_of_speech: props.word?.part_of_speech, meaning: props.word?.meaning})],
  frequencyLabel: props.word?.frequency_label || '', audioAsset: props.word?.audio_asset || '',
  slug: props.word?.slug || '', detailType: detail.type || 'richtext',
  detailFormat: detail.format || 'html', detailHtml: detail.html || '',
  detailUrl: detail.url || '', detailTitle: detail.title || '', detailSummary: detail.summary || ''
})
function addSense() { form.senses.push(makeSense()) }
function removeSense(index) { if (form.senses.length > 1) form.senses.splice(index, 1) }
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/words/${props.word.id}` : '/admin/words') }
function publish() { router.post(`/admin/words/${props.word.id}/${props.word.status === 'published' ? 'unpublish' : 'publish'}`) }
function command(name, value) {
  editor.value?.focus()
  document.execCommand(name, false, value || null)
  form.detailHtml = editor.value?.innerHTML || ''
}
</script>

<template>
  <AdminLayout :title="editing ? '编辑单词' : '新建单词'" section="内容管理 / 单词管理" :description="editing ? `单词 ID #${word.id} · 编辑资料和详解` : '添加新单词与对应详解。'">
    <template #actions><Link href="/admin/words"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel">
      <div class="form-stack"><section class="card">
        <div class="card-header"><div><h2>单词资料</h2><p>基本释义、发音和详解</p></div></div>
        <div class="card-body"><div class="form-stack">
          <div class="form-grid">
            <div class="form-field"><label>单词 <b class="required-mark">*</b></label><el-input v-model="form.headword" /><small v-if="form.errors.headword" class="form-error">{{ form.errors.headword }}</small></div>
            <div class="form-field"><label>音标</label><el-input v-model="form.ipa" /></div>
            <div class="form-field"><label>频率标签</label><el-input v-model="form.frequencyLabel" /></div>
            <div class="form-field"><label>发音资源路径</label><el-input v-model="form.audioAsset" /></div>
            <div class="form-field"><label>内容标识 slug</label><el-input v-model="form.slug" /></div>
            <div class="form-field"><label>详解形式</label><el-select v-model="form.detailType"><el-option value="richtext" label="富文本详解" /><el-option value="officialAccount" label="公众号文章链接" /></el-select></div>
          </div>
          <section class="sense-editor">
            <div class="sense-editor-head"><div><strong>词性与简明释义</strong><p>每条含义单独填写；同一词性可以重复添加多条释义，例如 accident 的两个名词义项。</p></div><el-button @click="addSense">＋ 添加释义</el-button></div>
            <div v-for="(sense, index) in form.senses" :key="sense.key" class="sense-row">
              <span class="sense-order">{{ index + 1 }}</span>
              <div class="form-field"><label>词性</label><el-input v-model="sense.partOfSpeech" placeholder="例如 n. / v. / adj." /></div>
              <div class="form-field"><label>第 {{ index + 1 }} 条释义 <b class="required-mark">*</b></label><el-input v-model="sense.meaning" placeholder="只填写一条含义；相近义项也可分开录入" /></div>
              <el-button v-if="form.senses.length > 1" link type="danger" @click="removeSense(index)">删除</el-button>
            </div>
            <small v-if="form.errors.senses" class="form-error">{{ form.errors.senses }}</small>
            <small v-if="form.errors['senses.0.meaning']" class="form-error">{{ form.errors['senses.0.meaning'] }}</small>
          </section>
          <div v-if="form.detailType === 'richtext'" class="form-field">
            <label>单词详解正文 <b class="required-mark">*</b></label>
            <el-radio-group v-model="form.detailFormat"><el-radio-button value="html">富文本</el-radio-button><el-radio-button value="markdown">Markdown</el-radio-button></el-radio-group>
            <template v-if="form.detailFormat === 'html'">
              <div class="toolbar"><el-button size="small" @click="command('bold')"><b>粗体</b></el-button><el-button size="small" @click="command('italic')"><i>斜体</i></el-button><el-button size="small" @click="command('insertUnorderedList')">列表</el-button><el-button size="small" @click="command('formatBlock', 'h3')">小标题</el-button></div>
              <div ref="editor" :key="'html'" class="rich-editor" contenteditable="true" data-placeholder="填写词义辨析、用法和例句…" @input="form.detailHtml = $event.target.innerHTML" v-html="form.detailHtml"></div>
            </template>
            <el-input v-else v-model="form.detailHtml" type="textarea" :rows="18" placeholder="可以直接粘贴 Markdown，例如 ## 用法、- 要点、**重点**" />
            <small v-if="form.errors.detailHtml" class="form-error">{{ form.errors.detailHtml }}</small>
          </div>
          <div v-else class="form-grid">
            <div class="form-field wide"><label>公众号文章链接 <b class="required-mark">*</b></label><el-input v-model="form.detailUrl" type="url" /><small v-if="form.errors.detailUrl" class="form-error">{{ form.errors.detailUrl }}</small></div>
            <div class="form-field"><label>文章标题</label><el-input v-model="form.detailTitle" /></div>
            <div class="form-field"><label>摘要</label><el-input v-model="form.detailSummary" /></div>
          </div>
        </div></div>
      </section></div>
      <aside class="form-stack editor-actions-panel"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body">
        <p v-if="editing"><el-tag :type="word.status === 'published' ? 'success' : 'info'">{{ word.status === 'published' ? '已发布' : '草稿' }}</el-tag></p>
        <p v-else class="helper">保存后默认为草稿。</p>
        <el-button type="primary" :loading="form.processing" @click="save">保存单词与详解</el-button>
        <el-button v-if="editing" :type="word.status === 'published' ? 'danger' : 'default'" :plain="word.status === 'published'" @click="publish">{{ word.status === 'published' ? '下架单词' : '发布单词' }}</el-button>
      </div></section></aside>
    </div>
  </AdminLayout>
</template>

<style scoped>
.sense-editor{display:grid;gap:12px;padding:16px;border:1px solid #e4e9f1;border-radius:9px;background:#fbfcfe}.sense-editor-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.sense-editor-head strong{color:#263449}.sense-editor-head p{margin:5px 0 0;color:#8590a0;font-size:12px}.sense-row{display:grid;grid-template-columns:28px minmax(130px,.7fr) minmax(220px,1.6fr) auto;gap:12px;align-items:end;padding:12px;background:#fff;border:1px solid #e8ecf2;border-radius:7px}.sense-order{align-self:center;color:#8a96a7;font-weight:700;text-align:center}@media(max-width:800px){.sense-row{grid-template-columns:24px 1fr}.sense-row .form-field:nth-child(3){grid-column:2}.sense-row :deep(.el-button){grid-column:2;justify-self:start}}
</style>
