import { useCallback, useEffect, useRef, useState } from "react";
import { Activity, BarChart3, CalendarDays, CheckCircle2, CircleDollarSign, SlidersHorizontal, X } from "lucide-react";
import { adminApi } from "../api/adminClient";
import useDialogFocus from "../components/useDialogFocus";
import { statusLabel } from "../orderStatus";
import { adminPrice } from "./utils";

const isoDate = (date) => {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
};

export default function ReportsPanel({ packages, language, refreshKey, t, onError }) {
  const today = isoDate(new Date());
  const initialFrom = isoDate(new Date(Date.now() - 29 * 86400000));
  const [filters, setFilters] = useState({ from: initialFrom, to: today, packageId: "" });
  const [draft, setDraft] = useState(filters);
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filtersOpen, setFiltersOpen] = useState(false);

  const load = useCallback(async (nextFilters) => {
    setLoading(true);
    try {
      const data = await adminApi.report(nextFilters);
      setReport(data);
      onError("");
    } catch (error) {
      onError(error?.code === "report_period_too_large" ? t.reportRangeError : error?.code === "invalid_report_period" ? t.reportDateError : t.adminUnavailable);
    } finally {
      setLoading(false);
    }
  }, [onError, t.adminUnavailable, t.reportDateError, t.reportRangeError]);

  useEffect(() => { load(filters); }, [filters, load, refreshKey]);
  const change = (event) => setDraft((current) => ({ ...current, [event.target.name]: event.target.value }));
  const submit = (event) => {
    event.preventDefault();
    if (draft.from > draft.to) {
      onError(t.reportDateError);
      return;
    }
    setFilters(draft);
    setFiltersOpen(false);
  };
  const preset = (days) => {
    const next = { ...draft, from: isoDate(new Date(Date.now() - (days - 1) * 86400000)), to: today };
    setDraft(next);
  };

  const maximum = Math.max(1, ...(report?.byDay || []).map((item) => item.orderCount));
  const selectedPackage = packages.find((item) => String(item.id) === String(filters.packageId));
  return <>
    <div className="reports-screen">
      <section className="admin-panel report-controls">
        <div><span className="admin-section-kicker">{t.adminReports}</span><h2>{t.performanceReport}</h2><p>{t.reportHelp}</p></div>
        <div className="report-current-filter"><span><CalendarDays /><strong>{filters.from} – {filters.to}</strong><small>{selectedPackage?.name || t.allPackages}</small></span><button type="button" onClick={() => { setDraft(filters); setFiltersOpen(true); }}><SlidersHorizontal />{t.filters}</button></div>
      </section>

      {report && <>
        <section className="report-summary">
          <article><span><BarChart3 /></span><div><small>{t.totalOrders}</small><strong>{report.summary.totalOrders}</strong></div></article>
          <article><span><CircleDollarSign /></span><div><small>{t.totalOrderValue}</small><strong>{adminPrice(report.summary.revenueKs, language)} <em>{t.ks}</em></strong></div></article>
          <article><span><CheckCircle2 /></span><div><small>{t.completedOrders}</small><strong>{report.summary.completedOrders}</strong></div></article>
          <article><span><Activity /></span><div><small>{t.activeOrders}</small><strong>{report.summary.activeOrders}</strong></div></article>
        </section>

        <section className="report-grid">
          <article className="admin-panel daily-report"><div className="report-heading"><div><span className="admin-section-kicker">{t.trend}</span><h3>{t.ordersByDay}</h3></div><CalendarDays /></div>{report.byDay.length ? <div className="daily-chart">{report.byDay.map((item) => <div key={item.date}><span style={{ height: `${Math.max(7, item.orderCount / maximum * 100)}%` }} title={`${item.date}: ${item.orderCount}`} /><small>{item.date.slice(5)}</small></div>)}</div> : <p className="report-empty">{t.noReportData}</p>}</article>
          <article className="admin-panel report-list"><div className="report-heading"><div><span className="admin-section-kicker">{t.breakdown}</span><h3>{t.ordersByStatus}</h3></div></div>{report.byStatus.length ? report.byStatus.map((item) => <div key={item.status}><span><em className={`status-${item.status}`}>{statusLabel(item.status, t)}</em><small>{adminPrice(item.revenueKs, language)} {t.ks}</small></span><strong>{item.orderCount}</strong></div>) : <p className="report-empty">{t.noReportData}</p>}</article>
          <article className="admin-panel report-list package-report"><div className="report-heading"><div><span className="admin-section-kicker">{t.breakdown}</span><h3>{t.ordersByPackage}</h3></div></div>{report.byPackage.length ? report.byPackage.map((item) => <div key={`${item.packageId}-${item.packageName}`}><span><b>{item.packageName}</b><small>{adminPrice(item.revenueKs, language)} {t.ks}</small></span><strong>{item.orderCount}</strong></div>) : <p className="report-empty">{t.noReportData}</p>}</article>
        </section>
      </>}
    </div>
    {filtersOpen && <ReportFilterModal draft={draft} packages={packages} loading={loading} t={t} onChange={change} onSubmit={submit} onPreset={preset} onClose={() => setFiltersOpen(false)} />}
  </>;
}

function ReportFilterModal({ draft, packages, loading, t, onChange, onSubmit, onPreset, onClose }) {
  const dialogRef = useRef(null);
  useDialogFocus(dialogRef, onClose, loading, "input[name='from']");

  return (
    <div className="order-filter-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !loading && onClose()}>
      <section ref={dialogRef} className="order-filter-dialog report-filter-dialog" role="dialog" aria-modal="true" aria-labelledby="report-filter-title" tabIndex="-1">
        <header><div><span className="admin-section-kicker">{t.adminReports}</span><h2 id="report-filter-title">{t.filterReports}</h2><p>{t.filterReportsHelp}</p></div><button type="button" onClick={onClose} disabled={loading} aria-label={t.close}><X /></button></header>
        <form className="order-filter-form report-filter-form" onSubmit={onSubmit}>
          <label><span>{t.dateFrom}</span><input type="date" name="from" value={draft.from} onChange={onChange} /></label>
          <label><span>{t.dateTo}</span><input type="date" name="to" value={draft.to} onChange={onChange} /></label>
          <label className="wide"><span>{t.packageFilter}</span><select name="packageId" value={draft.packageId} onChange={onChange}><option value="">{t.allPackages}</option>{packages.map((item) => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
          <div className="report-presets"><button type="button" onClick={() => onPreset(7)}>7 {t.days}</button><button type="button" onClick={() => onPreset(30)}>30 {t.days}</button><button type="button" onClick={() => onPreset(90)}>90 {t.days}</button><button type="button" onClick={() => onPreset(365)}>365 {t.days}</button></div>
          <div className="filter-actions"><button type="button" onClick={onClose}>{t.cancel}</button><button className="admin-primary" disabled={loading}>{loading ? t.loading : t.runReport}</button></div>
        </form>
      </section>
    </div>
  );
}
