<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'
import { formatBeijingDateTime as date } from '../../../utils/date-time.js'

const props = defineProps({ feedback: Object, filters: Object, counts: Object })
const current = ref(null)
const form = useForm({ status: 'pending', admin_reply: '', reply_kind: 'update', admin_note: '' })
const categoryNames = { content: '内容问题', suggestion: '功能建议', other: '其他' }
const targetNames = { reading: '精读', word: '单词', grammar: '语法', puzzle: '拼句', general: '通用反馈' }
const statusNames = { pending: '待处理', reviewing: '处理中', resolved: '已处理' }
const statusTypes = { pending: 'warning', reviewing: 'primary', resolved: 'success' }
function filter(status) { router.get('/admin/feedback', status ? { status } : {}, { preserveState: true, preserveScroll: true }) }
function open(row) { current.value = row; form.status = row.status; form.admin_reply = ''; form.reply_kind = 'update'; form.admin_note = row.admin_note || '' }
function save() { form.put(`/admin/feedback/${current.value.id}`, { onSuccess: () => { current.value = null } }) }
</script>

<template>
  <AdminLayout title="用户反馈" section="用户服务 / 用户反馈" description="查看并处理用户提交的内容问题与功能建议。">
    <div class="feedback-stats">
      <button :class="{ active: !filters.status }" @click="filter('')"><strong>{{ Number(counts?.pending || 0) + Number(counts?.reviewing || 0) + Number(counts?.resolved || 0) }}</strong><span>全部反馈</span></button>
      <button :class="{ active: filters.status === 'pending' }" @click="filter('pending')"><strong>{{ counts?.pending || 0 }}</strong><span>待处理</span></button>
      <button :class="{ active: filters.status === 'reviewing' }" @click="filter('reviewing')"><strong>{{ counts?.reviewing || 0 }}</strong><span>处理中</span></button>
      <button :class="{ active: filters.status === 'resolved' }" @click="filter('resolved')"><strong>{{ counts?.resolved || 0 }}</strong><span>已处理</span></button>
    </div>
    <section class="card">
      <div class="card-header"><div><h2>反馈列表</h2><p>共 {{ feedback.total }} 条</p></div></div>
      <div class="card-body">
        <el-table :data="feedback.data" stripe>
          <el-table-column label="反馈内容" min-width="300"><template #default="scope"><button class="feedback-message" @click="open(scope.row)">{{ scope.row.message }}</button><small>{{ categoryNames[scope.row.category] || scope.row.category }}<template v-if="scope.row.replies?.length"> · {{ scope.row.replies.length }} 条回复</template></small></template></el-table-column>
          <el-table-column label="关联内容" min-width="190"><template #default="scope"><span>{{ targetNames[scope.row.target_type] || scope.row.target_type }}</span><small v-if="scope.row.target_title">{{ scope.row.target_title }}</small></template></el-table-column>
          <el-table-column label="提交者" width="150"><template #default="scope">{{ scope.row.user_name || '未登录用户' }}</template></el-table-column>
          <el-table-column label="提交时间" width="180"><template #default="scope">{{ date(scope.row.created_at) }}</template></el-table-column>
          <el-table-column label="状态" width="110"><template #default="scope"><el-tag :type="statusTypes[scope.row.status]">{{ statusNames[scope.row.status] }}</el-tag></template></el-table-column>
          <el-table-column label="操作" width="100"><template #default="scope"><el-button link type="primary" @click="open(scope.row)">查看处理</el-button></template></el-table-column>
          <template #empty><el-empty description="暂时还没有用户反馈" /></template>
        </el-table>
        <div class="feedback-pagination"><el-pagination v-if="feedback.last_page > 1" background layout="prev, pager, next" :current-page="feedback.current_page" :page-count="feedback.last_page" @current-change="page => router.get('/admin/feedback', { ...filters, page }, { preserveState: true })" /></div>
      </div>
    </section>
    <el-dialog v-model="current" title="反馈详情" width="560px" destroy-on-close>
      <template v-if="current">
        <div class="feedback-context"><el-tag>{{ categoryNames[current.category] }}</el-tag><span>{{ targetNames[current.target_type] }}<template v-if="current.target_title"> · {{ current.target_title }}</template></span></div>
        <div class="feedback-full-message">{{ current.message }}</div>
        <p class="feedback-meta">{{ current.user_name || '未登录用户' }} · {{ date(current.created_at) }}</p>
        <div v-if="current.replies?.length" class="reply-thread"><strong>已发送给用户</strong><div v-for="reply in current.replies" :key="reply.id" class="reply-entry"><el-tag size="small" :type="reply.kind === 'resolution' ? 'success' : 'info'">{{ reply.kind === 'resolution' ? '处理结果' : '进度回复' }}</el-tag><span>{{ reply.message }}</span><small>{{ date(reply.created_at) }}</small></div></div>
        <el-form label-position="top"><el-form-item label="处理状态"><el-select v-model="form.status"><el-option label="待处理" value="pending" /><el-option label="处理中" value="reviewing" /><el-option label="已处理" value="resolved" /></el-select></el-form-item><el-form-item label="给用户的回复"><el-select v-model="form.reply_kind" class="reply-type-select"><el-option label="进度回复" value="update" /><el-option label="处理结果" value="resolution" /></el-select><el-input v-model="form.admin_reply" type="textarea" :rows="3" maxlength="3000" show-word-limit placeholder="这次要发送给用户的新消息" /><small class="reply-hint">每次填写的新回复都会单独保存，并提醒用户；进度回复会将状态更新为“处理中”，处理结果会更新为“已处理”。</small></el-form-item><el-form-item label="内部备注"><el-input v-model="form.admin_note" type="textarea" :rows="2" maxlength="3000" show-word-limit placeholder="仅后台可见" /></el-form-item></el-form>
      </template>
      <template #footer><el-button @click="current = null">关闭</el-button><el-button type="primary" :loading="form.processing" @click="save">保存处理</el-button></template>
    </el-dialog>
  </AdminLayout>
