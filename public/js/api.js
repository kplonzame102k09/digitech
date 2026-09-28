// API utility functions for student portal
const API = {
  async request(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    const config = {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers,
      },
      credentials: 'same-origin',
      ...options,
    };

    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
      }

      const payload = await response.json();

      // Pick up any id -> URL token the response carries, so records fetched
      // from a list endpoint can be used to build encrypted URLs later.
      try {
        window.DG?.learnRefs?.(payload);
      } catch (_) {
        /* refs are an optimisation; never fail a request over them */
      }

      return payload;
    } catch (error) {
      console.error('API request failed:', error);
      throw error;
    }
  },

  async get(url) {
    return this.request(url, { method: 'GET' });
  },

  async post(url, data) {
    return this.request(url, {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  async put(url, data) {
    return this.request(url, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  async delete(url) {
    return this.request(url, { method: 'DELETE' });
  },

  // Admin-specific API endpoints
  admin: {
    profile: {
      get: () => API.get('/admin/api/profile'),
      update: (data) => API.put('/admin/api/profile', data),
    },
  },

  // Phase 1 server-side analytics (authoritative; local JS math is fallback)
  analytics: {
    attendance: () => API.get('/api/portal/analytics/attendance'),
    gradeSummary: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return API.get(`/api/portal/analytics/grades/summary${query ? `?${query}` : ''}`);
    },
    gradePreview: (data) => API.post('/api/portal/analytics/grades/preview', data),
  },

  // Student-specific API endpoints
  student: {
    enrollments: {
      list: () => API.get('/student/api/enrollments'),
      get: (id) => API.get(`/student/api/enrollments/${DG.ref(id)}`),
      create: (data) => API.post('/student/api/enrollments', data),
      update: (id, data) => API.put(`/student/api/enrollments/${DG.ref(id)}`, data),
      delete: (id) => API.delete(`/student/api/enrollments/${DG.ref(id)}`),
    },
    grades: {
      list: () => API.get('/student/api/grades'),
      get: (id) => API.get(`/student/api/grades/${DG.ref(id)}`),
      summary: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return API.get(`/student/api/grades/summary${query ? `?${query}` : ''}`);
      },
    },
    documentRequests: {
      list: () => API.get('/student/api/document-requests'),
      get: (id) => API.get(`/student/api/document-requests/${DG.ref(id)}`),
      create: (data) => API.post('/student/api/document-requests', data),
      delete: (id) => API.delete(`/student/api/document-requests/${DG.ref(id)}`),
    },
    profile: {
      get: () => API.get('/student/api/profile'),
      update: (data) => API.put('/student/api/profile', data),
      uploadPhoto: (formData) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        return fetch('/student/api/profile/photo', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: formData,
        }).then(response => {
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          return response.json();
        });
      },
    },
  },
};

window.API = API;
