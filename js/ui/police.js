// js/ui/police.js
import { authManager } from '../core/auth.js';
import { getAllStreets, getAllIncidents, getNotifications, createReport, sendPoliceResponse, addStreet, markNotificationRead, assignNotification } from '../services/incident-service.js';
import { safetyEngine } from '../services/scoring-engine.js';
import { initializeRealMap, updateMapMarkers } from './map.js';
import { formatDate } from '../core/utils.js';
import { on } from '../core/event-bus.js';
import { getStreetReports } from '../services/analytics-service.js';

let policeMapInitialized = false;

export async function initializePolicePortal() {
    const allowed = await authManager.requireAuth(['police'], 'login.html');
    if (!allowed) {
        return;
    }

    bindFormHandlers();
    await renderPoliceOverview();
    await renderAllIncidents();
    await renderNotifications();
    await renderStreetReports();
    await loadStreetOptions();

    const mapContainer = document.getElementById('police-map');
    if (mapContainer) {
        if (!policeMapInitialized) {
            await initializeRealMap('police-map');
            policeMapInitialized = true;
        } else {
            await updateMapMarkers();
        }
    }
}

function bindFormHandlers() {
    const incidentForm = document.getElementById('incident-form');
    if (incidentForm && !incidentForm.dataset.bound) {
        incidentForm.dataset.bound = 'true';
        incidentForm.addEventListener('submit', submitIncident);
    }

    const responseForm = document.getElementById('response-form');
    if (responseForm && !responseForm.dataset.bound) {
        responseForm.dataset.bound = 'true';
        responseForm.addEventListener('submit', submitResponse);
    }

    const streetForm = document.getElementById('street-form');
    if (streetForm && !streetForm.dataset.bound) {
        streetForm.dataset.bound = 'true';
        streetForm.addEventListener('submit', submitStreet);
    }

    const streetPicker = document.getElementById('street-picker');
    if (streetPicker && !streetPicker.dataset.bound) {
        streetPicker.dataset.bound = 'true';
        streetPicker.addEventListener('change', async () => {
            const streetId = streetPicker.value;
            if (!streetId) {
                clearStreetForm();
                return;
            }
            const streets = await getAllStreets();
            const street = streets.find(item => String(item.id) === streetId);
            if (street) {
                populateStreetForm(street);
            }
        });
    }

    const coordinateToggle = document.getElementById('edit-coordinates');
    if (coordinateToggle && !coordinateToggle.dataset.bound) {
        coordinateToggle.dataset.bound = 'true';
        coordinateToggle.addEventListener('change', () => {
            const disabled = !coordinateToggle.checked;
            document.getElementById('street-lat').disabled = disabled;
            document.getElementById('street-lng').disabled = disabled;
        });
    }

    const reportGroupSelect = document.getElementById('street-report-group');
    if (reportGroupSelect && !reportGroupSelect.dataset.bound) {
        reportGroupSelect.dataset.bound = 'true';
        reportGroupSelect.addEventListener('change', () => renderStreetReports());
    }
}

async function loadStreetOptions() {
    const streets = await getAllStreets();
    const reportSelect = document.getElementById('incident-street-select');
    const streetSelect = document.getElementById('response-street-select');
    const streetPicker = document.getElementById('street-picker');

    const options = '<option value="">Choose location</option>' + streets.map(street => `<option value="${street.id}">${street.name} (${street.area})</option>`).join('');
    if (reportSelect) reportSelect.innerHTML = options;
    if (streetSelect) streetSelect.innerHTML = options;

    if (streetPicker) {
        const currentValue = streetPicker.value;
        streetPicker.innerHTML = '<option value="">Select existing street to edit</option>' + streets.map(street => `<option value="${street.id}" ${String(street.id) === currentValue ? 'selected' : ''}>${street.name} (${street.area})</option>`).join('');
    }
}

