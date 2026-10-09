<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ lesson: Object, content: Object, questions: Array })
const editing = Boolean(props.lesson)
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
  level: props.lesson?.level || '', sortOrder: props.lesson?.sort_order ?? 0,
  contentType: props.content?.type || 'richtext', contentHtml: props.content?.html || '',
  contentUrl: props.content?.url || '', contentTitle: props.content?.title || '',
  contentSummary: props.content?.summary || '',
  questions: props.questions?.length ? props.questions.map(newQuestion) : [newQuestion()]
})
const expanded = ref(form.questions.map(question => question.key))
function addQuestion() { const question = newQuestion(); form.questions.push(question); expanded.value.push(question.key) }
function removeQuestion(index) { if (form.questions.length > 1) form.questions.splice(index, 1) }
function setKind(question, kind) { question.kind = kind; question.answer = kind === 'choice' ? 0 : '' }
function save() { form.submit(editing ? 'put' : 'post', editing ? `/admin/grammar/${props.lesson.id}` : '/admin/grammar') }
function toggle() { router.post(`/admin/grammar/${props.lesson.id}/toggle`) }
function command(name, value) { document.execCommand(name, false, value || null) }
</script>

<template>
  <AdminLayout :title="editing ? '编辑语法课程' : '新建语法课程'" section="内容管理 / 语法课程" description="课程讲解和随堂练习一次保存；默认先存为草稿。">
    <template #actions><Link href="/admin/grammar"><el-button>← 返回列表</el-button></Link></template>
    <div class="split-panel"><div class="form-stack">
      <section class="card"><div class="card-header"><div><h2>课程资料</h2><p>标题、级别与讲解内容</p></div></div><div class="card-body">
        <div class="form-grid">
          <div class="form-field wide"><label>课程标题 <b class="required-mark">*</b></label><el-input v-model="form.title" maxlength="255" /></div>
          <div class="form-field wide"><label>简要介绍</label><el-input v-model="form.summary" type="textarea" :rows="2" /></div>
          <div class="form-field"><label>级别</label><el-input v-model="form.level" placeholder="例如：基础语法" /></div>
          <div class="form-field"><label>列表顺序</label><el-input v-model.number="form.sortOrder" type="number" min="0" /></div>
          <div class="form-field"><label>讲解形式</label><el-select v-model="form.contentType"><el-option value="richtext" label="富文本" /><el-option value="officialAccount" label="公众号文章" /></el-select></div>
          <div class="form-field"><label>讲解标题（选填）</label><el-input v-model="form.contentTitle" /></div>
          <div v-if="form.contentType === 'officialAccount'" class="form-field wide"><label>公众号文章链接 <b class="required-mark">*</b></label><el-input v-model="form.contentUrl" placeholder="https://..." /></div>
          <div v-if="form.contentType === 'officialAccount'" class="form-field wide"><label>文章摘要</label><el-input v-model="form.contentSummary" type="textarea" :rows="2" /></div>
        </div>
        <div v-if="form.contentType === 'richtext'" class="form-field"><label>讲解正文 <b class="required-mark">*</b></label><div class="toolbar"><el-button size="small" @click="command('bold')"><b>粗体</b></el-button><el-button size="small" @click="command('italic')"><i>斜体</i></el-button><el-button size="small" @click="command('insertUnorderedList')">列表</el-button><el-button size="small" @click="command('formatBlock','h3')">小标题</el-button></div><div class="rich-editor" contenteditable="true" data-placeholder="填写规则、用法和例句…" v-html="form.contentHtml" @input="form.contentHtml = $event.target.innerHTML"></div></div>
      </div></section>
      <section class="card"><div class="card-header"><div><h2>随堂题目</h2><p>选择题的正确答案按选项顺序设置。</p></div><el-button @click="addQuestion">＋ 添加题目</el-button></div><div class="card-body">
        <el-collapse v-model="expanded" class="editor-collapse"><el-collapse-item v-for="(question, index) in form.questions" :key="question.key" :name="question.key"><template #title><strong>第 {{ index + 1 }} 题 · {{ question.prompt || '未填写题目' }}</strong></template>
          <div class="form-grid">
            <div class="form-field"><label>题型</label><el-select :model-value="question.kind" @update:model-value="setKind(question, $event)"><el-option label="选择题" value="choice" /><el-option label="填空题" value="fill" /></el-select></div>
            <div class="form-field wide"><label>题目 <b class="required-mark">*</b></label><el-input v-model="question.prompt" type="textarea" :rows="2" /></div>
            <template v-if="question.kind === 'choice'"><div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="form-field"><label>选项 {{ String.fromCharCode(65 + optionIndex) }}</label><el-input v-model="question.options[optionIndex]" /></div><div class="form-field"><label>正确选项 <b class="required-mark">*</b></label><el-select v-model="question.answer"><el-option v-for="(_, optionIndex) in question.options" :key="optionIndex" :label="String.fromCharCode(65 + optionIndex)" :value="optionIndex" /></el-select></div></template>
            <div v-else class="form-field wide"><label>参考答案 <b class="required-mark">*</b></label><el-input v-model="question.answer" /></div>
            <div class="form-field wide"><label>答案解析</label><el-input v-model="question.explanation" type="textarea" :rows="2" /></div>
          </div><div class="section-actions"><el-button v-if="form.questions.length > 1" link type="danger" @click="removeQuestion(index)">移除本题</el-button></div>
        </el-collapse-item></el-collapse>
      </div></section>
      <div class="section-actions"><el-button type="primary" :loading="form.processing" @click="save">保存课程与题目</el-button></div>
    </div><aside class="form-stack"><section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body"><template v-if="editing"><p><el-tag :type="lesson.status === 'published' ? 'success' : 'info'">{{ lesson.status === 'published' ? '已发布' : '草稿' }}</el-tag></p><p class="helper">保存后再发布。已发布的内容修改保存后会同步更新前台。</p><el-button :type="lesson.status === 'published' ? 'danger' : 'primary'" :plain="lesson.status === 'published'" @click="toggle">{{ lesson.status === 'published' ? '下架课程' : '发布课程' }}</el-button></template><p v-else class="helper">创建后默认为草稿。</p></div></section></aside></div>
  </AdminLayout>
</template>
