'use strict';

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelector('[data-print]')?.addEventListener('click', () => window.print());

document.querySelector('[data-find-location]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const form = button.closest('form');
    const results = form.querySelector('.geocode-results');
    const name = form.elements.location.value.trim();
    if (!name) {
        results.textContent = 'Enter a place name first.';
        form.elements.location.focus();
        return;
    }
    button.disabled = true;
    results.textContent = 'Looking up coordinates…';
    try {
        const body = new URLSearchParams({csrf: form.elements.csrf.value, location: name});
        const response = await fetch('/admin/geocode/', {method: 'POST', body, credentials: 'same-origin'});
        const data = await response.json();
        results.replaceChildren();
        if (!response.ok) throw new Error(data.error || 'Location lookup failed.');
        if (!data.matches.length) {
            results.textContent = 'No matches found. Try adding a city or country, or enter coordinates manually.';
            return;
        }
        for (const match of data.matches) {
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.className = 'geocode-choice';
            choice.textContent = match.name;
            choice.addEventListener('click', () => {
                form.elements.location.value = match.name;
                form.elements.latitude.value = match.latitude;
                form.elements.longitude.value = match.longitude;
                results.textContent = 'Coordinates filled in. Review the location before recording.';
            });
            results.append(choice);
        }
    } catch (error) {
        results.textContent = error instanceof SyntaxError ? 'Location lookup is currently unavailable.' : error.message;
    } finally {
        button.disabled = false;
    }
});
