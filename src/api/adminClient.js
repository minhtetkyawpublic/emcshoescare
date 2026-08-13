import { ApiError, apiUrl } from "./client";

let adminCsrfToken = "";

async function adminRequest(path, options = {}) {
  const response = await fetch(apiUrl(path), {
    ...options,
    credentials: "include",
    headers: {
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(adminCsrfToken && options.method && options.method !== "GET" ? { "X-CSRF-Token": adminCsrfToken } : {}),
      ...options.headers,
    },
  });
  let payload;
  try {
    payload = await response.json();
  } catch {
    throw new ApiError("invalid_response", "The admin service returned an invalid response.", response.status);
  }
  if (!response.ok || !payload.success) {
    const error = payload.error || {};
    if (response.status === 401) adminCsrfToken = "";
    throw new ApiError(error.code || "request_failed", error.message || "Request failed.", response.status, error.fields);
  }
  if (payload.data?.csrfToken) adminCsrfToken = payload.data.csrfToken;
  return payload.data;
}

export const adminApi = {
  async session() {
    const data = await adminRequest("/admin/auth/session");
    if (!data.authenticated) adminCsrfToken = "";
    return data;
  },
  login(credentials) {
    return adminRequest("/admin/auth/login", { method: "POST", body: JSON.stringify(credentials) });
  },
  async logout() {
    const data = await adminRequest("/admin/auth/logout", { method: "POST" });
    adminCsrfToken = "";
    return data;
  },
  packages() {
    return adminRequest("/admin/packages");
  },
  createPackage(details) {
    return adminRequest("/admin/packages", { method: "POST", body: JSON.stringify(details) });
  },
  updatePackage(id, details) {
    return adminRequest(`/admin/packages/${id}`, { method: "PUT", body: JSON.stringify(details) });
  },
  archivePackage(id) {
    return adminRequest(`/admin/packages/${id}`, { method: "DELETE" });
  },
  settings() {
    return adminRequest("/admin/settings");
  },
  updateSettings(details) {
    return adminRequest("/admin/settings", { method: "PUT", body: JSON.stringify(details) });
  },
  orders() {
    return adminRequest("/admin/orders");
  },
  order(id) {
    return adminRequest(`/admin/orders/${id}`);
  },
  updateOrderStatus(id, details) {
    return adminRequest(`/admin/orders/${id}/status`, { method: "PUT", body: JSON.stringify(details) });
  },
};
