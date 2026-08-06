// js/services/analytics-service.js
/**
 * Analytics Service
 * Fetches insights, reports, and aggregated statistics from PHP API
 */

const API_BASE = 'api';

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'include',
        ...options
    });
    const data = await response.json();
    if (!response.ok) {
        throw new Error(data.error || `HTTP error! status: ${response.status}`);
    }
    return data;
}

/**
 * Get dashboard statistics
 */
export async function getDashboardStats() {
    try {
        const response = await fetch(`${API_BASE}/analytics.php?action=dashboard`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success && data.stats) {
            return data.stats;
        }

        console.error(data.error);
        return null;

    } catch (error) {
        console.error(error);
        return null;
    }
}

/**
 * Get detailed street statistics
 */
export async function getStreetDetails(streetId) {
    try {
        const data = await fetchJson(`${API_BASE}/analytics.php?action=street_details&street_id=${streetId}`);
        if (data.success && data.details) {
            console.log(`📍 Street details loaded for street ${streetId}`);
            return data.details;
        } else {
            console.error("Failed to load street details:", data.error);
            return null;
        }
    } catch (error) {
        console.error("❌ Error fetching street details:", error);
        return null;
    }
}

export async function getStreetReports(groupBy = 'type', startDate = '', endDate = '') {
    try {
        const params = new URLSearchParams({ action: 'street_reports', group_by: groupBy });
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        const data = await fetchJson(`${API_BASE}/analytics.php?${params.toString()}`);
        if (data.success && data.report) {
            return data.report;
        }

        console.error("Failed to load street reports:", data.error);
        return { group_by: groupBy, rows: [] };
    } catch (error) {
        console.error("❌ Error fetching street reports:", error);
        return { group_by: groupBy, rows: [] };
    }
}

/**
 * Get reports/insights
 */
export async function getReports(filters = {}) {
    try {
        let url = `${API_BASE}/reports.php`;
        const params = new URLSearchParams();
        
        if (filters.street_id) params.append('street_id', filters.street_id);
        if (filters.type) params.append('type', filters.type);
        if (filters.limit) params.append('limit', filters.limit);
        
        if (params.toString()) {
            url += '?' + params.toString();
        }

        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const data = await response.json();
        if (data.success && data.reports) {
            console.log("📄 Reports loaded");
            return data.reports;
        } else {
            console.error("Failed to load reports:", data.error);
            return [];
        }
    } catch (error) {
        console.error("❌ Error fetching reports:", error);
        return [];
    }
}

/**
 * Create a new report
 */
export async function createReport(reportData) {
    try {
        const payload = {
            title: reportData.title,
            description: reportData.description || '',
            report_type: reportData.report_type || 'custom',
            street_id: reportData.street_id || null,
            incidents_count: reportData.incidents_count || 0,
            avg_response_time: reportData.avg_response_time || 0,
            safety_score: reportData.safety_score || 50,
            report_date: reportData.report_date || new Date().toISOString().split('T')[0]
        };

        const response = await fetch(`${API_BASE}/reports.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const data = await response.json();
        if (data.success) {
            console.log("✅ Report created successfully:", data.id);
            return data;
        } else {
            console.error("Failed to create report:", data.error);
            return null;
        }
    } catch (error) {
        console.error("❌ Error creating report:", error);
        return null;
    }
}
