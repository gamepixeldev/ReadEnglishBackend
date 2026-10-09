<script setup>
import { Link, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ words: Object, search: String, status: String, availableDailyPlans: Array })
const filters = reactive({ q: props.search || '', status: props.status || '' })
const planDialog = ref(false)
const selectedWord = ref(null)
const form = useForm({ planDate: '' })
const targetMode = ref('new')
const selectedPlanDate = ref('')
const alreadyScheduled = computed(() => selectedWord.value?.daily_plans?.some(plan => plan.date === form.planDate))

function filter() { router.get('/admin/words', filters, { preserveState: true, replace: true }) }
function localDate(date = new Date()) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
function openPlanDialog(word) {
  selectedWord.value = word
  form.clearErrors()
  const todayPlan = props.availableDailyPlans?.find(plan => plan.plan_date === localDate())
  targetMode.value = props.availableDailyPlans?.length ? 'existing' : 'new'
  selectedPlanDate.value = todayPlan?.plan_date || props.availableDailyPlans?.[0]?.plan_date || ''
  form.planDate = targetMode.value === 'existing' ? selectedPlanDate.value : localDate()
  planDialog.value = true
}
function changeTargetMode(value) {
  targetMode.value = value
  if (value === 'existing') form.planDate = selectedPlanDate.value
  else form.planDate = localDate()
}
function changeExistingPlan(date) {
  selectedPlanDate.value = date
  form.planDate = date
}
function addToPlan() {
  if (!selectedWord.value || alreadyScheduled.value) return
  form.post(`/admin/words/${selectedWord.value.id}/daily-plans`, {
    preserveScroll: true,
    onSuccess: () => { planDialog.value = false; ElMessage.success('已加入每日计划') },
  })
}
function displayDate(date) { return String(date).slice(5).replace('-', '/') }
</script>

