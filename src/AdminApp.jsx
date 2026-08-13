import { useCallback, useEffect, useState } from "react";
import { Archive, ArrowRight, Box, Check, ChevronRight, CircleDollarSign, ClipboardList, Languages, LogOut, Menu, PackagePlus, Pencil, RefreshCw, Settings, UserRound } from "lucide-react";
import { adminApi } from "./api/adminClient";
import AdminLogin from "./admin/AdminLogin";
import OrderModal from "./admin/OrderModal";
import PackageModal from "./admin/PackageModal";
import AdminLogo from "./admin/AdminLogo";
import { adminDateTime, adminPrice } from "./admin/utils";
import { translations } from "./i18n/translations";
import { statusLabel } from "./orderStatus";

function AdminApp() {
  const [language, setLanguage] = useState(() => localStorage.getItem("emc-language") || "en");
  const [admin, setAdmin] = useState(null);
  const [checking, setChecking] = useState(true);
  const [tab, setTab] = useState("orders");
  const [menuOpen, setMenuOpen] = useState(false);
  const [orders, setOrders] = useState([]);
  const [packages, setPackages] = useState([]);
  const [pickupFee, setPickupFee] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [packageModal, setPackageModal] = useState(undefined);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const t = translations[language];

  useEffect(() => {
    localStorage.setItem("emc-language", language);
    document.documentElement.lang = language === "mm" ? "my" : "en";
  }, [language]);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [orderData, packageData, settingData] = await Promise.all([adminApi.orders(), adminApi.packages(), adminApi.settings()]);
      setOrders(orderData.orders || []);
      setPackages(packageData.packages || []);
      setPickupFee(settingData.pickupFeeKs || 0);
    } catch {
      setError(t.adminUnavailable);
    } finally {
      setLoading(false);
    }
  }, [t.adminUnavailable]);

  useEffect(() => {
    adminApi.session()
      .then((data) => {
        if (data.authenticated) {
          setAdmin(data.admin);
          loadDashboard();
        }
      })
      .catch(() => {})
      .finally(() => setChecking(false));
  }, [loadDashboard]);

  const logout = async () => {
    try { await adminApi.logout(); } finally { setAdmin(null); }
  };

  const openOrder = async (id) => {
    setError("");
    try {
      const data = await adminApi.order(id);
      setSelectedOrder(data.order);
    } catch {
      setError(t.adminUnavailable);
    }
  };

  const orderUpdated = (updatedOrder) => {
    setSelectedOrder(updatedOrder);
    setOrders((current) => current.map((order) => order.id === updatedOrder.id ? { ...order, ...updatedOrder } : order));
    setNotice(t.statusUpdated);
  };

  const packageSaved = async () => {
    setPackageModal(undefined);
    const data = await adminApi.packages();
    setPackages(data.packages || []);
    setNotice(t.packageSaved);
  };

  const archivePackage = async (packageItem) => {
    if (!window.confirm(t.confirmArchive)) return;
    try {
      await adminApi.archivePackage(packageItem.id);
      const data = await adminApi.packages();
      setPackages(data.packages || []);
      setNotice(t.packageArchived);
    } catch {
      setError(t.adminUnavailable);
    }
  };

  const saveSettings = async (event) => {
    event.preventDefault();
    const value = Number(new FormData(event.currentTarget).get("pickupFeeKs"));
    try {
      const data = await adminApi.updateSettings({ pickupFeeKs: value });
      setPickupFee(data.pickupFeeKs);
      setNotice(t.settingsSaved);
    } catch {
      setError(t.adminUnavailable);
    }
  };

  if (checking) return <div className="admin-splash"><AdminLogo /><span>{t.loading}</span></div>;
  if (!admin) return <AdminLogin language={language} setLanguage={setLanguage} t={t} onLogin={(nextAdmin) => { setAdmin(nextAdmin); loadDashboard(); }} />;

  const activePackages = packages.filter((item) => item.active).length;
  return (
    <div className={language === "mm" ? "admin-app myanmar" : "admin-app"}>
      <aside className={menuOpen ? "admin-sidebar open" : "admin-sidebar"}>
        <AdminLogo />
        <nav>
          <button className={tab === "orders" ? "active" : ""} onClick={() => { setTab("orders"); setMenuOpen(false); }}><ClipboardList />{t.adminOrders}<span>{orders.length}</span></button>
          <button className={tab === "packages" ? "active" : ""} onClick={() => { setTab("packages"); setMenuOpen(false); }}><Box />{t.adminPackages}</button>
          <button className={tab === "settings" ? "active" : ""} onClick={() => { setTab("settings"); setMenuOpen(false); }}><Settings />{t.adminSettings}</button>
        </nav>
        <div className="admin-user"><span><UserRound /></span><div><strong>{admin.displayName}</strong><small>{admin.username}</small></div><button onClick={logout} aria-label={t.logout}><LogOut /></button></div>
      </aside>
      {menuOpen && <button className="admin-sidebar-shade" onClick={() => setMenuOpen(false)} aria-label={t.close} />}
      <main className="admin-main">
        <header className="admin-topbar"><button className="admin-menu" onClick={() => setMenuOpen(true)} aria-label="Menu"><Menu /></button><div><span>{t.adminPortal}</span><strong>{t.dashboard}</strong></div><div><button onClick={() => setLanguage(language === "en" ? "mm" : "en")}><Languages />{t.languageName}</button><button onClick={loadDashboard} disabled={loading} aria-label={t.refresh}><RefreshCw className={loading ? "spinning" : ""} /></button></div></header>
        <div className="admin-content">
          <div className="admin-welcome"><div><p>{t.welcomeAdmin}, {admin.displayName}</p><h1>{tab === "orders" ? t.adminOrders : tab === "packages" ? t.adminPackages : t.adminSettings}</h1></div>{tab === "packages" && <button className="admin-primary" onClick={() => setPackageModal(null)}><PackagePlus />{t.createPackage}</button>}</div>
          {error && <p className="admin-error page-message" role="alert">{error}</p>}
          {notice && <p className="admin-notice page-message" role="status"><Check />{notice}</p>}

          <section className="admin-stats">
            <article><span><ClipboardList /></span><div><small>{t.newOrders}</small><strong>{orders.length}</strong></div></article>
            <article><span><Box /></span><div><small>{t.activePackages}</small><strong>{activePackages}</strong></div></article>
            <article><span><CircleDollarSign /></span><div><small>{t.pickupFee}</small><strong>{adminPrice(pickupFee, language)} <em>{t.ks}</em></strong></div></article>
          </section>

          {tab === "orders" && <OrdersPanel orders={orders} language={language} t={t} onOpen={openOrder} />}
          {tab === "packages" && <PackagesPanel packages={packages} language={language} t={t} onEdit={setPackageModal} onArchive={archivePackage} />}
          {tab === "settings" && <SettingsPanel pickupFee={pickupFee} t={t} onSave={saveSettings} />}
        </div>
      </main>
      {packageModal !== undefined && <PackageModal packageItem={packageModal} t={t} onClose={() => setPackageModal(undefined)} onSaved={packageSaved} />}
      {selectedOrder && <OrderModal order={selectedOrder} language={language} t={t} onClose={() => setSelectedOrder(null)} onUpdated={orderUpdated} />}
    </div>
  );
}

