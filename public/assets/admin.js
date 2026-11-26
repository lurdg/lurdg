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
    const { location, latitude, longitude } = form.elements;
    let generatedCoordinates = results.hasAttribute('data-coordinates-filled')
        ? {latitude: latitude.value, longitude: longitude.value}
        : null;

    location.addEventListener('input', () => {
        results.replaceChildren();
        if (generatedCoordinates && latitude.value === generatedCoordinates.latitude && longitude.value === generatedCoordinates.longitude) {
            latitude.value = '';
            longitude.value = '';
        }
        generatedCoordinates = null;
    });
    button.addEventListener('click', async (event) => {
        if (typeof fetch !== 'function' || typeof form.requestSubmit !== 'function') return;
        event.preventDefault();
        const name = location.value.trim();
        if (!name) {
            results.textContent = 'Enter a place name first.';
            location.focus();
            return;
        }

        function fillCoordinates(match) {
            location.value = match.name;
            latitude.value = match.latitude;
            longitude.value = match.longitude;
            generatedCoordinates = {latitude: latitude.value, longitude: longitude.value};
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
                if (error instanceof TypeError || error instanceof SyntaxError) {
                    button.disabled = false;
                    form.requestSubmit(button);
                } else {
                    results.textContent = error.message;
                }
            }
        } finally {
            button.disabled = false;
        }
    });
});
