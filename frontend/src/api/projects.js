import api from './axios'

export const projectsApi = {
  index: (params = {}) => api.get('/projects', { params }),
  show: (id) => api.get(`/projects/${id}`),
  store: (data) => api.post('/projects', data),
  update: (id, data) => api.put(`/projects/${id}`, data),
  destroy: (id) => api.delete(`/projects/${id}`),
}
