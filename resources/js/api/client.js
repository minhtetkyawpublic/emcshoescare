import { apiBaseFromModuleUrl } from "./baseUrl";

class ApiError extends Error {
  constructor(code, message, status, fields = {}) {
    super(message);
    this.name = "ApiError";
    this.code = code;
    this.status = status;
    this.fields = fields;
  }
}

let csrfToken = "";
const API_BASE = apiBaseFromModuleUrl(import.meta.url);

export function apiUrl(path) {
  return `${API_BASE}${path}`;
}

async function request(path, options = {}) {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(apiUrl(path), {
    ...options,
    credentials: "include",
    headers: {
      ...(options.body && !isFormData ? { "Content-Type": "application/json" } : {}),
      ...(csrfToken && options.method && options.method !== "GET" ? { "X-CSRF-Token": csrfToken } : {}),
      ...options.headers,
    },
  });

  let payload;
  try {
    payload = await response.json();
  } catch {
    throw new ApiError("invalid_response", "The account service returned an invalid response.", response.status);
  }

  if (!response.ok || !payload.success) {
    const error = payload.error || {};
    if (response.status === 401) csrfToken = "";
    throw new ApiError(error.code || "request_failed", error.message || "Request failed.", response.status, error.fields);
  }

  if (payload.data?.csrfToken) csrfToken = payload.data.csrfToken;
  return payload.data;
}

export const accountApi = {
  async session() {
    const data = await request("/auth/session");
    return data;
  },
  register(details) {
    return request("/auth/register", { method: "POST", body: JSON.stringify(details) });
  },
  login(details) {
    return request("/auth/login", { method: "POST", body: JSON.stringify(details) });
  },
  updateProfile(details) {
    return request("/profile", { method: "PUT", body: JSON.stringify(details) });
  },
  packages() {
    return request("/packages");
  },
  createOrder(formData) {
    return request("/orders", { method: "POST", body: formData });
  },
  orders() {
    return request("/orders");
  },
  order(orderId) {
    return request(`/orders/${orderId}`);
  },
  markOrderSeen(orderId) {
    return request(`/orders/${orderId}/seen`, { method: "POST" });
  },
  pushConfiguration() {
    return request("/push/public-key");
  },
  savePushSubscription(subscription) {
    return request("/push/customer", { method: "POST", body: JSON.stringify(subscription) });
  },
  removePushSubscription(endpoint) {
    return request("/push/customer", { method: "DELETE", body: JSON.stringify({ endpoint }) });
  },
  async logout() {
    const data = await request("/auth/logout", { method: "POST" });
    csrfToken = "";
    return data;
  },
};

export { ApiError };
