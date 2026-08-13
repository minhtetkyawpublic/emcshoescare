const DATABASE_NAME = "emc-order-drafts";
const STORE_NAME = "drafts";
const DRAFT_KEY = "current";

function openDatabase() {
  return new Promise((resolve, reject) => {
    if (!("indexedDB" in window)) {
      reject(new Error("IndexedDB is unavailable"));
      return;
    }
    const request = window.indexedDB.open(DATABASE_NAME, 1);
    request.onerror = () => reject(request.error);
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(STORE_NAME)) {
        request.result.createObjectStore(STORE_NAME);
      }
    };
    request.onsuccess = () => resolve(request.result);
  });
}

function transact(mode, action) {
  return openDatabase().then((database) => new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE_NAME, mode);
    const request = action(transaction.objectStore(STORE_NAME));
    let result;
    request.onerror = () => reject(request.error);
    request.onsuccess = () => { result = request.result; };
    transaction.oncomplete = () => {
      database.close();
      resolve(result);
    };
    transaction.onerror = () => {
      database.close();
      reject(transaction.error);
    };
  }));
}

export function loadOrderDraft() {
  return transact("readonly", (store) => store.get(DRAFT_KEY));
}

export function saveOrderDraft(draft) {
  return transact("readwrite", (store) => store.put({ ...draft, savedAt: new Date().toISOString() }, DRAFT_KEY));
}

export function clearOrderDraft() {
  return transact("readwrite", (store) => store.delete(DRAFT_KEY));
}

export function newRequestId() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (character) => {
    const random = Math.floor(Math.random() * 16);
    const value = character === "x" ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}
