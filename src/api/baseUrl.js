export function apiBaseFromModuleUrl(moduleUrl) {
  const modulePath = new URL(moduleUrl).pathname;
  const assetsMarker = "/assets/";
  const assetsIndex = modulePath.lastIndexOf(assetsMarker);
  const deploymentPath = assetsIndex >= 0 ? modulePath.slice(0, assetsIndex + 1) : "/";

  return `${deploymentPath}api`.replace(/\/{2,}/g, "/");
}
