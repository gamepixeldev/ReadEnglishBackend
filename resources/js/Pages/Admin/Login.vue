<script setup>
import { Head, useForm } from '@inertiajs/vue3';
const form = useForm({ email: '', password: '' });
function submit() { form.post('/admin/login'); }
</script>
<template>
  <Head title="管理员登录" />
  <div class="login-view"><main class="login-card">
    <div class="login-brand"><span class="brand-symbol">E</span>精读英语管理后台</div>
    <h1>登录账号</h1><p>输入管理员账号和密码</p>
    <form @submit.prevent="submit">
      <div class="form-field"><label for="email">管理员账号</label><input id="email" v-model="form.email" class="input" type="email" autocomplete="username" required autofocus><small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small></div>
      <div class="form-field"><label for="password">密码</label><input id="password" v-model="form.password" class="input" type="password" autocomplete="current-password" required><small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small></div>
      <button class="btn" type="submit" :disabled="form.processing">{{ form.processing ? '正在登录…' : '登录' }}</button>
    </form><div class="login-foot">精读英语 · 内容管理</div>
  </main></div>
</template>
