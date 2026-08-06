// js/ui/community.js
import { authManager } from '../core/auth.js';
import { getAllStreets, getAllIncidents, getMyReports, getNotifications, createReport, updateProfile, markNotificationRead } from '../services/incident-service.js';
import { safetyEngine } from '../services/scoring-engine.js';
import { initializeRealMap, updateMapMarkers } from './map.js';
import { formatDate } from '../core/utils.js';
import { on } from '../core/event-bus.js';

let mapReady = false;

export async function initializeCommunityDashboard() {
    const allowed = await authManager.requireAuth(['community'], 'login.html');
    if (!allowed) {
        return;
    }

    bindFormHandlers();
    await loadProfile();
    await loadStreetOptions();
    await renderStreetScores();
    await renderMyReports();
    await renderNotifications();

    if (!mapReady) {
        await initializeRealMap('community-map');
        mapReady = true;
    } else {
        await updateMapMarkers();
    }
}

function bindFormHandlers() {
    const profileForm = document.getElementById('profile-form');
    if (profileForm && !profileForm.dataset.bound) {
        profileForm.dataset.bound = 'true';
        profileForm.addEventListener('submit', saveProfile);
    }

    const reportForm = document.getElementById('report-form');
    if (reportForm && !reportForm.dataset.bound) {
        reportForm.dataset.bound = 'true';
        reportForm.addEventListener('submit', submitReport);
    }
}

async function loadProfile() {
    const profile = await authManager.checkAuth() ? authManager.user : null;
    if (!profile) return;

    const profileData = profile;
    const setValue = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
            element.value = value || '';
        }
    };

    setValue('profile-full-name', profileData.full_name);
    setValue('profile-display-name', profileData.display_name);
    setValue('profile-email', profileData.email);
    setValue('profile-phone', profileData.phone);
    setValue('profile-neighborhood', profileData.neighborhood);
    setValue('profile-address', profileData.address);
    setValue('profile-note', profileData.profile_note);
    const publicToggle = document.getElementById('profile-public');
    if (publicToggle) {
        publicToggle.checked = !!profileData.personal_details_public;
    }

    const headerUser = document.getElementById('community-user-label');
    if (headerUser) {
        headerUser.textContent = profileData.display_name || profileData.full_name || profileData.email || 'Community Member';
    }
}

async function saveProfile(event) {
    event.preventDefault();
    const payload = {
        full_name: document.getElementById('profile-full-name').value,
        display_name: document.getElementById('profile-display-name').value,
        email: document.getElementById('profile-email').value,
        phone: document.getElementById('profile-phone').value,
        neighborhood: document.getElementById('profile-neighborhood').value,
        address: document.getElementById('profile-address').value,
        personal_details_public: document.getElementById('profile-public').checked,
        profile_note: document.getElementById('profile-note').value
    };

    await updateProfile(payload);
    alert('Profile updated successfully');
    await loadProfile();
}

async function loadStreetOptions() {
    const select = document.getElementById('report-street-select');
    if (!select) return;

    const streets = await getAllStreets();
    let html = '<option value="">Choose location</option>';
    streets.forEach(street => {
        html += `<option value="${street.id}">${street.name} (${street.area})</option>`;
    });
    select.innerHTML = html;
}

async function submitReport(event) {
    event.preventDefault();

    const payload = {
        street_id: document.getElementById('report-street-select').value,
        type: document.getElementById('report-type').value,
        severity: document.getElementById('report-severity').value,
        description: document.getElementById('report-description').value,
        response_time: document.getElementById('report-response-time').value,
        source_name: document.getElementById('report-source-name').value,
        is_anonymous: document.getElementById('report-anonymous').checked
    };

    await createReport(payload);
    alert('Report submitted successfully');
    event.target.reset();
    await renderMyReports();
    await renderNotifications();
}

async function renderStreetScores() {
    const container = document.getElementById('street-scores');
    if (!container) return;

    const streets = await getAllStreets();
    const incidents = await getAllIncidents();
    const rankedStreets = [...streets].sort((left, right) => (left.score ?? safetyEngine.calculateStreetScore(left, incidents)) - (right.score ?? safetyEngine.calculateStreetScore(right, incidents)));

    const html = rankedStreets.map(street => {
        const score = Number(street.score ?? safetyEngine.calculateStreetScore(street, incidents));
        const status = safetyEngine.getSafetyLabel(score);
        const areaScore = Number(street.area_score ?? 100);
        const areaStatus = safetyEngine.getSafetyLabel(areaScore);
        const riskDrivers = [];
        if (street.lighting === 'poor') riskDrivers.push('Poor lighting');
        if (!street.cctv) riskDrivers.push('No CCTV');
        if (!street.neighborhood_watch) riskDrivers.push('No watch');
        const driverText = riskDrivers.length ? riskDrivers.join(' · ') : 'Good environmental coverage';
        return `
            <div class="info-card">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <strong>${street.name}</strong>
                        <div>${street.area}</div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:${status.color === 'green' ? '#dcfce7' : status.color === 'yellow' ? '#fef3c7' : '#fee2e2'};color:${status.color === 'green' ? '#166534' : status.color === 'yellow' ? '#b45309' : '#991b1b'};">${status.label}</span>
                </div>
                <div style="color:${status.color};font-weight:700;margin-top:8px;">${score}% safety score</div>
                <div class="muted">Area score: ${areaScore}% — ${areaStatus.label}</div>
                <small>${street.incidents_count || 0} linked reports</small>
                <div class="muted" style="margin-top:6px;">${driverText}</div>
            </div>
        `;
    }).join('');

    container.innerHTML = html || '<p>No location profiles yet. Ask the admin office to add streets.</p>';
}

async function renderMyReports() {
    const container = document.getElementById('my-reports');
    if (!container) return;

    const reports = await getMyReports();
    container.innerHTML = reports.length ? reports.map(report => `
        <div class="info-card">
            <strong>${report.type}</strong> - ${report.street_name || 'Unknown location'}
            <div>Status: ${report.status}</div>
            <div>Source: ${report.reporter_label || 'Anonymous Source'}</div>
            <div>${report.description || ''}</div>
            <small>${formatDate(report.reported_at || report.timestamp)}</small>
            ${report.response_message ? `<div class="response-box">${report.response_message}</div>` : ''}
        </div>
    `).join('') : '<p>You have not reported anything yet.</p>';
}

async function renderNotifications() {
    const container = document.getElementById('notifications-list');
    if (!container) return;

    const notifications = await getNotifications();
    container.innerHTML = notifications.length ? notifications.map(notification => `
        <div class="info-card ${notification.is_read ? '' : 'unread'}">
            <strong>${notification.title}</strong>
            <div>${notification.message}</div>
            <small>${formatDate(notification.created_at)}</small>
            ${notification.is_read ? '<div class="muted">Read</div>' : `<button data-notification-id="${notification.id}" class="mark-read-btn">Mark as read</button>`}
        </div>
    `).join('') : '<p>No notifications yet.</p>';

    container.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', async () => {
            await markNotificationRead(button.dataset.notificationId);
            await renderNotifications();
        });
    });
}

on('incident:new', async () => {
    await renderStreetScores();
    await renderMyReports();
    await updateMapMarkers();
});
