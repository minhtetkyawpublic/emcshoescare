export function adminPrice(value, language) {
  return new Intl.NumberFormat(language === "mm" ? "my-MM" : "en-US").format(Number(value || 0));
}

export function adminDateTime(value, language) {
  return new Intl.DateTimeFormat(language === "mm" ? "my-MM" : "en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
