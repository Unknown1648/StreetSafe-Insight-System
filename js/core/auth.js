// js/core/auth.js
class AuthManager {
    constructor() {
        this.user = null;
        this.isAuthenticated = false;
    }

    async fetchJson(url, options = {}) {
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

    async checkAuth() {
        try {
            const data = await this.fetchJson('api/auth.php?action=check');
            this.user = data.user;
            this.isAuthenticated = true;
            return true;
        } catch (error) {
            this.user = null;
            this.isAuthenticated = false;
            return false;
        }
    }

    async login(role, identifier, password) {
        const data = await this.fetchJson('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'login',
                role,
                identifier,
                password
            })
        });
        this.user = data.user;
        this.isAuthenticated = true;
        return data.user;
    }

    async registerCommunity(payload) {
        const data = await this.fetchJson('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'register_community',
                ...payload
            })
        });
        this.user = data.user;
        this.isAuthenticated = true;
        return data.user;
    }

    async requireAuth(allowedRoles = null, redirectTo = 'login.html') {
        const authenticated = await this.checkAuth();
        if (!authenticated) {
            window.location.href = redirectTo;
            return false;
        }

        if (allowedRoles && !allowedRoles.includes(this.user.role)) {
            window.location.href = redirectTo;
            return false;
        }

        return true;
    }

    async logout() {
        await this.fetchJson('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'logout' })
        });
        this.user = null;
        this.isAuthenticated = false;
        window.location.href = 'login.html';
    }
}

export const authManager = new AuthManager();
