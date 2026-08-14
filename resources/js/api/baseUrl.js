export function appBaseFromModuleUrl(moduleUrl) {
  const modulePath = new URL(moduleUrl).pathname;
  const assetsMarker = "/build/assets/";
  const assetsIndex = modulePath.lastIndexOf(assetsMarker);
  const deploymentPath = assetsIndex >= 0 ? modulePath.slice(0, assetsIndex + 1) : "/";

  return deploymentPath === "/" ? "" : deploymentPath.replace(/\/$/, "");
}

export function apiBaseFromModuleUrl(moduleUrl) {
  return `${appBaseFromModuleUrl(moduleUrl)}/api`;
}
