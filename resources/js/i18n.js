import { ref } from 'vue';
import it from '../../lang/it.json';

// English (default) or Italian. Texts are written in English in the pages and translated with
// lang/it.json, the same file the server uses for emails and messages.
//   t('Save')                          → "Salva"
//   t('Hello :name', { name: 'Anna' }) → "Ciao Anna"
//   tc(':count album|:count albums', 3) → "3 album"
export const locale = ref('en');
const dictionaries = { it };

export function t(key, params = {}) {
    let text = (locale.value !== 'en' && dictionaries[locale.value]?.[key]) || key;
    for (const [name, value] of Object.entries(params)) text = text.replaceAll(`:${name}`, value);
    return text;
}

// "singular|plural": the first form for 1, the second otherwise (Laravel's style)
export function tc(key, count, params = {}) {
    const forms = t(key, { count, ...params }).split('|');
    return forms.length > 1 && count !== 1 ? forms[1] : forms[0];
}

// For Intl (dates, numbers, money)
export const intlLocale = () => (locale.value === 'it' ? 'it-IT' : 'en-GB');
