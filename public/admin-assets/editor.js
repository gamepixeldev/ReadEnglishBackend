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
            if (!value) return;
            const format = form.querySelector('.analysis-format-value')?.value || 'html';
            value.value = format === 'markdown'
                ? form.querySelector('.markdown-editor')?.value || ''
                : editor.innerHTML;
        });
    });
});

document.querySelectorAll('.format-switch').forEach(switcher => {
    const form = switcher.closest('form');
    const formatField = form.querySelector('.analysis-format-value');
    const sync = () => {
        const markdown = formatField.value === 'markdown';
        form.querySelector('.html-editor-tools').hidden = markdown;
        form.querySelector('.rich-editor').hidden = markdown;
        form.querySelector('.markdown-editor').hidden = !markdown;
        switcher.querySelectorAll('.format-option').forEach(button => {
            button.classList.toggle('active', button.dataset.format === formatField.value);
        });
    };
    switcher.addEventListener('click', event => {
        const button = event.target.closest('.format-option');
        if (!button) return;
        const editor = form.querySelector('.rich-editor');
        const markdownEditor = form.querySelector('.markdown-editor');
        if (button.dataset.format === 'markdown' && !markdownEditor.value.trim() && editor.innerHTML.trim()) {
            markdownEditor.value = editor.innerText.trim();
        } else if (button.dataset.format === 'html' && !editor.innerHTML.trim() && markdownEditor.value.trim()) {
            editor.innerText = markdownEditor.value;
        }
        formatField.value = button.dataset.format;
        sync();
    });
    sync();
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
const selectedWords = new Map();
document.querySelectorAll('#selected-words .selected-word').forEach(row => {
    const id = row.dataset.id;
    selectedWords.set(id, {
        id,
        headword: row.querySelector('span').childNodes[0].textContent.trim(),
        meaning: row.querySelector('small')?.textContent || '',
        status: row.querySelector('em') ? 'draft' : 'published',
    });
});
const selectedContainer = document.getElementById('selected-words');
const selectedInputs = document.getElementById('selected-word-inputs');
const wordResults = document.getElementById('word-picker');
function renderSelectedWords() {
    selectedContainer.replaceChildren();
    selectedInputs.replaceChildren();
    selectedWords.forEach(word => {
        const chip = document.createElement('span');
        chip.className = 'selected-word';
        chip.dataset.id = word.id;
        const label = document.createElement('span');
        label.append(document.createTextNode(`${word.headword} `));
        const meaning = document.createElement('small');
        meaning.textContent = word.meaning || '';
        label.append(meaning);
        if (word.status !== 'published') {
            const badge = document.createElement('em');
            badge.textContent = '草稿';
            label.append(' ', badge);
        }
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `移除 ${word.headword}`);
        remove.dataset.removeWord = word.id;
        chip.append(label, remove);
        selectedContainer.append(chip);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'wordIds[]';
        hidden.value = word.id;
        selectedInputs.append(hidden);
    });
    document.getElementById('selected-word-count').textContent = `已选 ${selectedWords.size} 个`;
    document.querySelector('.draft-word-hint').hidden = ![...selectedWords.values()].some(word => word.status !== 'published');
}
selectedContainer?.addEventListener('click', event => {
    const id = event.target.closest('[data-remove-word]')?.dataset.removeWord;
    if (!id) return;
    selectedWords.delete(id);
    renderSelectedWords();
    renderWordResults(lastWordResults);
});
let lastWordResults = [];
function renderWordResults(items) {
    lastWordResults = items;
    wordResults.replaceChildren();
    if (!items.length) {
        const empty = document.createElement('p');
        empty.className = 'helper';
        empty.textContent = wordFilter.value.trim() ? '没有匹配的单词。' : '输入至少一个字后，显示最多 20 个匹配结果。';
        wordResults.append(empty);
        return;
    }
    items.forEach(word => {
        const row = document.createElement('div');
        row.className = 'word-option';
        const copy = document.createElement('span');
        copy.append(document.createTextNode(`${word.headword} `));
        const meaning = document.createElement('small');
        meaning.textContent = word.meaning;
        copy.append(meaning);
        if (word.status !== 'published') {
            const status = document.createElement('em');
            status.textContent = '草稿';
            copy.append(' ', status);
        }
        const action = document.createElement('button');
        action.type = 'button';
        action.className = 'btn small secondary';
        action.textContent = selectedWords.has(String(word.id)) ? '已添加' : '添加';
        action.disabled = selectedWords.has(String(word.id));
        action.addEventListener('click', () => {
            selectedWords.set(String(word.id), {...word, id: String(word.id)});
            renderSelectedWords();
            renderWordResults(lastWordResults);
        });
        row.append(copy, action);
        wordResults.append(row);
    });
}
let wordSearchTimer;
wordFilter?.addEventListener('input', () => {
    clearTimeout(wordSearchTimer);
    const needle = wordFilter.value.trim();
    if (!needle) { renderWordResults([]); return; }
    wordResults.innerHTML = '<p class="helper">正在搜索…</p>';
    wordSearchTimer = setTimeout(async () => {
        try {
            const response = await fetch(`${wordFilter.dataset.searchUrl}?q=${encodeURIComponent(needle)}`, {headers:{Accept:'application/json'}, credentials:'same-origin'});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || '单词搜索失败');
            renderWordResults(result.items || []);
        } catch (error) {
            wordResults.textContent = error.message;
        }
    }, 250);
});

const wordDialog = document.getElementById('new-word-dialog');
document.getElementById('open-new-word')?.addEventListener('click', () => wordDialog.showModal());
document.getElementById('close-new-word')?.addEventListener('click', () => wordDialog.close());
document.getElementById('new-word-form')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const errorBox = document.getElementById('new-word-error');
    errorBox.hidden = true;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
        const response = await fetch(form.dataset.createUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || '创建单词失败');
        selectedWords.set(String(result.id), {...result, id: String(result.id)});
        renderSelectedWords();
        renderWordResults(lastWordResults);
        form.reset();
        wordDialog.close();
    } catch (error) {
        errorBox.textContent = error.message;
        errorBox.hidden = false;
    } finally {
        button.disabled = false;
    }
});

for (const [inputId, previewId, emptyId] of [
    ['coverImage', 'cover-preview-image', 'cover-preview-empty'],
    ['detailImage', 'detail-preview-image', 'detail-preview-empty'],
]) {
    const input = document.getElementById(inputId);
    let temporaryUrl;
    input?.addEventListener('change', () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        const file = input.files?.[0];
        if (!file) return;
        temporaryUrl = URL.createObjectURL(file);
        const preview = document.getElementById(previewId);
        preview.src = temporaryUrl;
        preview.hidden = false;
        document.getElementById(emptyId).hidden = true;
    });
}
