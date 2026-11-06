// Picks the FAQ topic that best fits a typed question. No network, no AI: plain keyword matching.
// Used by the chat panel as the always-available answer source.

/** Lower-case, letters and digits only, words separated by single spaces. */
export function normalize(text) {
    return String(text).toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
}

/**
 * The best topic for a question, or null when nothing matches.
 * A keyword matches when it starts a word in the question ("book" matches "booking").
 * Most matching keywords wins; on a tie the topic with the higher priority wins.
 */
export function findTopic(question, topics) {
    const text = ' ' + normalize(question);

    if (text.trim() === '') {
        return null;
    }

    let best = null;
    let bestHits = 0;

    for (const topic of topics) {
        const hits = topic.keywords.filter((keyword) => text.includes(' ' + normalize(keyword))).length;

        if (hits > bestHits || (hits > 0 && hits === bestHits && topic.priority > best.priority)) {
            best = topic;
            bestHits = hits;
        }
    }

    return best;
}
