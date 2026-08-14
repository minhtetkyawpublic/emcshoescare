export const statusTranslationKeys = {
  submitted: "statusSubmitted",
  confirmed: "statusConfirmed",
  pickup_scheduled: "statusPickupScheduled",
  rider_on_way: "statusRiderOnWay",
  shoes_received: "statusShoesReceived",
  repairing: "statusRepairing",
  ready: "statusReady",
  done: "statusDone",
  cancelled: "statusCancelled",
};

export function statusLabel(status, t) {
  return t[statusTranslationKeys[status]] || status;
}

export function localizedStatusNote(entry, language, fallback) {
  const primary = language === "mm" ? entry.noteMm : entry.noteEn;
  const secondary = language === "mm" ? entry.noteEn : entry.noteMm;
  return primary || secondary || fallback;
}
