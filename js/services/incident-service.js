// js/services/incident-service.js
import { emit } from '../core/event-bus.js';

const API_BASE = 'api';

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'include',
        ...options
    });

    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.error || `HTTP error ${response.status}`);
    }
    return data;
}

export async function getAllStreets() {
    const data = await fetchJson(`${API_BASE}/streets.php`);
    return data.streets || [];
}

export async function getAllIncidents(scope = 'public') {
    const data = await fetchJson(`${API_BASE}/reports.php?scope=${scope}`);
    return data.reports || [];
}

export async function getMyReports() {
    const data = await fetchJson(`${API_BASE}/reports.php?scope=mine`);
    return data.reports || [];
}

export async function addIncident(newIncident) {
    return createReport(newIncident);
}

export async function createReport(reportData) {
    const payload = {
        street_id: reportData.street_id,
        type: reportData.type,
        severity: reportData.severity || 'medium',
        description: reportData.description || '',
        response_time: reportData.response_time || 0,
        source_name: reportData.source_name || '',
        is_anonymous: !!reportData.is_anonymous
    };

    const data = await fetchJson(`${API_BASE}/reports.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    emit('incident:new', data);
    return data;
}

export async function getNotifications() {
    const data = await fetchJson(`${API_BASE}/notifications.php`);
    return data.notifications || [];
}

export async function markNotificationRead(notificationId) {
    return fetchJson(`${API_BASE}/notifications.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action: 'mark_read', notification_id: notificationId })
    });
}

export async function sendPoliceResponse(incidentId, message, status = 'received', policeAction = '') {
    return fetchJson(`${API_BASE}/notifications.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'respond',
            incident_id: incidentId,
            message,
            status,
            police_action: policeAction
        })
    });
}

export async function assignNotification(notificationId, status = 'reported') {
    return fetchJson(`${API_BASE}/notifications.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'assign',
            notification_id: notificationId,
            status
        })
    });
}

export async function getProfile() {
    const data = await fetchJson(`${API_BASE}/profile.php`);
    return data.profile;
}

export async function updateProfile(profileData) {
    return fetchJson(`${API_BASE}/profile.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(profileData)
    });
}

export async function addStreet(streetData) {
    return fetchJson(`${API_BASE}/streets.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(streetData)
    });
}