function OrdersPanel({ orders, language, t, onOpen }) {
  return (
    <section className="admin-panel">
      <div className="admin-panel-heading"><div><span>{t.adminOrders}</span><h2>{t.recentOrders}</h2></div></div>
      {orders.length === 0 ? <div className="admin-empty"><ClipboardList /><p>{t.noAdminOrders}</p></div> : (
        <div className="admin-order-table">
          <div className="admin-order-row table-head"><span>{t.orderNumber}</span><span>{t.customerLabel}</span><span>{t.packageLabelAdmin}</span><span>{t.orderTotal}</span><span>{t.submittedAt}</span><span /></div>
          {orders.map((order) => <div className="admin-order-row" key={order.id}><span data-label={t.orderNumber}><strong>{order.orderNumber}</strong><em className={`status-${order.status}`}>{statusLabel(order.status, t)}</em></span><span data-label={t.customerLabel}>{order.customer.name}<small>{order.customer.phone}</small></span><span data-label={t.packageLabelAdmin}>{language === "mm" ? order.package.nameMm : order.package.nameEn}<small>{order.photoCount} {t.photosSection}</small></span><span data-label={t.orderTotal}><strong>{adminPrice(order.totalPriceKs, language)} {t.ks}</strong></span><span data-label={t.submittedAt}>{adminDateTime(order.createdAt, language)}</span><span><button onClick={() => onOpen(order.id)} aria-label={`${t.viewOrder} ${order.orderNumber}`}><ChevronRight /></button></span></div>)}
        </div>
      )}
    </section>
  );
}

function PackagesPanel({ packages, language, t, onEdit, onArchive }) {
  return (
    <section className="admin-package-grid">
      {packages.length === 0 && <div className="admin-empty"><Box /><p>{t.noPackages}</p></div>}
      {packages.map((item) => <article className={!item.active ? "inactive" : ""} key={item.id}><div className="admin-package-top"><span><Box /></span><em>{item.active ? t.activePackage : t.archive}</em></div><h3>{language === "mm" ? item.nameMm : item.nameEn}</h3><p>{language === "mm" ? item.descriptionMm : item.descriptionEn}</p><strong>{adminPrice(item.priceKs, language)} <small>{t.ks}</small></strong><div><button onClick={() => onEdit(item)}><Pencil />{t.edit}</button>{item.active && <button className="archive" onClick={() => onArchive(item)}><Archive />{t.archive}</button>}</div></article>)}
    </section>
  );
}

function SettingsPanel({ pickupFee, t, onSave }) {
  return (
    <section className="admin-panel settings-panel"><div className="settings-icon"><CircleDollarSign /></div><span className="admin-section-kicker">{t.adminSettings}</span><h2>{t.pickupFee}</h2><p>{t.pickupFeeHelp}</p><form onSubmit={onSave}><label><span>{t.priceKs}</span><div><input name="pickupFeeKs" type="number" min="0" step="500" defaultValue={pickupFee} /><em>{t.ks}</em></div></label><button className="admin-primary">{t.savePickupFee}<ArrowRight /></button></form></section>
  );
}

export default AdminApp;
