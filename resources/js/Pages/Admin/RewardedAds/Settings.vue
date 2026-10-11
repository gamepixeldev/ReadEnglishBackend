<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '../../../Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })
const form = useForm({
  enabled: Boolean(props.settings?.enabled),
  adUnitId: props.settings?.adUnitId || '',
  answerSeconds: Number(props.settings?.answerSeconds || 3),
})
function save() { form.put('/admin/rewarded-ads') }
</script>

<template>
  <AdminLayout title="激励广告" section="用户服务 / 激励广告" description="配置“需要提示”功能的全局广告策略和答案展示时长。">
    <section class="card settings-card">
      <div class="card-header"><div><h2>提示解锁策略</h2><p>用户可在用户广告策略页单独覆盖全局设置。</p></div></div>
      <el-form label-position="top" class="settings-form">
        <el-form-item label="全局激励广告">
          <div class="switch-line"><el-switch v-model="form.enabled" /><span>{{ form.enabled ? '开启后，用户需完整观看广告才能查看提示' : '关闭后，用户点击提示可直接查看答案' }}</span></div>
        </el-form-item>
        <el-form-item label="微信激励视频广告位 ID" :error="form.errors.adUnitId">
          <el-input v-model="form.adUnitId" placeholder="例如 adunit-xxxxxxxxxxxxxxxx" :disabled="!form.enabled" />
          <small>需先在微信公众平台创建激励视频广告位。广告位 ID 由小程序端调用微信广告组件。</small>
        </el-form-item>
        <el-form-item label="答案自动关闭时间（秒）" :error="form.errors.answerSeconds">
          <el-input-number v-model="form.answerSeconds" :min="1" :max="30" :step="1" />
          <small>答案弹层会在指定时间后自动关闭，用户也可以手动关闭。</small>
        </el-form-item>
        <div class="settings-actions"><el-button type="primary" :loading="form.processing" @click="save">保存设置</el-button></div>
      </el-form>
    </section>
    <el-alert class="settings-note" type="info" :closable="false" show-icon title="中途关闭或广告播放失败时，不会展示答案；广告关闭后，小程序会在广告完成回调后请求答案。" />
  </AdminLayout>
</template>

<style scoped>
.settings-card{max-width:860px}.card-header{margin-bottom:22px}.card-header h2{margin:0;color:#2d3a50;font-size:16px}.card-header p{margin:6px 0 0;color:#8995a7;font-size:13px}.settings-form{max-width:620px}.switch-line{display:flex;align-items:center;gap:12px;color:#59677d;font-size:13px}.settings-form small{display:block;margin-top:7px;color:#8995a7;line-height:1.6}.settings-actions{padding-top:6px}.settings-note{max-width:860px;margin-top:16px}
</style>
