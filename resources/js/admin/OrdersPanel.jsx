import { useEffect, useRef, useState } from "react";
import { ChevronLeft, ChevronRight, ClipboardList, RotateCcw, Search, SlidersHorizontal, X } from "lucide-react";
import { statusLabel } from "../orderStatus";
import useDialogFocus from "../components/useDialogFocus";
import { adminDateTime, adminPrice } from "./utils";

const STATUSES = ["submitted", "confirmed", "pickup_scheduled", "rider_on_way", "shoes_received", "repairing", "ready", "done", "cancelled"];

export default function OrdersPanel({ orders, packages, pagination, filters, language, loading, t, onFilter, onPage, onOpen }) {
  const [draft, setDraft] = useState(filters);
  const [filtersOpen, setFiltersOpen] = useState(false);
  useEffect(() => setDraft(filters), [filters]);

  const change = (event) => setDraft((current) => ({ ...current, [event.target.name]: event.target.value }));
  const submit = (event) => {
    event.preventDefault();
    onFilter({ ...draft, page: 1 });
    setFiltersOpen(false);
  };
  const reset = () => {
    const clean = { search: "", status: "", packageId: "", handover: "", from: "", to: "", perPage: filters.perPage, page: 1 };
    setDraft(clean);
    onFilter(clean);
    setFiltersOpen(false);
  };
  const activeFilterCount = [filters.search, filters.status, filters.packageId, filters.handover, filters.from, filters.to].filter(Boolean).length;

  return <>
    <section className="admin-panel orders-panel">
      <div className="admin-panel-heading orders-heading"><div><span>{t.adminOrders}</span><h2>{t.orderManagement}</h2><p>{t.orderManagementHelp}</p></div><button type="button" onClick={() => { setDraft(filters); setFiltersOpen(true); }}><SlidersHorizontal />{t.filters}{activeFilterCount > 0 && <i>{activeFilterCount}</i>}</button><strong>{pagination.total || 0}<small>{t.results}</small></strong></div>
      {orders.length === 0 ? <div className="admin-empty"><ClipboardList /><p>{loading ? t.loading : t.noFilteredOrders}</p></div> : (
        <div className={loading ? "admin-order-table loading" : "admin-order-table"}>
          <div className="admin-order-row table-head"><span>{t.orderNumber}</span><span>{t.customerLabel}</span><span>{t.packageLabelAdmin}</span><span>{t.orderTotal}</span><span>{t.submittedAt}</span><span /></div>
          {orders.map((order) => <div className="admin-order-row" key={order.id}><span data-label={t.orderNumber}><strong>{order.orderNumber}</strong><em className={`status-${order.status}`}>{statusLabel(order.status, t)}</em></span><span data-label={t.customerLabel}>{order.customer.name}<small>{order.customer.phone}</small></span><span data-label={t.packageLabelAdmin}>{order.package.name}<small>{order.photoCount} {t.photosSection}</small></span><span data-label={t.orderTotal}><strong>{adminPrice(order.totalPriceKs, language)} {t.ks}</strong></span><span data-label={t.submittedAt}>{adminDateTime(order.createdAt, language)}</span><span><button onClick={() => onOpen(order.id)} aria-label={`${t.viewOrder} ${order.orderNumber}`}><ChevronRight /></button></span></div>)}
        </div>
      )}

      <footer className="order-pagination">
        <p>{pagination.total ? `${pagination.from}–${pagination.to} ${t.of} ${pagination.total}` : `0 ${t.results}`}</p>
        <label><span>{t.rowsPerPage}</span><select value={filters.perPage} onChange={(event) => onFilter({ ...filters, perPage: Number(event.target.value), page: 1 })}><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>
        <div><button disabled={loading || pagination.currentPage <= 1} onClick={() => onPage(pagination.currentPage - 1)} aria-label={t.previousPage}><ChevronLeft /></button><span>{pagination.currentPage || 1} / {pagination.lastPage || 1}</span><button disabled={loading || pagination.currentPage >= pagination.lastPage} onClick={() => onPage(pagination.currentPage + 1)} aria-label={t.nextPage}><ChevronRight /></button></div>
      </footer>
    </section>
    {filtersOpen && <OrderFilterModal draft={draft} packages={packages} loading={loading} t={t} onChange={change} onSubmit={submit} onReset={reset} onClose={() => setFiltersOpen(false)} />}
  </>;
}

function OrderFilterModal({ draft, packages, loading, t, onChange, onSubmit, onReset, onClose }) {
  const dialogRef = useRef(null);
  useDialogFocus(dialogRef, onClose, loading, "input[name='search']");

  return (
    <div className="order-filter-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !loading && onClose()}>
      <section ref={dialogRef} className="order-filter-dialog" role="dialog" aria-modal="true" aria-labelledby="order-filter-title" tabIndex="-1">
        <header><div><span className="admin-section-kicker">{t.adminOrders}</span><h2 id="order-filter-title">{t.filterOrders}</h2><p>{t.filterOrdersHelp}</p></div><button type="button" onClick={onClose} disabled={loading} aria-label={t.close}><X /></button></header>
        <form className="order-filter-form" onSubmit={onSubmit}>
          <label className="filter-search"><span>{t.searchOrders}</span><div><Search /><input name="search" value={draft.search} onChange={onChange} placeholder={t.searchOrdersPlaceholder} /></div></label>
          <label><span>{t.statusFilter}</span><select name="status" value={draft.status} onChange={onChange}><option value="">{t.allStatuses}</option>{STATUSES.map((status) => <option key={status} value={status}>{statusLabel(status, t)}</option>)}</select></label>
          <label><span>{t.packageFilter}</span><select name="packageId" value={draft.packageId} onChange={onChange}><option value="">{t.allPackages}</option>{packages.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
          <label><span>{t.handoverLabel}</span><select name="handover" value={draft.handover} onChange={onChange}><option value="">{t.allHandover}</option><option value="pickup">{t.pickup}</option><option value="dropoff">{t.dropoff}</option></select></label>
          <label><span>{t.dateFrom}</span><input type="date" name="from" value={draft.from} onChange={onChange} /></label>
          <label><span>{t.dateTo}</span><input type="date" name="to" value={draft.to} onChange={onChange} /></label>
          <div className="filter-actions"><button type="button" onClick={onReset} disabled={loading}><RotateCcw />{t.clearFilters}</button><button className="admin-primary" disabled={loading}>{loading ? t.loading : t.applyFilters}</button></div>
        </form>
      </section>
    </div>
  );
}