async function submitIncident(event) {
    event.preventDefault();

    await createReport({
        street_id: document.getElementById('incident-street-select').value,
        type: document.getElementById('incident-type').value,
        severity: document.getElementById('incident-severity').value,
        description: document.getElementById('incident-description').value,
        response_time: document.getElementById('incident-response-time').value,
        source_name: 'Police Log',
        is_anonymous: false
    });

    alert('Incident recorded successfully');
    event.target.reset();
    await refreshPoliceView();
}

async function submitResponse(event) {
    event.preventDefault();
    const incidentId = document.getElementById('response-incident-id').value;
    const message = document.getElementById('response-message').value;
    const status = document.getElementById('response-status').value;
    await sendPoliceResponse(incidentId, message, status);
    alert('Response sent successfully');
    event.target.reset();
    await renderNotifications();
    await renderAllIncidents();
}

async function submitStreet(event) {
    event.preventDefault();
    const streetId = document.getElementById('street-id').value;
    const isUpdate = Boolean(streetId);

    await addStreet({
        street_id: streetId || undefined,
        name: document.getElementById('street-name').value,
        area: document.getElementById('street-area').value,
        lat: document.getElementById('street-lat').value,
        lng: document.getElementById('street-lng').value,
        lighting: document.getElementById('street-lighting').value,
        cctv: document.getElementById('street-cctv').checked,
        neighborhood_watch: document.getElementById('street-watch').checked,
        notes: document.getElementById('street-notes').value
    });

    alert(isUpdate ? 'Location profile updated' : 'Location profile saved');
    clearStreetForm();
    await loadStreetOptions();
    await refreshPoliceView();
}

async function refreshPoliceView() {
    await renderPoliceOverview();
    await renderAllIncidents();
    await renderNotifications();
    await renderStreetReports();
    await updateMapMarkers();
}

async function renderPoliceOverview() {
    const container = document.getElementById('police-streets-container');
    if (!container) return;

    const streets = await getAllStreets();
    const incidents = await getAllIncidents('all');

    container.innerHTML = streets.length ? streets.map(street => {
        const score = Number(street.score ?? safetyEngine.calculateStreetScore(street, incidents));
        const status = safetyEngine.getSafetyLabel(score);
        const areaScore = Number(street.area_score ?? 100);
        const areaStatus = safetyEngine.getSafetyLabel(areaScore);
        const emphasis = score < 60 ? 'border-red-200 bg-red-50' : score < 75 ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50';
        return `
            <div class="info-card ${emphasis}">
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <strong>${street.name}</strong>
                        <div>${street.area}</div>
                        <div style="color:${status.color};font-weight:700;">${score}% - ${status.label}</div>
                        <div class="muted">Area score: ${areaScore}% — ${areaStatus.label}</div>
                        <small>${street.incidents_count || 0} reports linked</small>
                        <div class="muted">Lighting: ${street.lighting || 'moderate'} · CCTV: ${street.cctv ? 'Yes' : 'No'} · Watch: ${street.neighborhood_watch ? 'Yes' : 'No'}</div>
                    </div>
                    <button type="button" class="text-sm bg-slate-100 px-3 py-1 rounded-xl" data-edit-street="${street.id}">Edit</button>
                </div>
            </div>
        `;
    }).join('') : '<p>No location profiles yet. Create one below.</p>';

    container.querySelectorAll('[data-edit-street]').forEach(button => {
        button.addEventListener('click', () => {
            const streetId = button.getAttribute('data-edit-street');
            const street = streets.find(item => String(item.id) === streetId);
            if (street) {
                populateStreetForm(street);
            }
        });
    });
}

async function renderAllIncidents() {
    const container = document.getElementById('all-incidents-list');
    if (!container) return;

    const incidents = await getAllIncidents();
    container.innerHTML = incidents.length ? incidents.map(incident => `
        <div class="info-card">
            <strong>${incident.type}</strong> - ${incident.street_name || 'Unknown location'}
            <div>Status: ${incident.status}</div>
            <div>Reporter: ${incident.reporter_label || incident.source_name || 'Community member'}</div>
            <div>${incident.description || ''}</div>
            <small>${formatDate(incident.reported_at || incident.timestamp)}</small>
            ${incident.response_message ? `<div class="response-box">${incident.response_message}</div>` : ''}
        </div>
    `).join('') : '<p>No reports available yet.</p>';
}

