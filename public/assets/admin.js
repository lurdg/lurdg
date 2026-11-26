'use strict';

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelector('[data-print]')?.addEventListener('click', () => window.print());

document.querySelectorAll('form[data-geocode]').forEach((form) => {
    const button = form.querySelector('[data-find-location]');
    const results = form.querySelector('.geocode-results');
    const location = form.elements.location;

    location.addEventListener('input', () => results.replaceChildren());
    button.addEventListener('click', async () => {
        const name = location.value.trim();
        if (!name) {
            results.textContent = 'Enter a place name first.';
            location.focus();
            return;
        }

        function fillCoordinates(match) {
            location.value = match.name;
            form.elements.latitude.value = match.latitude;
            form.elements.longitude.value = match.longitude;
            results.textContent = 'Coordinates filled in. Review the location before recording.';
        }

        button.disabled = true;
        results.textContent = 'Looking up coordinates…';
        try {
            const body = new URLSearchParams({csrf: form.elements.csrf.value, location: name});
            const response = await fetch('/admin/geocode/', {method: 'POST', body, credentials: 'same-origin'});
            const data = await response.json();
            if (location.value.trim() !== name) return;
            results.replaceChildren();
            if (!response.ok) throw new Error(data.error || 'Location lookup failed.');

            if (data.matches.length === 1) {
                fillCoordinates(data.matches[0]);
            } else if (data.matches.length === 0) {
                results.textContent = 'No matches found. Try adding a city or country, or enter coordinates manually.';
            } else {
                results.textContent = 'Choose a matching location to fill in the coordinates.';
                for (const match of data.matches) {
                    const choice = document.createElement('button');
                    choice.type = 'button';
                    choice.className = 'geocode-choice';
                    choice.textContent = match.name;
                    choice.addEventListener('click', () => fillCoordinates(match));
                    results.append(choice);
                }
            }
        } catch (error) {
            if (location.value.trim() === name) {
                results.textContent = error instanceof SyntaxError ? 'Location lookup is currently unavailable.' : error.message;
            }
        } finally {
            button.disabled = false;
        }
    });
});
