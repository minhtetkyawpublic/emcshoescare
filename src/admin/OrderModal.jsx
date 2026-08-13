import { useEffect, useRef, useState } from "react";
import { ArrowRight, Box, CheckCircle2, Clock3, Image, MapPin, Phone, UserRound, X } from "lucide-react";
import { adminApi } from "../api/adminClient";
import { apiUrl } from "../api/client";
import { localizedStatusNote, statusLabel } from "../orderStatus";
import { adminDateTime, adminPrice } from "./utils";

function OrderModal({ order, language, t, onClose, onUpdated }) {
  const packageName = language === "mm" ? order.package.nameMm : order.package.nameEn;
  const dialogRef = useRef(null);
  const [nextStatus, setNextStatus] = useState(order.nextStatuses[0] || "");
  const [noteEn, setNoteEn] = useState("");
  const [noteMm, setNoteMm] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  useEffect(() => {
    const closeOnEscape = (event) => event.key === "Escape" && onClose();
    document.body.classList.add("modal-open");
    window.addEventListener("keydown", closeOnEscape);
    dialogRef.current?.focus();
    return () => {
      document.body.classList.remove("modal-open");
      window.removeEventListener("keydown", closeOnEscape);
    };
  }, [onClose]);

  const updateStatus = async (event) => {
    event.preventDefault();
    if (!nextStatus || (!noteEn.trim() && !noteMm.trim())) {
      setMessage(t.statusNoteRequired);
      return;
    }
    setBusy(true);
    setMessage("");
    try {
      const data = await adminApi.updateOrderStatus(order.id, { status: nextStatus, noteEn, noteMm });
      setNextStatus(data.order.nextStatuses[0] || "");
      setNoteEn("");
      setNoteMm("");
      setBusy(false);
      onUpdated(data.order);
    } catch (error) {
      setMessage(error?.code === "invalid_status_transition" ? t.invalidStatusTransition : error?.code === "status_note_required" ? t.statusNoteRequired : t.adminUnavailable);
      setBusy(false);
    }
  };

  return (
    <div className="admin-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section ref={dialogRef} className="admin-modal order-viewer" role="dialog" aria-modal="true" aria-labelledby="order-modal-title" tabIndex="-1">
        <button className="admin-modal-close" onClick={onClose} aria-label={t.close}><X /></button>
        <span className="admin-section-kicker">{t.orderDetails}</span>
        <div className="order-modal-title"><div><h2 id="order-modal-title">{order.orderNumber}</h2><p>{adminDateTime(order.createdAt, language)}</p></div><em className={`status-${order.status}`}>{statusLabel(order.status, t)}</em></div>
        <div className="admin-order-total"><span>{packageName}<small>{adminPrice(order.package.priceKs, language)} {t.ks}{order.pickupFeeKs > 0 ? ` + ${adminPrice(order.pickupFeeKs, language)} ${t.ks}` : ""}</small></span><strong>{adminPrice(order.totalPriceKs, language)} {t.ks}</strong></div>
        <div className="order-info-grid">
          <div><span><UserRound /></span><div><small>{t.customerDetails}</small><strong>{order.customer.name}</strong><p><Phone />{order.customer.phone}</p><p><MapPin />{order.customer.address}</p></div></div>
          <div><span><Box /></span><div><small>{t.handoverLabel}</small><strong>{order.handover === "pickup" ? t.pickup : t.dropoff}</strong><p>{order.pickupFeeKs > 0 ? `${t.pickupFeeLabel}: ${adminPrice(order.pickupFeeKs, language)} ${t.ks}` : t.submitNote}</p></div></div>
        </div>
        <div className="admin-order-notes"><small>{t.orderNotes}</small><p>{order.notes || "—"}</p></div>
        <div className="admin-order-photos"><small>{t.photosSection} · {order.photos.length}</small><div>{order.photos.map((photo, index) => <a key={photo.id} href={apiUrl(photo.url)} target="_blank" rel="noreferrer"><img src={apiUrl(photo.url)} alt={`${t.photosSection} ${index + 1}`} /></a>)}{order.photos.length === 0 && <span><Image />{t.noPhotos}</span>}</div></div>
        <section className="admin-status-section">
          <div className="admin-status-heading"><span><Clock3 /></span><div><small>{t.statusUpdate}</small><h3>{t.orderTimeline}</h3></div></div>
          <div className="admin-status-timeline">
            {[...order.history].reverse().map((entry, index) => <article key={entry.id} className={index === 0 ? "latest" : ""}><i><CheckCircle2 /></i><div><div><strong>{statusLabel(entry.status, t)}</strong><time>{adminDateTime(entry.createdAt, language)}</time></div><p>{localizedStatusNote(entry, language, t.noStatusNote)}</p><small>{entry.changedBy || t.systemUpdate}</small></div></article>)}
          </div>
        </section>
        {order.nextStatuses.length > 0 ? (
          <form className="admin-status-form" onSubmit={updateStatus}>
            <span className="admin-section-kicker">{t.updateStatus}</span>
            <label><span>{t.nextStatus}</span><select value={nextStatus} onChange={(event) => setNextStatus(event.target.value)}>{order.nextStatuses.map((status) => <option key={status} value={status}>{statusLabel(status, t)}</option>)}</select></label>
            <label><span>{t.noteEnglish}</span><textarea rows="2" value={noteEn} onChange={(event) => setNoteEn(event.target.value)} placeholder={t.noteEnglishPlaceholder} maxLength="1000" /></label>
            <label><span>{t.noteMyanmar}</span><textarea rows="2" value={noteMm} onChange={(event) => setNoteMm(event.target.value)} placeholder={t.noteMyanmarPlaceholder} maxLength="1500" /></label>
            <p>{t.noteLanguageHint}</p>
            {message && <p className="admin-error" role="alert">{message}</p>}
            <button className="admin-primary" disabled={busy}>{busy ? t.saving : t.updateStatus}<ArrowRight /></button>
          </form>
        ) : <p className="terminal-order"><CheckCircle2 />{t.terminalOrder}</p>}
      </section>
    </div>
  );
}

export default OrderModal;
