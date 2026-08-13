import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import AdminApp from "./AdminApp";
import "./styles.css";
import "./admin.css";

const isAdminRoute = /\/admin\/?$/.test(window.location.pathname);

createRoot(document.getElementById("root")).render(
  <StrictMode>
    {isAdminRoute ? <AdminApp /> : <App />}
  </StrictMode>,
);

if ("serviceWorker" in navigator && import.meta.env.PROD) {
  const serviceWorkerUrl = new URL(/* @vite-ignore */ "../sw.js", import.meta.url).pathname;
  window.addEventListener("load", () => navigator.serviceWorker.register(serviceWorkerUrl));
}
