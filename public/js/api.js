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

      return await response.json();
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

  // Student-specific API endpoints
  student: {
    enrollments: {
      list: () => API.get('/student/api/enrollments'),
      get: (id) => API.get(`/student/api/enrollments/${id}`),
      create: (data) => API.post('/student/api/enrollments', data),
      update: (id, data) => API.put(`/student/api/enrollments/${id}`, data),
      delete: (id) => API.delete(`/student/api/enrollments/${id}`),
    },
    grades: {
      list: () => API.get('/student/api/grades'),
      get: (id) => API.get(`/student/api/grades/${id}`),
      summary: (params = {}) => {
        const query = new URLSearchParams(params).toString();
        return API.get(`/student/api/grades/summary${query ? `?${query}` : ''}`);
      },
    },
    documentRequests: {
      list: () => API.get('/student/api/document-requests'),
      get: (id) => API.get(`/student/api/document-requests/${id}`),
      create: (data) => API.post('/student/api/document-requests', data),
      delete: (id) => API.delete(`/student/api/document-requests/${id}`),
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
