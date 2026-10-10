<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ lesson: Object, content: Object, questions: Array, levels: Array, coverUrl: String })
const editing = Boolean(props.lesson)
const coverPreview = ref(props.coverUrl || '')
let nextKey = 0
function newQuestion(source = {}) {
  return {
    key: ++nextKey, kind: source.kind || 'choice', prompt: source.prompt || '',
    options: source.options?.length ? [...source.options] : ['', '', '', ''],
    answer: source.answer ?? 0, explanation: source.explanation || ''
  }
}
const form = useForm({
  title: props.lesson?.title || '', summary: props.lesson?.summary || '',
  level: props.lesson?.level || '', sortOrder: props.lesson?.sort_order ?? 0, coverImage: null,
  contentType: props.content?.type || 'richtext', contentFormat: props.content?.format || 'html', contentHtml: props.content?.html || '',
  contentUrl: props.content?.url || '', contentTitle: props.content?.title || '',
  contentSummary: props.content?.summary || '',
  questions: (props.questions || []).map(newQuestion)
})
const expanded = ref(form.questions.map(question => question.key))
function addQuestion() { const question = newQuestion(); form.questions.push(question); expanded.value.push(question.key) }
function removeQuestion(index) { const key = form.questions[index]?.key; form.questions.splice(index, 1); expanded.value = expanded.value.filter(value => value !== key) }
function setKind(question, kind) { question.kind = kind; question.answer = kind === 'choice' ? 0 : '' }
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/grammar/${props.lesson.id}` : '/admin/grammar', { forceFormData: true }) }
function coverChanged(upload) { const file = upload.raw; if (!file) return; coverPreview.value = URL.createObjectURL(file); form.coverImage = file }
function toggle() { router.post(`/admin/grammar/${props.lesson.id}/toggle`) }
function command(name, value) { document.execCommand(name, false, value || null) }
</script>

<template>
  <AdminLayout :title="editing ? '编辑语法课程' : '新建语法课程'" section="内容管理 / 语法课程" description="随堂题目可选填；新课程默认先存为草稿。">
    <template #actions><Link href="/admin/grammar"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel"><div class="form-stack">
      <section class="card"><div class="card-header"><div><h2>课程资料</h2><p>标题、级别与讲解内容</p></div></div><div class="card-body">
        <div class="form-grid">
          <div class="form-field wide"><label>课程标题 <b class="required-mark">*</b></label><el-input v-model="form.title" maxlength="255" /></div>
          <div class="form-field wide"><label>简要介绍</label><el-input v-model="form.summary" type="textarea" :rows="2" /></div>
          <div class="form-field wide"><label>课程封面（选填）</label><div class="cover-upload"><div class="cover-preview square"><img v-if="coverPreview" :src="coverPreview" alt="课程封面预览"><span v-else>标题字封面</span></div><div class="cover-upload-controls"><el-upload :auto-upload="false" :show-file-list="false" accept="image/jpeg,image/png,image/webp" :on-change="coverChanged"><el-button>选择封面</el-button></el-upload><p class="form-hint">不上传时，最近学习中会用标题首字生成封面。最大 4 MB。</p></div></div><small v-if="form.errors.coverImage" class="form-error">{{ form.errors.coverImage }}</small></div>
          <div class="form-field"><label>级别 <b class="required-mark">*</b></label><el-select v-model="form.level" placeholder="请选择语法级别"><el-option v-for="level in levels" :key="level.name" :label="level.name" :value="level.name" :disabled="!level.is_active && level.name !== form.level" /></el-select><small v-if="form.errors.level" class="form-error">{{ form.errors.level }}</small></div>
          <div class="form-field"><label>列表顺序</label><el-input v-model.number="form.sortOrder" type="number" min="0" /></div>
          <div class="form-field"><label>讲解形式</label><el-select v-model="form.contentType"><el-option value="richtext" label="富文本" /><el-option value="officialAccount" label="公众号文章" /></el-select></div>
          <div class="form-field"><label>讲解标题（选填）</label><el-input v-model="form.contentTitle" /></div>
          <div v-if="form.contentType === 'officialAccount'" class="form-field wide"><label>公众号文章链接 <b class="required-mark">*</b></label><el-input v-model="form.contentUrl" placeholder="https://..." /></div>
          <div v-if="form.contentType === 'officialAccount'" class="form-field wide"><label>文章摘要</label><el-input v-model="form.contentSummary" type="textarea" :rows="2" /></div>
        </div>
        <div v-if="form.contentType === 'richtext'" class="form-field"><label>讲解正文 <b class="required-mark">*</b></label><el-radio-group v-model="form.contentFormat"><el-radio-button value="html">富文本</el-radio-button><el-radio-button value="markdown">Markdown</el-radio-button></el-radio-group><template v-if="form.contentFormat === 'html'"><div class="toolbar"><el-button size="small" @click="command('bold')"><b>粗体</b></el-button><el-button size="small" @click="command('italic')"><i>斜体</i></el-button><el-button size="small" @click="command('insertUnorderedList')">列表</el-button><el-button size="small" @click="command('formatBlock','h3')">小标题</el-button></div><div class="rich-editor" contenteditable="true" data-placeholder="填写规则、用法和例句…" v-html="form.contentHtml" @input="form.contentHtml = $event.target.innerHTML"></div></template><el-input v-else v-model="form.contentHtml" type="textarea" :rows="18" placeholder="可以直接粘贴 Markdown 内容" /><small v-if="form.errors.contentHtml" class="form-error">{{ form.errors.contentHtml }}</small></div>
      </div></section>
      <section class="card"><div class="card-header"><div><h2>随堂题目</h2><p>选择题的正确答案按选项顺序设置。</p></div><el-button @click="addQuestion">＋ 添加题目</el-button></div><div class="card-body">
        <p v-if="!form.questions.length" class="helper">暂无题目，可以直接保存课程。</p>
        <el-collapse v-model="expanded" class="editor-collapse"><el-collapse-item v-for="(question, index) in form.questions" :key="question.key" :name="question.key"><template #title><strong>第 {{ index + 1 }} 题 · {{ question.prompt || '未填写题目' }}</strong></template>
          <div class="form-grid">
            <div class="form-field"><label>题型</label><el-select :model-value="question.kind" @update:model-value="setKind(question, $event)"><el-option label="选择题" value="choice" /><el-option label="填空题" value="fill" /></el-select></div>
            <div class="form-field wide"><label>题目 <b class="required-mark">*</b></label><el-input v-model="question.prompt" type="textarea" :rows="2" /></div>
            <template v-if="question.kind === 'choice'"><div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="form-field"><label>选项 {{ String.fromCharCode(65 + optionIndex) }}</label><el-input v-model="question.options[optionIndex]" /></div><div class="form-field"><label>正确选项 <b class="required-mark">*</b></label><el-select v-model="question.answer"><el-option v-for="(_, optionIndex) in question.options" :key="optionIndex" :label="String.fromCharCode(65 + optionIndex)" :value="optionIndex" /></el-select></div></template>
            <div v-else class="form-field wide"><label>参考答案 <b class="required-mark">*</b></label><el-input v-model="question.answer" /></div>
            <div class="form-field wide"><label>答案解析</label><el-input v-model="question.explanation" type="textarea" :rows="2" /></div>
          </div><div class="section-actions"><el-button link type="danger" @click="removeQuestion(index)">移除本题</el-button></div>
        </el-collapse-item></el-collapse>
      </div></section>
    </div><aside class="form-stack editor-actions-panel"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body"><template v-if="editing"><p><el-tag :type="lesson.status === 'published' ? 'success' : 'info'">{{ lesson.status === 'published' ? '已发布' : '草稿' }}</el-tag></p><p class="helper">保存后再发布。已发布的内容修改保存后会同步更新前台。</p></template><p v-else class="helper">创建后默认为草稿。</p><el-button type="primary" :loading="form.processing" @click="save">保存课程与题目</el-button><el-button v-if="editing" :type="lesson.status === 'published' ? 'danger' : 'default'" :plain="lesson.status === 'published'" @click="toggle">{{ lesson.status === 'published' ? '下架课程' : '发布课程' }}</el-button></div></section></aside></div>
  </AdminLayout>
</template>
