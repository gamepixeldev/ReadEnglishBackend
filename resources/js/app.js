import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ElAlert, ElButton, ElConfigProvider, ElDialog, ElInput, ElOption, ElPagination, ElRadioButton, ElRadioGroup, ElSelect, ElTable, ElTableColumn, ElTag, ElUpload } from 'element-plus';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import 'element-plus/dist/index.css';
import '../css/app.css';
import '../css/admin-ui.css';

createInertiaApp({
    title: title => `${title} · 精读英语`,
    resolve: name => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(ElConfigProvider, { locale: zhCn }, () => h(App, props)) }).use(plugin);
        [ElAlert, ElButton, ElDialog, ElInput, ElOption, ElPagination, ElRadioButton, ElRadioGroup, ElSelect, ElTable, ElTableColumn, ElTag, ElUpload]
            .forEach(component => app.component(component.name, component));
        app.mount(el);
    },
    progress: { color: '#315ee8' },
});
