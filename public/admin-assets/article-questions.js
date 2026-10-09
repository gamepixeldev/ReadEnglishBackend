const questionList = document.getElementById('reading-question-list');
const questionTemplate = document.getElementById('reading-question-template');
const letters = 'ABCDEFGH';

function refreshAnswerChoices(card) {
    const options = card.querySelector('[data-question-options]');
    const select = card.querySelector('[data-choice-answer]');
    if (!options || !select) return;
    const count = options.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean).length;
    const available = Math.max(2, Math.min(8, count));
    const selected = select.value || 'A';
    select.replaceChildren(...Array.from({length: available}, (_, index) => {
        const option = document.createElement('option');
        option.value = letters[index];
        option.textContent = letters[index];
        return option;
    }));
    select.value = letters.slice(0, available).includes(selected) ? selected : 'A';
}

function updateQuestionKind(card) {
    const isChoice = card.querySelector('[data-question-kind]')?.value === 'choice';
    card.querySelectorAll('.choice-fields').forEach(field => { field.hidden = !isChoice; });
    const choiceAnswer = card.querySelector('[data-choice-answer]');
    const fillAnswer = card.querySelector('.fill-answer');
    const label = card.querySelector('[data-answer-label]');
    const hint = card.querySelector('.answer-hint');
    if (choiceAnswer && fillAnswer) {
        choiceAnswer.hidden = !isChoice;
        choiceAnswer.disabled = !isChoice;
        fillAnswer.hidden = isChoice;
        fillAnswer.disabled = isChoice;
        label.textContent = isChoice ? '正确选项' : '参考答案';
        hint.textContent = isChoice ? '选择正确选项字母。' : '填空题按此答案判定，比较时不区分大小写。';
    }
}

function renumberQuestions() {
    const cards = [...questionList.querySelectorAll('[data-question]')];
    cards.forEach((card, index) => {
        card.querySelector('[data-question-number]').textContent = index + 1;
        card.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace(/questions\[\d+\]/, `questions[${index}]`);
        });
        refreshAnswerChoices(card);
        updateQuestionKind(card);
    });
}

document.getElementById('add-reading-question')?.addEventListener('click', () => {
    const index = questionList.querySelectorAll('[data-question]').length;
    const templateHtml = questionTemplate.innerHTML.replaceAll('__INDEX__', String(index));
    const holder = document.createElement('div');
    holder.innerHTML = templateHtml.trim();
    questionList.append(holder.firstElementChild);
    renumberQuestions();
    questionList.lastElementChild.querySelector('textarea[name$="[prompt]"]').focus();
});

questionList?.addEventListener('click', event => {
    const remove = event.target.closest('[data-remove-question]');
    if (!remove) return;
    remove.closest('[data-question]').remove();
    renumberQuestions();
});

questionList?.addEventListener('input', event => {
    if (event.target.matches('[data-question-options]')) refreshAnswerChoices(event.target.closest('[data-question]'));
});
questionList?.addEventListener('change', event => {
    if (event.target.matches('[data-question-kind]')) updateQuestionKind(event.target.closest('[data-question]'));
});
renumberQuestions();
