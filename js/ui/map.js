// js/ui/map.js
import { getAllStreets, getAllIncidents } from '../services/incident-service.js';
import { safetyEngine } from '../services/scoring-engine.js';
import { getSafetyColor } from '../core/utils.js';

const L = window.L;

let mapInstance = null;
let markers = [];
let riskCircles = [];

export async function initializeRealMap(containerId = 'map') {
    if (mapInstance) {
        mapInstance.invalidateSize();
        await updateMapMarkers();
        return;
    }

    if (!L) {
        console.error("❌ Leaflet not loaded");
        return;
    }

    const container = document.getElementById(containerId);
    if (!container) {
        console.error(`❌ Map container #${containerId} not found!`);
        return;
    }

    console.log(`🗺️ Initializing map on container: #${containerId}`);

    mapInstance = L.map(containerId).setView([-1.396, 36.765], 14.5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(mapInstance);

    await updateMapMarkers();
}

export async function updateMapMarkers() {
    if (!mapInstance) return;

    markers.forEach(m => mapInstance.removeLayer(m));
    riskCircles.forEach(c => mapInstance.removeLayer(c));
    markers = [];
    riskCircles = [];

    try {
        const streets = await getAllStreets();
        const incidents = await getAllIncidents();

        streets.forEach(street => {
            if (!street.lat || !street.lng) return;

            const score = safetyEngine.calculateStreetScore(street, incidents);
            const status = safetyEngine.getSafetyLabel(score);
            const areaScore = Number(street.area_score ?? 100);
            const areaStatus = safetyEngine.getSafetyLabel(areaScore);
            const color = getSafetyColor(score);

            const marker = L.circleMarker([street.lat, street.lng], {
                radius: 15,
                fillColor: color === "green" ? "#10b981" : color === "yellow" ? "#f59e0b" : "#ef4444",
                color: "#ffffff",
                weight: 3,
                fillOpacity: 0.9
            }).addTo(mapInstance);

            const popupContent = `
                <b>${street.name}</b><br>
                <span style="color:${status.color};font-weight:bold;">${score}% — ${status.label}</span><br>
                <span style="color:${areaStatus.color};font-weight:bold;">Area score: ${areaScore}% — ${areaStatus.label}</span><br><br>
                <small>Area: ${street.area}<br>
                Lighting: ${street.lighting}<br>
                CCTV: ${street.cctv ? "Yes" : "No"}</small>
            `;

            marker.bindPopup(popupContent);
            markers.push(marker);

            if (score < 50) {
                const riskCircle = L.circle([street.lat, street.lng], {
                    radius: 350,
                    color: "#ef4444",
                    fillColor: "#ef4444",
                    fillOpacity: 0.08,
                    weight: 1
                }).addTo(mapInstance);
                riskCircles.push(riskCircle);
            }
        });
    } catch (error) {
        console.error("❌ Error updating markers:", error);
    }
}