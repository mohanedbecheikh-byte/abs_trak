const API = {
  async request(url, options = {}) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload.error || `HTTP ${response.status}`);
    }
    return payload;
  },

  getModules() {
    return this.request('/api/get_modules.php');
  },

  getAttendance(moduleId) {
    return this.request(`/api/get_attendance.php?module_id=${encodeURIComponent(moduleId)}`);
  },

  toggleAttendance(moduleId, weekId, status) {
    return this.request('/api/toggle_attendance.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.APP_CONFIG?.csrfToken || ''
      },
      body: JSON.stringify({
        module_id: moduleId,
        week_id: weekId,
        status
      })
    });
  },

  getStats() {
    return this.request('/api/get_stats.php');
  }
};