async function renderNotifications() {
    const container = document.getElementById('police-notifications-list');
    if (!container) return;

    const notifications = await getNotifications();
    container.innerHTML = notifications.length ? notifications.map(notification => `
        <div class="info-card ${notification.is_read ? '' : 'unread'}">
            <strong>${notification.title}</strong>
            <div>${renderNotificationBody(notification)}</div>
            <small>${formatDate(notification.created_at)}</small>
            ${notification.payload ? `<div class="muted">${notification.payload.street_name || 'Pending report'} · ${notification.payload.type || ''}</div>` : ''}
            <div class="notification-actions">
                ${notification.payload && !notification.incident_id ? `<button data-notification-id="${notification.id}" class="assign-btn">Assign to incidents</button>` : ''}
                ${notification.is_read ? '<div class="muted">Read</div>' : `<button data-notification-id="${notification.id}" class="mark-read-btn">Mark as read</button>`}
            </div>
        </div>
    `).join('') : '<p>No notifications yet.</p>';

    container.querySelectorAll('.assign-btn').forEach(button => {
        button.addEventListener('click', async () => {
            await assignNotification(button.dataset.notificationId);
            await refreshPoliceView();
        });
    });

    container.querySelectorAll('.mark-read-btn').forEach(button => {
        button.addEventListener('click', async () => {
            await markNotificationRead(button.dataset.notificationId);
            await renderNotifications();
        });
    });
}

async function renderStreetReports() {
    const container = document.getElementById('street-report-list');
    if (!container) return;

    const groupBy = document.getElementById('street-report-group')?.value || 'type';
    const report = await getStreetReports(groupBy);

    if (!report.rows || !report.rows.length) {
        container.innerHTML = '<p>No community crime reports available for this grouping yet.</p>';
        return;
    }

    container.innerHTML = report.rows.map(row => {
        if (groupBy === 'score') {
            return `
                <div class="info-card">
                    <strong>${row.bucket}</strong>
                    <div>${row.street_name || 'Street'} · ${row.street_area || ''}</div>
                    <div>Score: ${row.score}%</div>
                </div>
            `;
        }

        const bucket = row.bucket || 'Unknown';
        const count = row.count || 0;
        const responseTime = row.avg_response_time ? ` · Avg response ${Math.round(row.avg_response_time)}m` : '';
        return `
            <div class="info-card">
                <strong>${bucket}</strong>
                <div>${count} recorded report${count === 1 ? '' : 's'}${responseTime}</div>
            </div>
        `;
    }).join('');
}

function populateStreetForm(street) {
    const form = document.getElementById('street-form');
    if (!form) return;

    document.getElementById('street-id').value = street.id || '';
    document.getElementById('street-name').value = street.name || '';
    document.getElementById('street-area').value = street.area || '';
    document.getElementById('street-lat').value = street.lat ?? '';
    document.getElementById('street-lng').value = street.lng ?? '';
    document.getElementById('street-lighting').value = street.lighting || 'moderate';
    document.getElementById('street-cctv').checked = !!street.cctv;
    document.getElementById('street-watch').checked = !!street.neighborhood_watch;
    document.getElementById('street-notes').value = street.notes || '';
    document.getElementById('street-form-title').textContent = `Edit ${street.name || 'street profile'}`;
    document.getElementById('street-submit-label').textContent = 'Update Street Details';
    document.getElementById('edit-coordinates').checked = false;
    document.getElementById('street-lat').disabled = true;
    document.getElementById('street-lng').disabled = true;
}

function clearStreetForm() {
    const form = document.getElementById('street-form');
    if (!form) return;

    form.reset();
    document.getElementById('street-id').value = '';
    document.getElementById('street-picker').value = '';
    document.getElementById('street-form-title').textContent = 'Add or Edit Street Profile';
    document.getElementById('street-submit-label').textContent = 'Save Street Details';
    document.getElementById('edit-coordinates').checked = false;
    document.getElementById('street-lat').disabled = true;
    document.getElementById('street-lng').disabled = true;
}

