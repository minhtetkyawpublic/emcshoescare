function decodeVapidKey(value) {
  const padding = "=".repeat((4 - (value.length % 4)) % 4);
  const binary = atob((value + padding).replace(/-/g, "+").replace(/_/g, "/"));
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

export function pushSupported() {
  return import.meta.env.PROD && window.isSecureContext && "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
}

export async function pushState() {
  if (!pushSupported()) return "unsupported";
  if (Notification.permission === "denied") return "denied";
  const registration = await navigator.serviceWorker.ready;
  return (await registration.pushManager.getSubscription()) ? "enabled" : "disabled";
}

export async function enablePush(publicKey, saveSubscription) {
  if (!pushSupported()) throw new Error("push_unsupported");
  const permission = await Notification.requestPermission();
  if (permission !== "granted") throw new Error(permission === "denied" ? "push_denied" : "push_cancelled");
  const registration = await navigator.serviceWorker.ready;
  let subscription = await registration.pushManager.getSubscription();
  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: decodeVapidKey(publicKey),
    });
  }
  const payload = subscription.toJSON();
  await saveSubscription({ ...payload, contentEncoding: "aes128gcm" });
  return "enabled";
}

export async function disablePush(removeSubscription) {
  if (!pushSupported()) return "unsupported";
  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription();
  if (subscription) {
    await removeSubscription(subscription.endpoint);
    await subscription.unsubscribe();
  }
  return "disabled";
}
