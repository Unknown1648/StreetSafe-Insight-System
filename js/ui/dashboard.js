// js/ui/dashboard.js
import { getAllStreets, getAllIncidents } from '../services/incident-service.js';
import { safetyEngine } from '../services/scoring-engine.js';
import { initializeRealMap, updateMapMarkers } from './map.js';
import { on } from '../core/event-bus.js';
import { formatDate } from '../core/utils.js';

let currentMapInitialized = false;

export async function initializePublicDashboard() {
    await renderSummaryKpis();
    await renderStreetsOverview();
    await renderRecentReports();

    if (!currentMapInitialized) {
        await initializeRealMap();
        currentMapInitialized = true;
    } else {
        await updateMapMarkers();
    }
}

async function renderSummaryKpis() {
    const incidents = await getAllIncidents();
    const streets = await getAllStreets();

    const communityIncidents = incidents.filter(incident => !incident.reporter_role || incident.reporter_role === 'community');
    const alertCount = communityIncidents.filter(incident => !['resolved', 'follow_up'].includes(incident.status || '')).length;
    const averageScore = streets.length
        ? Math.round(streets.reduce((sum, street) => sum + safetyEngine.calculateStreetScore(street, communityIncidents), 0) / streets.length)
        : 100;

    const incidentsEl = document.getElementById('kpi-incidents');
    if (incidentsEl) incidentsEl.textContent = communityIncidents.length;

    const alertsEl = document.getElementById('kpi-alerts');
    if (alertsEl) alertsEl.textContent = alertCount;

    const scoreEl = document.getElementById('kpi-score');
    if (scoreEl) scoreEl.textContent = `${averageScore}`;
}

async function renderStreetsOverview() {
    const container = document.getElementById('streets-container');
    if (!container) return;

    const streets = await getAllStreets();
    const incidents = await getAllIncidents();

    if (!streets.length) {
        container.innerHTML = '<p>No location profiles have been added yet. The police/admin office can add them from the portal.</p>';
        return;
    }

    const rankedStreets = [...streets].sort((left, right) => (left.score ?? safetyEngine.calculateStreetScore(left, incidents)) - (right.score ?? safetyEngine.calculateStreetScore(right, incidents)));

    container.innerHTML = rankedStreets.map(street => {
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
            <div class="street-card">
                <div class="street-name">${street.name}</div>
                <div class="street-score" style="color:${status.color};font-weight:700;">${score}% — ${status.label}</div>
                <div class="muted">Area: ${street.area}</div>
                <div class="muted">Area score: ${areaScore}% — ${areaStatus.label}</div>
                <div class="muted">Lighting: ${street.lighting || 'moderate'} · CCTV: ${street.cctv ? 'Yes' : 'No'} · Watch: ${street.neighborhood_watch ? 'Yes' : 'No'}</div>
                <div class="muted">${street.incidents_count || 0} linked reports</div>
                <div class="muted" style="margin-top:6px;">${driverText}</div>
            </div>
        `;
    }).join('');
}

async function renderRecentReports() {
    const container = document.getElementById('incidents-list');
    if (!container) return;

    const incidents = await getAllIncidents();
    if (!incidents.length) {
        container.innerHTML = '<p>No reports yet.</p>';
        return;
    }

    container.innerHTML = incidents.slice(0, 8).map(incident => `
        <div class="incident-item">
            <strong>${incident.type}</strong> — ${incident.street_name || 'Unknown location'}<br>
            <small>Source: ${incident.reporter_label || incident.source_name || 'Anonymous Source'}</small><br>
            <small>Status: ${incident.status}</small><br>
            <small>${formatDate(incident.reported_at || incident.timestamp)}</small>
        </div>
    `).join('');
}

on('incident:new', async () => {
    await renderStreetsOverview();
    await renderRecentReports();
    await updateMapMarkers();
});