function renderNotificationBody(notification) {
    if (notification.payload) {
        const parts = [];
        if (notification.payload.street_name) {
            parts.push(`<strong>${notification.payload.street_name}</strong>`);
        }
        if (notification.payload.street_area) {
            parts.push(`<div>${notification.payload.street_area}</div>`);
        }
        if (notification.payload.type) {
            parts.push(`<div>Type: ${notification.payload.type}</div>`);
        }
        if (notification.payload.severity) {
            parts.push(`<div>Severity: ${notification.payload.severity}</div>`);
        }
        if (notification.payload.description) {
            parts.push(`<div>${notification.payload.description}</div>`);
        }
        return parts.join('');
    }

    return notification.message;
}

export async function renderResponseTable() {
    const container = document.getElementById('response-table-body');
    if (!container) return;

    const notifications = await getNotifications();

    container.innerHTML = notifications.map(notification => {
        const currentStatus = notification.incident_status || 'pending';
        const currentAction = notification.incident_action || '';
        return `
        <tr class="border-t">
            <td class="p-2 border">${notification.id}</td>
            <td class="p-2 border">${notification.title}</td>
            <td class="p-2 border">${notification.payload?.street_name || 'Unknown'}</td>
            <td class="p-2 border">${notification.payload?.type || '-'}</td>
            <td class="p-2 border">${notification.payload?.severity || '-'}</td>
            <td class="p-2 border">
                ${notification.incident_id ? `
                    <select id="status-${notification.incident_id}" class="border rounded-lg p-2 w-full text-sm">
                        <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="in_progress" ${currentStatus === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="resolved" ${currentStatus === 'resolved' ? 'selected' : ''}>Resolved</option>
                    </select>
                ` : '<span class="text-xs text-slate-500">No incident</span>'}
            </td>
            <td class="p-2 border text-xs">${notification.payload?.description || notification.message || ''}</td>
            <td class="p-2 border">
                ${notification.incident_id ? `<textarea id="reply-${notification.incident_id}" class="w-full border p-1 rounded"></textarea>` : '<span class="text-xs text-slate-500">No incident yet</span>'}
            </td>
            <td class="p-2 border">
                ${notification.incident_id ? `
                    <select id="action-${notification.incident_id}" class="border rounded-lg p-2 w-full text-sm">
                        <option value="">Select Action</option>
                        <option value="Dispatch Patrol" ${currentAction === 'Dispatch Patrol' ? 'selected' : ''}>🚓 Dispatch Patrol</option>
                        <option value="Under Investigation" ${currentAction === 'Under Investigation' ? 'selected' : ''}>🔎 Under Investigation</option>
                        <option value="Close Case" ${currentAction === 'Close Case' ? 'selected' : ''}>✅ Close Case</option>
                    </select>
                ` : '<span class="text-xs text-slate-500">No incident yet</span>'}
            </td>
            <td class="p-2 border text-center">
                ${notification.incident_id ? `
                    <button class="bg-green-600 text-white px-3 py-1 rounded" onclick="submitTableResponse(${notification.incident_id})">Save</button>
                ` : '<span class="text-xs text-slate-500">Assign first</span>'}
            </td>
        </tr>
    `;
    }).join('');
}

window.submitTableResponse = async function(incidentId) {
    const message = document.getElementById(`reply-${incidentId}`)?.value || '';
    const status = document.getElementById(`status-${incidentId}`)?.value || 'in_progress';
    const policeAction = document.getElementById(`action-${incidentId}`)?.value || '';

    if (!message) {
        alert("Response message required");
        return;
    }

    await sendPoliceResponse(incidentId, message, status, policeAction);

    alert("Response saved");

    await renderResponseTable();
    await renderAllIncidents();
    await renderNotifications();
};

on('incident:new', async () => {
    await refreshPoliceView();
});