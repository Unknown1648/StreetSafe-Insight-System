// js/main.js
import { initializePublicDashboard } from './ui/dashboard.js';

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('/')) {
        initializePublicDashboard();
    }
});