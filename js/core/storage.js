// js/core/storage.js
const DB_PREFIX = "ssis_";

export function get(key) {
    const data = localStorage.getItem(DB_PREFIX + key);
    return data ? JSON.parse(data) : null;
}

export function set(key, value) {
    localStorage.setItem(DB_PREFIX + key, JSON.stringify(value));
    console.log(`💾 Saved: ${key}`);
}