import axios from 'axios';

export interface ApiErrorPayload {
  message: string;
  errors?: Record<string, string[]>;
}

const client = axios.create({
  // Production sets VITE_API_BASE_URL at build time. With it unset (dev / a phone on the same WiFi
  // opening http://<pc-ip>:4000) the API is assumed to sit on the SAME host, port 8100 - a hardcoded
  // 127.0.0.1 would point a phone at itself.
  baseURL: import.meta.env.VITE_API_BASE_URL || `${window.location.protocol}//${window.location.hostname}:8100/api`,
  headers: {
    Accept: 'application/json',
  },
});

// Attach the Sanctum bearer token (if the user is logged in) to every request.
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// A 401 means the token is missing/expired — clear it and let the app react
// (e.g. redirect to login) instead of leaving a dead token in storage.
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.dispatchEvent(new Event('auth:unauthorized'));
    }
    return Promise.reject(error);
  },
);

export default client;
