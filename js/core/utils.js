// js/core/utils.js
export function getCurrentTimestamp() {
    return new Date().toISOString();
}

export function formatDate(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleDateString('en-GB', { 
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' 
    });
}

export function getSafetyColor(score) {
    if (score >= 75) return "green";
    if (score >= 50) return "yellow";
    return "red";
}

export function calculateDistance(x1, y1, x2, y2) {
    return Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
}