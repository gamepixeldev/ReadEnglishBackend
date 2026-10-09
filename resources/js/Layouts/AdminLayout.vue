<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ title: String, section: String, description: String });
const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const success = computed(() => page.props.flash?.success);
function logout() { router.post('/admin/logout'); }
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>

<template>
  <Head :title="title" />
  <div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
      <Link class="admin-brand" href="/admin"><span class="brand-symbol">E</span><span><strong>精读英语</strong><small>管理后台 · Vue</small></span></Link>
      <div class="nav-section">工作台</div>
      <nav class="side-nav"><Link class="nav-item" :class="{active: $page.component === 'Admin/Dashboard'}" href="/admin"><span class="nav-icon">▦</span>概览</Link></nav>
      <div class="nav-section">内容管理</div>
      <nav class="side-nav">
        <Link class="nav-item" :class="{active: $page.component.startsWith('Admin/Articles')}" href="/admin/articles"><span class="nav-icon">▤</span>精读文章</Link>
        <Link class="nav-item" :class="{active: $page.component.startsWith('Admin/Words')}" href="/admin/words"><span class="nav-icon">Aa</span>单词管理</Link>
      </nav>
      <div class="nav-section">账号</div>
      <nav class="side-nav"><Link class="nav-item" :class="{active: $page.component === 'Admin/Password'}" href="/admin/password"><span class="nav-icon">⚙</span>修改密码</Link></nav>
      <div class="sidebar-bottom"><span class="online-dot"></span> 管理服务运行中</div>
    </aside>
    <div class="admin-content">
      <header class="admin-topbar">
        <el-button class="menu-toggle" text aria-label="打开导航" @click="toggleSidebar">☰</el-button>
        <div class="topbar-location">精读英语 <span>/</span> {{ section }}</div>
        <div class="topbar-user"><span class="user-avatar">{{ (user.name || 'A').slice(0,1) }}</span><span class="user-name">{{ user.name || user.email }}</span><el-button link class="logout-link" @click="logout">退出</el-button></div>
      </header>
      <main class="admin-main">
        <div class="page-heading"><div><p class="eyebrow">{{ section }}</p><h1>{{ title }}</h1><p class="page-description">{{ description }}</p></div><div class="heading-actions"><slot name="actions" /></div></div>
        <el-alert v-if="success" :title="success" type="success" :closable="false" show-icon />
        <el-alert v-if="Object.keys($page.props.errors || {}).length" title="请检查以下内容" type="error" :closable="false" show-icon><ul><li v-for="(messages,key) in $page.props.errors" :key="key">{{ messages }}</li></ul></el-alert>
        <slot />
      </main>
    </div>
  </div>
  <div class="sidebar-backdrop" @click="closeSidebar" />
</template>
