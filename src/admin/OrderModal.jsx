import { useEffect, useRef } from "react";
import { Box, Image, MapPin, Phone, UserRound, X } from "lucide-react";
import { apiUrl } from "../api/client";
import { adminDateTime, adminPrice } from "./utils";

function OrderModal({ order, language, t, onClose }) {
  const packageName = language === "mm" ? order.package.nameMm : order.package.nameEn;
  const dialogRef = useRef(null);
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
  return (
    <div className="admin-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section ref={dialogRef} className="admin-modal order-viewer" role="dialog" aria-modal="true" aria-labelledby="order-modal-title" tabIndex="-1">
        <button className="admin-modal-close" onClick={onClose} aria-label={t.close}><X /></button>
        <span className="admin-section-kicker">{t.orderDetails}</span>
        <div className="order-modal-title"><div><h2 id="order-modal-title">{order.orderNumber}</h2><p>{adminDateTime(order.createdAt, language)}</p></div><em>{t.statusSubmitted}</em></div>
        <div className="admin-order-total"><span>{packageName}<small>{adminPrice(order.package.priceKs, language)} {t.ks}{order.pickupFeeKs > 0 ? ` + ${adminPrice(order.pickupFeeKs, language)} ${t.ks}` : ""}</small></span><strong>{adminPrice(order.totalPriceKs, language)} {t.ks}</strong></div>
        <div className="order-info-grid">
          <div><span><UserRound /></span><div><small>{t.customerDetails}</small><strong>{order.customer.name}</strong><p><Phone />{order.customer.phone}</p><p><MapPin />{order.customer.address}</p></div></div>
          <div><span><Box /></span><div><small>{t.handoverLabel}</small><strong>{order.handover === "pickup" ? t.pickup : t.dropoff}</strong><p>{order.pickupFeeKs > 0 ? `${t.pickupFeeLabel}: ${adminPrice(order.pickupFeeKs, language)} ${t.ks}` : t.submitNote}</p></div></div>
        </div>
        <div className="admin-order-notes"><small>{t.orderNotes}</small><p>{order.notes || "—"}</p></div>
        <div className="admin-order-photos"><small>{t.photosSection} · {order.photos.length}</small><div>{order.photos.map((photo, index) => <a key={photo.id} href={apiUrl(photo.url)} target="_blank" rel="noreferrer"><img src={apiUrl(photo.url)} alt={`${t.photosSection} ${index + 1}`} /></a>)}{order.photos.length === 0 && <span><Image />{t.noPhotos}</span>}</div></div>
      </section>
    </div>
  );
}

export default OrderModal;
