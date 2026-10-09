<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

defineProps({ levels: { type: Array, default: () => [] } })
const dialogVisible = ref(false)
const form = useForm({ name: '' })
function addLevel() {
  form.post('/admin/grammar-levels', { onSuccess: () => { form.reset(); dialogVisible.value = false } })
}
function toggle(level) { router.post(`/admin/grammar-levels/${level.id}/toggle`) }
function move(level, direction) { router.post(`/admin/grammar-levels/${level.id}/move/${direction}`) }
</script>

<template>
  <AdminLayout title="语法级别" section="内容管理 / 语法级别" description="维护语法课程编辑时使用的级别选项。">
    <template #actions><el-button type="primary" @click="dialogVisible = true">＋ 添加级别</el-button></template>
    <section class="card">
      <div class="card-header"><div><h2>级别列表</h2><p>停用后不能用于新课程，已有课程不受影响。</p></div></div>
      <div class="card-body">
        <el-table :data="levels" row-key="id" stripe>
          <el-table-column label="顺序" width="150"><template #default="scope"><el-button link :disabled="scope.$index === 0" @click="move(scope.row, 'up')">上移</el-button><el-button link :disabled="scope.$index === levels.length - 1" @click="move(scope.row, 'down')">下移</el-button></template></el-table-column>
          <el-table-column prop="name" label="级别名称" min-width="180" />
          <el-table-column prop="content_count" label="关联课程" width="120" />
          <el-table-column label="状态" width="120"><template #default="scope"><el-tag :type="scope.row.is_active ? 'success' : 'info'">{{ scope.row.is_active ? '启用' : '已停用' }}</el-tag></template></el-table-column>
          <el-table-column label="操作" width="120"><template #default="scope"><el-button link :type="scope.row.is_active ? 'danger' : 'primary'" @click="toggle(scope.row)">{{ scope.row.is_active ? '停用' : '启用' }}</el-button></template></el-table-column>
          <template #empty><el-empty description="还没有语法级别" /></template>
        </el-table>
      </div>
    </section>
    <el-dialog v-model="dialogVisible" title="添加语法级别" width="420px" destroy-on-close>
      <el-form @submit.prevent="addLevel"><el-form-item label="级别名称" :error="form.errors.name"><el-input v-model="form.name" maxlength="32" show-word-limit placeholder="例如：高级语法" @keyup.enter="addLevel" /></el-form-item></el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" :loading="form.processing" @click="addLevel">添加</el-button></template>
    </el-dialog>
  </AdminLayout>
</template>