<template>
  <AdminLayout title="单词管理" section="内容管理 / 单词管理" description="管理单词资料、详解和每日学习安排。">
    <template #actions><Link href="/admin/words/create"><el-button type="primary">＋ 新建单词</el-button></Link></template>
    <section class="card">
      <form class="filter-bar" @submit.prevent="filter">
        <el-input v-model="filters.q" class="search" clearable placeholder="搜索单词或释义" @keyup.enter="filter" />
        <el-select v-model="filters.status"><el-option label="全部状态" value="" /><el-option label="草稿" value="draft" /><el-option label="已发布" value="published" /></el-select>
        <el-button type="primary" native-type="submit">筛选</el-button>
        <el-button v-if="filters.q || filters.status" text type="primary" @click="filters.q = ''; filters.status = ''; filter()">清空</el-button>
      </form>

      <div v-if="!words.data.length" class="empty-state"><strong>{{ filters.q || filters.status ? '没有匹配的单词' : '还没有单词' }}</strong></div>
      <div v-else class="table-wrap">
        <el-table :data="words.data" stripe>
          <el-table-column label="ID" width="80"><template #default="scope"><span class="badge-id">#{{ scope.row.id }}</span></template></el-table-column>
          <el-table-column label="单词" min-width="150"><template #default="scope"><div class="table-primary">{{ scope.row.headword }}</div><div class="table-secondary">{{ scope.row.ipa }}</div></template></el-table-column>
          <el-table-column label="词性" width="110"><template #default="scope">{{ scope.row.part_of_speech || '—' }}</template></el-table-column>
          <el-table-column prop="meaning" label="释义" min-width="190" show-overflow-tooltip />
          <el-table-column label="每日计划" min-width="220">
            <template #default="scope">
              <div v-if="scope.row.daily_plans?.length" class="plan-tags">
                <el-tooltip v-for="plan in scope.row.daily_plans.slice(0, 3)" :key="plan.date" :content="plan.status === 'published' ? '已发布' : '草稿'">
                  <el-tag size="small" :type="plan.status === 'published' ? 'success' : 'info'" effect="plain">{{ displayDate(plan.date) }}</el-tag>
                </el-tooltip>
                <el-tooltip v-if="scope.row.daily_plans.length > 3" :content="scope.row.daily_plans.slice(3).map(plan => `${plan.date}（${plan.status === 'published' ? '已发布' : '草稿'}）`).join('、')">
                  <el-tag size="small" effect="plain">+{{ scope.row.daily_plans.length - 3 }}</el-tag>
                </el-tooltip>
              </div>
              <span v-else class="table-secondary">未安排</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100"><template #default="scope"><el-tag :type="scope.row.status === 'published' ? 'success' : 'info'" effect="light">{{ scope.row.status === 'published' ? '已发布' : '草稿' }}</el-tag></template></el-table-column>
          <el-table-column label="最后更新" min-width="155"><template #default="scope">{{ scope.row.updated_at || '—' }}</template></el-table-column>
          <el-table-column label="操作" width="170" fixed="right">
            <template #default="scope">
              <div class="row-actions">
                <el-button link type="primary" :disabled="scope.row.status !== 'published'" @click="openPlanDialog(scope.row)">加入计划</el-button>
                <Link :href="`/admin/words/${scope.row.id}/edit`"><el-button link type="primary">编辑</el-button></Link>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>
      <div v-if="words.data.length" class="table-footer">
        <span>共 {{ words.total }} 个</span>
        <el-pagination background layout="prev, pager, next" :current-page="words.current_page" :page-count="words.last_page" @current-change="page => router.get('/admin/words', { ...filters, page }, { preserveState: true, replace: true })" />
      </div>
    </section>

    <el-dialog v-model="planDialog" title="加入每日单词计划" width="420px">
      <div class="plan-dialog-content">
        <div><span class="table-secondary">单词</span><strong>{{ selectedWord?.headword }}</strong></div>
        <div class="form-field"><label>加入方式</label>
          <el-radio-group :model-value="targetMode" @change="changeTargetMode">
            <el-radio-button value="existing" :disabled="!availableDailyPlans?.length">选择已有计划</el-radio-button>
            <el-radio-button value="new">新建计划</el-radio-button>
          </el-radio-group>
        </div>
        <div v-if="targetMode === 'existing'" class="form-field"><label>选择计划</label>
          <el-select :model-value="selectedPlanDate" class="plan-select" @change="changeExistingPlan">
            <el-option v-for="plan in availableDailyPlans" :key="plan.plan_date" :value="plan.plan_date" :label="`${plan.plan_date} · ${plan.status === 'published' ? '已发布' : '草稿'} · ${plan.word_count}个词`" />
          </el-select>
        </div>
        <div v-else class="form-field"><label>新计划日期</label><el-date-picker v-model="form.planDate" type="date" value-format="YYYY-MM-DD" format="YYYY年MM月DD日" :disabled-date="date => localDate(date) < localDate()" /></div>
        <el-alert v-if="alreadyScheduled" title="这个单词已经安排在该日计划中" type="success" :closable="false" />
        <el-alert v-else title="该日期没有计划时，会自动新建一个草稿计划；加入已有计划不会自动发布。" type="info" :closable="false" />
        <small v-if="form.errors.planDate || form.errors.dailyPlan" class="form-error">{{ form.errors.planDate || form.errors.dailyPlan }}</small>
      </div>
      <template #footer><el-button @click="planDialog = false">取消</el-button><el-button type="primary" :loading="form.processing" :disabled="alreadyScheduled || !form.planDate" @click="addToPlan">确认加入</el-button></template>
    </el-dialog>
  </AdminLayout>
</template>

<style scoped>
.plan-tags,.row-actions{display:flex;align-items:center;gap:7px}.plan-dialog-content{display:grid;gap:18px}.plan-dialog-content>div:first-child{display:flex;align-items:center;gap:12px}.plan-dialog-content .el-date-editor,.plan-select{width:100%}
</style>
