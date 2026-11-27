'use strict';

const toggle = document.querySelector('.nav-toggle');
const navigation = document.getElementById('site-nav');

if (toggle && navigation) {
    toggle.addEventListener('click', () => {
        const expanded = navigation.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Close navigation' : 'Open navigation');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.classList.contains('open')) {
            navigation.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation');
            toggle.focus();
        }
    });
}

document.querySelectorAll('form[data-loading]').forEach((form) => {
    const submit = form.querySelector('button[data-loading-label]');
    if (!submit) return;
    const original = submit.innerHTML;

    form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        submit.disabled = true;
        submit.textContent = submit.dataset.loadingLabel;
    });
    window.addEventListener('pageshow', () => {
        form.removeAttribute('aria-busy');
        submit.disabled = false;
        submit.innerHTML = original;
    });
});

document.querySelectorAll('.map[data-latitude]').forEach((element) => {
    if (!window.L) return;
    const coordinates = [Number(element.dataset.latitude), Number(element.dataset.longitude)];
    const map = L.map(element, {scrollWheelZoom: false}).setView(coordinates, 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    const popup = document.createElement('span');
    popup.textContent = element.dataset.location;
    if (element.hasAttribute('data-pulse-marker')) {
        L.circleMarker(coordinates, {
            className: 'map-marker-pulse',
            radius: 13,
            fillColor: '#d32636',
            stroke: false,
            interactive: false
        }).addTo(map);
    }
    const marker = L.marker(coordinates, {alt: element.dataset.location, title: element.dataset.location});
    marker.addTo(map).bindPopup(popup);
});
