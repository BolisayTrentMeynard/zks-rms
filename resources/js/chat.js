import { findTopic } from './faq-match';

// FAQ chat panel. The answers are fetched once from /chat/faq when the panel is first opened.
// A typed question is sent to /chat (the AI, server side). If that is unavailable for any reason the
// keyword matcher answers instead, so the chat always replies. Only the typed text and a few earlier
// messages are sent: nothing about the guest.
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
    let busy = false;
    const history = []; // earlier typed questions and replies, sent along for context

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

        return box;
    };

    // Asks the AI. Resolves to the reply text, or null when the keyword answers should be used.
    const askAi = async (question) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 12000);

        try {
            const response = await fetch(widget.dataset.chatAskUrl, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': widget.dataset.csrf,
                },
                body: JSON.stringify({ message: question, history: history.slice(-6) }),
            });

            if (!response.ok) {
                return null;
            }

            const json = await response.json();

            return json.ai === true && typeof json.reply === 'string' && json.reply !== '' ? json.reply : null;
        } catch {
            return null;
        } finally {
            clearTimeout(timer);
        }
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

        if (question === '' || busy) {
            return;
        }

        busy = true;
        input.value = '';
        addMessage(question, true);

        const waiting = addMessage('Typing…', false);

        await load();

        let reply = await askAi(question);
        let lines;

        if (reply !== null) {
            lines = reply.split('\n').filter((line) => line.trim() !== '');
        } else if (data) {
            const topic = findTopic(question, data.topics);
            lines = topic ? topic.answer : [data.fallback];
            reply = lines.join('\n');
        } else {
            lines = ['Sorry, the answers could not be loaded. Please try again, or open the Questions and answers page.'];
            reply = null;
        }

        waiting.remove();
        addMessage(lines, false);

        if (reply !== null) {
            history.push({ role: 'user', content: question }, { role: 'assistant', content: reply.slice(0, 1400) });
            history.splice(0, Math.max(0, history.length - 6));
        }

        busy = false;
        input.focus();
    });
}
