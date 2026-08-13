import { useEffect, useRef, useState } from "react";
import { Download, RefreshCw, Share2, WifiOff, X } from "lucide-react";

function InstallGuide({ open, onClose, onInstall, canPrompt, installed, t }) {
  const closeButton = useRef(null);
  const userAgent = navigator.userAgent || "";
  const isIos = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;

  useEffect(() => {
    if (!open) return undefined;
    closeButton.current?.focus();
    const closeOnEscape = (event) => event.key === "Escape" && onClose();
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [open, onClose]);

  if (!open) return null;
  return (
    <div className="install-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section className="install-guide" role="dialog" aria-modal="true" aria-labelledby="install-title">
        <button ref={closeButton} className="install-close" type="button" onClick={onClose} aria-label={t.close}><X /></button>
        <span className="install-mark"><img src="./icon-192.png" alt="" /></span>
        <h2 id="install-title">{installed ? t.alreadyInstalled : t.installTitle}</h2>
        <p>{installed ? t.alreadyInstalledBody : t.installIntro}</p>
        {!installed && canPrompt && <button className="primary-button install-now" type="button" onClick={onInstall}><Download size={18} />{t.installNow}</button>}
        {!installed && !canPrompt && (
          <ol className="install-steps">
            <li><span>{isIos ? <Share2 /> : "1"}</span><p><strong>{isIos ? t.iosStepOneTitle : t.androidStepOneTitle}</strong><small>{isIos ? t.iosStepOneBody : t.androidStepOneBody}</small></p></li>
            <li><span>2</span><p><strong>{t.installStepTwoTitle}</strong><small>{t.installStepTwoBody}</small></p></li>
          </ol>
        )}
        <small className="install-footnote">{t.installFootnote}</small>
      </section>
    </div>
  );
}

export function NetworkSignals({ t }) {
  const [online, setOnline] = useState(navigator.onLine);
  const [updateWorker, setUpdateWorker] = useState(null);
  useEffect(() => {
    const goOnline = () => setOnline(true);
    const goOffline = () => setOnline(false);
    const showUpdate = (event) => setUpdateWorker(event.detail || true);
    window.addEventListener("online", goOnline);
    window.addEventListener("offline", goOffline);
    window.addEventListener("emc-update-available", showUpdate);
    return () => {
      window.removeEventListener("online", goOnline);
      window.removeEventListener("offline", goOffline);
      window.removeEventListener("emc-update-available", showUpdate);
    };
  }, []);
  const applyUpdate = () => {
    if (updateWorker?.postMessage) {
      navigator.serviceWorker.addEventListener("controllerchange", () => window.location.reload(), { once: true });
      updateWorker.postMessage("SKIP_WAITING");
    } else {
      window.location.reload();
    }
  };
  if (!online) return <div className="network-signal offline" role="status"><WifiOff size={17} />{t.offlineNotice}</div>;
  if (updateWorker) return <button className="network-signal update" type="button" onClick={applyUpdate}><RefreshCw size={17} />{t.updateReady}</button>;
  return null;
}

export default InstallGuide;
