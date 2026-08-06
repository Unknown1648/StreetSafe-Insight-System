// js/services/scoring-engine.js
import { get } from '../core/storage.js';

export class StreetSafetyEngine {
    constructor() {
        this.severityWeight = {
            low: 1.0,
            medium: 1.8,
            high: 2.8
        };
    }

    calculateStreetScore(street, allIncidents) {
        if (typeof street?.score === 'number') {
            return Math.max(0, Math.min(100, Math.round(street.score)));
        }

        let score = 100;
        const communityIncidents = (allIncidents || []).filter(incident => !incident.reporter_role || incident.reporter_role === 'community');
        const recentIncidents = this.getRecentIncidents(street.id, communityIncidents);

        if (recentIncidents.length === 0) {
            return this.applyEnvironmentalScore(street, score);
        }

        // Weighted Incident Penalty with Severity & Decay
        let totalPenalty = 0;

        recentIncidents.forEach(inc => {
            const incidentTime = inc.reported_at || inc.timestamp;
            const daysAgo = (Date.now() - new Date(incidentTime)) / (1000 * 60 * 60 * 24);
            const decay = Math.max(0.25, 1 - (daysAgo / 35)); // Stronger decay after 35 days

            const severity = this.severityWeight[inc.severity] || 1.5;
            totalPenalty += 11 * severity * decay;
        });

        score -= Math.min(totalPenalty, 65);

        // Response Time Penalty
        const avgResponse = recentIncidents.reduce((sum, i) => sum + (i.response_time || 40), 0) / recentIncidents.length;
        score -= Math.min(avgResponse * 0.35, 22);

        return this.applyEnvironmentalScore(street, Math.max(5, Math.round(score)));
    }

    getRecentIncidents(streetId, incidents) {
        const thirtyFiveDaysAgo = Date.now() - (35 * 24 * 60 * 60 * 1000);
        return incidents.filter(inc => 
            inc.street_id === streetId && 
            new Date(inc.reported_at || inc.timestamp).getTime() >= thirtyFiveDaysAgo
        );
    }

    applyEnvironmentalScore(street, score) {
        if (street.lighting === "poor") score -= 16;
        else if (street.lighting === "moderate") score -= 7;

        if (!street.cctv) score -= 11;
        if (!street.neighborhood_watch) score -= 9;

        return Math.max(0, Math.min(100, score));
    }

    getSafetyLabel(score) {
        if (score >= 75) return { label: "Safe", color: "green" };
        if (score >= 50) return { label: "Moderate Risk", color: "yellow" };
        return { label: "High Risk", color: "red" };
    }
}

export const safetyEngine = new StreetSafetyEngine();