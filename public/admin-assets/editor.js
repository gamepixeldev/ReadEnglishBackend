document.querySelectorAll('.content-type').forEach(select => {
    const form = select.closest('form');
    const sync = () => {
        form.querySelectorAll('.type-rich').forEach(element => { element.hidden = select.value !== 'richtext'; });
        form.querySelectorAll('.type-official').forEach(element => { element.hidden = select.value !== 'officialAccount'; });
    };
    select.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('.rich-form').forEach(form => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('.rich-editor').forEach((editor, index) => {
            const value = form.querySelectorAll('.rich-value')[index];
            if (value) value.value = editor.innerHTML;
        });
    });
});

document.querySelectorAll('.toolbar button').forEach(button => {
    button.addEventListener('click', () => {
        const editor = button.closest('.form-field').querySelector('.rich-editor');
        editor.focus();
        document.execCommand(button.dataset.command, false, button.dataset.value || null);
    });
});

const paragraphs = document.getElementById('paragraphs');
const reindexParagraphs = () => {
    paragraphs?.querySelectorAll('.paragraph').forEach((row, index) => {
        row.querySelector('.paragraph-head span').textContent = `第 ${index + 1} 段`;
        row.querySelectorAll('[data-field]').forEach(field => {
            field.name = `paragraphs[${index}][${field.dataset.field}]`;
        });
    });
};
document.getElementById('add-paragraph')?.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'paragraph';
    row.innerHTML = '<div class="paragraph-head"><span></span><button class="ghost remove-paragraph" type="button">移除</button></div><div class="form-grid"><div class="form-field wide"><label>英文句子（一行一句） *</label><textarea class="textarea" data-field="sentences" required></textarea></div><div class="form-field wide"><label>中文翻译 *</label><textarea class="textarea" data-field="translation" required></textarea></div></div>';
    paragraphs.appendChild(row);
    reindexParagraphs();
    row.querySelector('textarea').focus();
});
paragraphs?.addEventListener('click', event => {
    if (!event.target.closest('.remove-paragraph')) return;
    if (paragraphs.querySelectorAll('.paragraph').length <= 1) return;
    event.target.closest('.paragraph').remove();
    reindexParagraphs();
});

const wordFilter = document.getElementById('word-filter');
wordFilter?.addEventListener('input', () => {
    const needle = wordFilter.value.trim().toLocaleLowerCase();
    document.querySelectorAll('#word-picker .word-option').forEach(row => {
        row.hidden = !row.dataset.search.includes(needle);
    });
});
