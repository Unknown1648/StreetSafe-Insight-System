// js/core/event-bus.js
const events = {};

export function on(event, callback) {
    events[event] = events[event] || [];
    events[event].push(callback);
}

export function emit(event, data) {
    (events[event] || []).forEach(callback => callback(data));
}