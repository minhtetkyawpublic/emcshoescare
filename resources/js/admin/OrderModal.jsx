import { useEffect, useRef, useState } from "react";
import { ArrowLeft, ArrowRight, Box, CheckCircle2, Clock3, Image, MapPin, Phone, UserRound, X } from "lucide-react";
import { adminApi } from "../api/adminClient";
import { apiUrl } from "../api/client";
import { localizedStatusNote, statusLabel } from "../orderStatus";
import { adminDateTime, adminPrice } from "./utils";
import useDialogFocus from "../components/useDialogFocus";

function OrderModal({ order, language, t, onClose, onUpdated }) {
  const packageName = order.package.name;
  const dialogRef = useRef(null);
  const [nextStatus, setNextStatus] = useState(order.nextStatuses[0] || "");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [activePhotoIndex, setActivePhotoIndex] = useState(null);
  useDialogFocus(dialogRef, onClose, busy, "", activePhotoIndex === null);

  const updateStatus = async (event) => {
    event.preventDefault();
    if (!nextStatus) {
      setMessage(t.invalidStatusTransition);
      return;
    }
    setBusy(true);
    setMessage("");
    try {
      const data = await adminApi.updateOrderStatus(order.id, { status: nextStatus, note });
      setNextStatus(data.order.nextStatuses[0] || "");
      setNote("");
      setBusy(false);
      onUpdated(data.order, data.notification);
    } catch (error) {
      setMessage(error?.code === "invalid_status_transition" ? t.invalidStatusTransition : t.adminUnavailable);
      setBusy(false);
    }
  };

  return (
    <div className="admin-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section ref={dialogRef} className="admin-modal order-viewer" role="dialog" aria-modal="true" aria-labelledby="order-modal-title" tabIndex="-1">
        <button className="admin-modal-close" onClick={onClose} aria-label={t.close}><X /></button>
        <span className="admin-section-kicker">{t.orderDetails}</span>
        <div className="order-modal-title"><div><h2 id="order-modal-title">{order.orderNumber}</h2><p>{adminDateTime(order.createdAt, language)}</p></div><em className={`status-${order.status}`}>{statusLabel(order.status, t)}</em></div>
        <div className="admin-order-total"><span>{packageName}<small>{adminPrice(order.package.priceKs, language)} {t.ks}</small></span><strong>{adminPrice(order.totalPriceKs, language)} {t.ks}</strong></div>
        <div className="order-info-grid">
          <div><span><UserRound /></span><div><small>{t.customerDetails}</small><strong>{order.customer.name}</strong><p><Phone />{order.customer.phone}</p><p><MapPin />{order.customer.address}</p></div></div>
          <div><span><Box /></span><div><small>{t.handoverLabel}</small><strong>{order.handover === "pickup" ? t.pickup : t.dropoff}</strong><p>{order.handover === "pickup" ? t.pickupBody : t.dropoffBody}</p></div></div>
        </div>
        <div className="admin-order-notes"><small>{t.orderNotes}</small><p>{order.notes || "—"}</p></div>
        <div className="admin-order-photos"><small>{t.photosSection} · {order.photos.length}</small><div>{order.photos.map((photo, index) => <button type="button" key={photo.id} onClick={() => setActivePhotoIndex(index)} aria-label={`${t.viewPhoto} ${index + 1}`}><img src={apiUrl(photo.url)} alt={`${t.photosSection} ${index + 1}`} /></button>)}{order.photos.length === 0 && <span><Image />{t.noPhotos}</span>}</div></div>
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
            <label><span>{t.customerNoteOptional}</span><textarea rows="3" value={note} onChange={(event) => setNote(event.target.value)} placeholder={t.customerNotePlaceholder} maxLength="1500" /></label>
            <p>{t.customerNoteHint}</p>
            {message && <p className="admin-error" role="alert">{message}</p>}
            <button className="admin-primary" disabled={busy}>{busy ? t.saving : t.updateStatus}<ArrowRight /></button>
          </form>
        ) : <p className="terminal-order"><CheckCircle2 />{t.terminalOrder}</p>}
      </section>
      {activePhotoIndex !== null && <PhotoViewer photos={order.photos} activeIndex={activePhotoIndex} setActiveIndex={setActivePhotoIndex} t={t} />}
    </div>
  );
}

function PhotoViewer({ photos, activeIndex, setActiveIndex, t }) {
  const viewerRef = useRef(null);
  const touchStart = useRef(null);
  const close = () => setActiveIndex(null);
  const previous = () => setActiveIndex((activeIndex - 1 + photos.length) % photos.length);
  const next = () => setActiveIndex((activeIndex + 1) % photos.length);
  useDialogFocus(viewerRef, close, false, ".photo-viewer-close");

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === "ArrowLeft" && photos.length > 1) {
        event.preventDefault();
        previous();
      } else if (event.key === "ArrowRight" && photos.length > 1) {
        event.preventDefault();
        next();
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  });

  const finishSwipe = (event) => {
    if (touchStart.current === null || photos.length < 2) return;
    const distance = event.changedTouches[0].clientX - touchStart.current;
    touchStart.current = null;
    if (Math.abs(distance) < 45) return;
    if (distance > 0) previous(); else next();
  };

  const photo = photos[activeIndex];
  return (
    <div className="photo-viewer-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && close()}>
      <section ref={viewerRef} className="photo-viewer" role="dialog" aria-modal="true" aria-label={t.photoViewer} tabIndex="-1">
        <header><strong>{t.photoViewer}</strong><span>{activeIndex + 1} / {photos.length}</span><button type="button" className="photo-viewer-close" onClick={close} aria-label={t.close}><X /></button></header>
        <div className="photo-viewer-stage" onTouchStart={(event) => { touchStart.current = event.touches[0].clientX; }} onTouchEnd={finishSwipe}>
          <img src={apiUrl(photo.url)} alt={`${t.photosSection} ${activeIndex + 1}`} />
          {photos.length > 1 && <><button type="button" className="photo-viewer-previous" onClick={previous} aria-label={t.previousPhoto}><ArrowLeft /></button><button type="button" className="photo-viewer-next" onClick={next} aria-label={t.nextPhoto}><ArrowRight /></button></>}
        </div>
        {photos.length > 1 && <div className="photo-viewer-thumbnails">{photos.map((item, index) => <button type="button" className={index === activeIndex ? "active" : ""} key={item.id} onClick={() => setActiveIndex(index)} aria-label={`${t.viewPhoto} ${index + 1}`} aria-current={index === activeIndex ? "true" : undefined}><img src={apiUrl(item.url)} alt="" /></button>)}</div>}
      </section>
    </div>
  );
}

export default OrderModal;
