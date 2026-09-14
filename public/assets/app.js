'use strict';

const feedback = document.querySelector('#feedback');
const label = status => status.replaceAll('_', ' ');
function message(text, error = false) {
    feedback.textContent = text;
    feedback.className = error ? 'error' : 'success';
}
async function api(path, options = {}) {
    const response = await fetch('/api/requests' + path, {
        ...options,
        headers: {'Content-Type': 'application/json', ...options.headers}
    });
    const body = await response.json();
    if (!response.ok) {
        const fields = Object.entries(body.error?.fields || {}).map(([key, value]) =>
            label(key) + ': ' + value).join(' ');
        throw new Error((body.error?.message || 'Request failed.') + (fields ? ' ' + fields : ''));
    }
    return body.data;
}
function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
}
const form = document.querySelector('#request-form');
if (form) {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        message('Submitting…');
        try {
            const data = Object.fromEntries(new FormData(form));
            data.quantity = Number(data.quantity);
            const request = await api('', {method: 'POST', body: JSON.stringify(data)});
            message('Request #' + request.id + ' submitted successfully. Thank you.');
            form.reset();
        } catch (error) {
            message(error.message, true);
        } finally {
            button.disabled = false;
        }
    });
}

const list = document.querySelector('#requests');
if (list) {
    const detail = document.querySelector('#detail');
    let selectionVersion = 0;
    async function refresh() {
        const requests = await api('');
        list.replaceChildren();
        document.querySelector('#empty').hidden = requests.length !== 0;
        for (const request of requests) {
            const row = node('tr');
            const customer = node('td', '#' + request.id + ' · ' + request.customer_name);
            const service = node('td', request.service_type + ' × ' + request.quantity);
            const status = node('td');
            status.append(node('span', label(request.request_status), 'badge'));
            const action = node('td');
            const button = node('button', 'Open');
            button.type = 'button';
            button.setAttribute('aria-label', 'Open request ' + request.id);
            button.addEventListener('click', () => open(request.id));
            action.append(button);
            row.append(customer, service, status, action);
            list.append(row);
        }
    }
    async function open(id) {
        const version = ++selectionVersion;
        detail.replaceChildren(node('p', 'Loading request…'));
        try {
            const [request, history] = await Promise.all([api('/' + id), api('/' + id + '/history')]);
            if (version !== selectionVersion) return;
            detail.replaceChildren(node('h2', 'Request #' + request.id),
                node('span', label(request.request_status), 'badge'));
            const fields = node('dl');
            for (const [title, value] of [
                ['Customer', request.customer_name], ['Email', request.customer_email],
                ['Service', request.service_type], ['Quantity', request.quantity],
                ['Due date', request.due_date || 'Not supplied'],
                ['Description', request.request_description || 'Not supplied'],
                ['Created (UTC)', request.created_at], ['Updated (UTC)', request.updated_at]
            ]) fields.append(node('dt', title), node('dd', String(value)));
            detail.append(fields, node('h3', 'Next step'));
            if (!request.allowed_statuses.length) detail.append(node('p', 'This request is closed.'));
            const controls = node('div', undefined, 'controls');
            for (const status of request.allowed_statuses) {
                const button = node('button', label(status));
                button.type = 'button';
                button.addEventListener('click', async () => {
                    controls.querySelectorAll('button').forEach(item => item.disabled = true);
                    try {
                        await api('/' + id + '/status', {method: 'PATCH', body: JSON.stringify({status})});
                        message('Request #' + id + ' updated to ' + label(status) + '.');
                    } catch (error) {
                        message(error.message, true);
                    }
                    try {
                        await refresh();
                        if (version === selectionVersion) await open(id);
                    } catch (error) {
                        message(error.message, true);
                    }
                });
                controls.append(button);
            }
            detail.append(controls, node('h3', 'Status history'));
            const timeline = node('ol');
            for (const item of history) timeline.append(node('li',
                (item.old_status ? label(item.old_status) : 'Created') + ' → ' +
                label(item.new_status) + ' · ' + item.changed_at + ' UTC'));
            detail.append(timeline);
            detail.focus();
        } catch (error) {
            if (version === selectionVersion) {
                detail.replaceChildren(node('p', 'Unable to load this request.'));
                message(error.message, true);
            }
        }
    }
    document.querySelector('#refresh').addEventListener('click', () =>
        refresh().then(() => message('Request list refreshed.')).catch(error => message(error.message, true)));
    refresh().catch(error => message(error.message, true));
}
