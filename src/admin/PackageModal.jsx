import { useEffect, useRef, useState } from "react";
import { ArrowRight, Check, X } from "lucide-react";
import { adminApi } from "../api/adminClient";

function PackageModal({ packageItem, t, onClose, onSaved }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const editing = Boolean(packageItem);
  const dialogRef = useRef(null);

  useEffect(() => {
    const closeOnEscape = (event) => event.key === "Escape" && !busy && onClose();
    document.body.classList.add("modal-open");
    window.addEventListener("keydown", closeOnEscape);
    dialogRef.current?.focus();
    return () => {
      document.body.classList.remove("modal-open");
      window.removeEventListener("keydown", closeOnEscape);
    };
  }, [busy, onClose]);

  const submit = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const details = {
      nameEn: form.get("nameEn"),
      nameMm: form.get("nameMm"),
      descriptionEn: form.get("descriptionEn"),
      descriptionMm: form.get("descriptionMm"),
      priceKs: Number(form.get("priceKs")),
      sortOrder: Number(form.get("sortOrder")),
      active: form.get("active") === "on",
    };
    setBusy(true);
    setError("");
    try {
      if (editing) await adminApi.updatePackage(packageItem.id, details);
      else await adminApi.createPackage(details);
      onSaved();
    } catch {
      setError(t.adminUnavailable);
      setBusy(false);
    }
  };

  return (
    <div className="admin-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !busy && onClose()}>
      <section ref={dialogRef} className="admin-modal package-editor" role="dialog" aria-modal="true" aria-labelledby="package-modal-title" tabIndex="-1">
        <button className="admin-modal-close" onClick={onClose} disabled={busy} aria-label={t.close}><X /></button>
        <span className="admin-section-kicker">{t.adminPackages}</span>
        <h2 id="package-modal-title">{editing ? t.editPackage : t.createPackage}</h2>
        <form onSubmit={submit}>
          <div className="admin-field-grid">
            <label><span>{t.packageNameEn}</span><input name="nameEn" defaultValue={packageItem?.nameEn || ""} required /></label>
            <label><span>{t.packageNameMm}</span><input name="nameMm" defaultValue={packageItem?.nameMm || ""} required /></label>
            <label className="wide"><span>{t.descriptionEn}</span><textarea name="descriptionEn" rows="2" defaultValue={packageItem?.descriptionEn || ""} /></label>
            <label className="wide"><span>{t.descriptionMm}</span><textarea name="descriptionMm" rows="2" defaultValue={packageItem?.descriptionMm || ""} /></label>
            <label><span>{t.priceKs}</span><input name="priceKs" type="number" min="0" step="500" defaultValue={packageItem?.priceKs ?? 0} required /></label>
            <label><span>{t.sortOrder}</span><input name="sortOrder" type="number" min="0" defaultValue={packageItem?.sortOrder ?? 10} required /></label>
          </div>
          <label className="admin-check"><input name="active" type="checkbox" defaultChecked={packageItem?.active ?? true} aria-label={t.activePackage} /><span><Check />{t.activePackage}</span></label>
          {error && <p className="admin-error" role="alert">{error}</p>}
          <div className="admin-modal-actions"><button type="button" className="admin-secondary" onClick={onClose} disabled={busy}>{t.close}</button><button className="admin-primary" disabled={busy}>{busy ? t.saving : t.savePackage}<ArrowRight /></button></div>
        </form>
      </section>
    </div>
  );
}

export default PackageModal;
