<script setup>
import { ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ word: Object })
const editing = Boolean(props.word)
const detail = props.word?.detail || {}
const editor = ref(null)
const form = useForm({
  headword: props.word?.headword || '', ipa: props.word?.ipa || '',
  partOfSpeech: props.word?.part_of_speech || '', meaning: props.word?.meaning || '',
  frequencyLabel: props.word?.frequency_label || '', audioAsset: props.word?.audio_asset || '',
  slug: props.word?.slug || '', detailType: detail.type || 'richtext',
  detailFormat: detail.format || 'html', detailHtml: detail.html || '',
  detailUrl: detail.url || '', detailTitle: detail.title || '', detailSummary: detail.summary || ''
})
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
            <div class="form-field"><label>词性</label><el-input v-model="form.partOfSpeech" placeholder="n. / v. / adj." /></div>
            <div class="form-field"><label>简明释义 <b class="required-mark">*</b></label><el-input v-model="form.meaning" /><small v-if="form.errors.meaning" class="form-error">{{ form.errors.meaning }}</small></div>
            <div class="form-field"><label>频率标签</label><el-input v-model="form.frequencyLabel" /></div>
            <div class="form-field"><label>发音资源路径</label><el-input v-model="form.audioAsset" /></div>
            <div class="form-field"><label>内容标识 slug</label><el-input v-model="form.slug" /></div>
            <div class="form-field"><label>详解形式</label><el-select v-model="form.detailType"><el-option value="richtext" label="富文本详解" /><el-option value="officialAccount" label="公众号文章链接" /></el-select></div>
          </div>
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