</template>

<style scoped>
.feedback-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.feedback-stats button{display:flex;flex-direction:column;gap:6px;align-items:flex-start;padding:18px 20px;border:1px solid #e7ebf1;border-radius:12px;background:#fff;color:#738096;text-align:left;cursor:pointer}.feedback-stats button.active{border-color:#9db9f4;background:#f5f8ff}.feedback-stats strong{font-size:24px;color:#26364f}.feedback-stats span{font-size:13px}.feedback-message{display:-webkit-box;max-width:100%;overflow:hidden;text-align:left;text-overflow:ellipsis;white-space:normal;line-height:1.5;border:0;background:none;color:#34445e;cursor:pointer;-webkit-box-orient:vertical;-webkit-line-clamp:2}.feedback-context{display:flex;align-items:center;gap:12px;color:#718097}.feedback-full-message{margin:18px 0;padding:16px;border-radius:10px;background:#f6f8fb;color:#34445e;line-height:1.8;white-space:pre-wrap;word-break:break-word}.feedback-meta{margin:0 0 22px;color:#8995a7;font-size:13px}.feedback-pagination{display:flex;justify-content:flex-end;margin-top:18px}.card-body small{display:block;margin-top:5px;color:#8a95a7;font-size:12px}.reply-hint{display:block;margin-top:8px;color:#8995a7;font-size:12px}.reply-type-select{width:100%;margin-bottom:10px}.reply-thread{display:flex;flex-direction:column;gap:10px;margin:0 0 22px;padding:16px;border-radius:10px;background:#f6f8fb}.reply-entry{display:grid;grid-template-columns:auto 1fr;gap:8px 12px;align-items:start;padding-top:10px;border-top:1px solid #e7ebf1}.reply-entry span{white-space:pre-wrap;word-break:break-word;color:#34445e;line-height:1.6}.reply-entry small{grid-column:2}@media(max-width:700px){.feedback-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
