import { findTopic } from './faq-match';

// FAQ chat panel. The answers are fetched once from /chat/faq when the panel is first opened.
// Nothing about the guest is sent or shown: it is the same public FAQ for everyone.
const widget = document.querySelector('[data-chat-widget]');

if (widget) {
    const button = widget.querySelector('[data-chat-toggle]');
    const panel = widget.querySelector('[data-chat-panel]');
    const log = widget.querySelector('[data-chat-log]');
    const topics = widget.querySelector('[data-chat-topics]');
    const form = widget.querySelector('[data-chat-form]');
    const input = widget.querySelector('[data-chat-input]');

    let data = null;
    let loading = null;

    // Text only, never HTML, so nothing typed or stored can inject markup.
    const addMessage = (lines, fromGuest) => {
        const box = document.createElement('div');
        box.className = fromGuest
            ? 'ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-pool px-3.5 py-2 text-sm text-white'
            : 'mr-auto max-w-[92%] space-y-1.5 rounded-2xl rounded-bl-sm bg-mist px-3.5 py-2.5 text-sm text-ink';

        (Array.isArray(lines) ? lines : [lines]).forEach((line) => {
            const p = document.createElement('p');
            p.textContent = line;
            box.appendChild(p);
        });

        log.appendChild(box);
        log.scrollTop = log.scrollHeight;
    };

    const load = () => {
        loading ??= fetch(widget.dataset.chatUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('bad response'))))
            .then((json) => {
                data = json;

                data.topics.forEach((topic) => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.textContent = topic.title;
                    chip.className = 'rounded-full border border-line bg-white px-3 py-1.5 text-left text-xs font-semibold text-pool hover:border-pool';
                    chip.addEventListener('click', () => {
                        addMessage(topic.title, true);
                        addMessage(topic.answer, false);
                    });
                    topics.appendChild(chip);
                });

                addMessage(data.intro, false);
            })
            .catch(() => {
                loading = null; // try again next time it is opened
                addMessage('Sorry, the answers could not be loaded. Please try again, or open the Questions and answers page.', false);
            });

        return loading;
    };

    const open = (show) => {
        panel.hidden = !show;
        button.setAttribute('aria-expanded', String(show));

        if (show) {
            input.focus();
            load();
        } else {
            button.focus();
        }
    };

    button.addEventListener('click', () => open(panel.hidden));
    widget.querySelector('[data-chat-close]').addEventListener('click', () => open(false));
    widget.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            open(false);
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = input.value.trim().slice(0, 300);

        if (question === '') {
            return;
        }

        input.value = '';
        addMessage(question, true);

        await load();

        if (data) {
            const topic = findTopic(question, data.topics);
            addMessage(topic ? topic.answer : data.fallback, false);
        }
    });
}
