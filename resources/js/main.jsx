import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import AdminApp from "./AdminApp";
import "./styles.css";
import "./admin.css";
import { appBaseFromModuleUrl } from "./api/baseUrl";

const isAdminRoute = /\/admin\/?$/.test(window.location.pathname);

createRoot(document.getElementById("root")).render(
  <StrictMode>
    {isAdminRoute ? <AdminApp /> : <App />}
  </StrictMode>,
);

if ("serviceWorker" in navigator && import.meta.env.PROD) {
  const serviceWorkerUrl = `${appBaseFromModuleUrl(import.meta.url)}/sw.js`;
  window.addEventListener("load", () => {
    navigator.serviceWorker.register(serviceWorkerUrl).then((registration) => {
      const watchWorker = (worker) => worker?.addEventListener("statechange", () => {
        if (worker.state === "installed" && navigator.serviceWorker.controller) {
          window.dispatchEvent(new CustomEvent("emc-update-available", { detail: worker }));
        }
      });
      watchWorker(registration.installing);
      registration.addEventListener("updatefound", () => watchWorker(registration.installing));
    }).catch(() => {});
  });
}
