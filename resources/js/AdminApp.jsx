import { useCallback, useEffect, useState } from "react";
import { Archive, BarChart3, Box, Check, ClipboardList, Languages, LogOut, Menu, PackagePlus, Pencil, RefreshCw, UserRound } from "lucide-react";
import { adminApi } from "./api/adminClient";
import AdminLogin from "./admin/AdminLogin";
import OrderModal from "./admin/OrderModal";
import OrdersPanel from "./admin/OrdersPanel";
import PackageModal from "./admin/PackageModal";
import ReportsPanel from "./admin/ReportsPanel";
import AdminLogo from "./admin/AdminLogo";
import { adminPrice } from "./admin/utils";
import { translations } from "./i18n/translations";

const INITIAL_FILTERS = { search: "", status: "", packageId: "", handover: "", from: "", to: "", perPage: 25, page: 1 };
const EMPTY_PAGINATION = { currentPage: 1, lastPage: 1, perPage: 25, total: 0, from: null, to: null };
const tabFromPath = () => {
  const match = window.location.pathname.match(/\/admin(?:\/(orders|packages|reports))?\/?$/);
  return match?.[1] || "orders";
};
const adminPath = (tab) => `${window.location.pathname.replace(/\/admin(?:\/(?:orders|packages|reports))?\/?$/, "/admin")}/${tab}`;

function AdminApp() {
  const [language, setLanguage] = useState(() => localStorage.getItem("emc-language") || "en");
  const [admin, setAdmin] = useState(null);
  const [checking, setChecking] = useState(true);
  const [tab, setTab] = useState(tabFromPath);
  const [menuOpen, setMenuOpen] = useState(false);
  const [orders, setOrders] = useState([]);
  const [pagination, setPagination] = useState(EMPTY_PAGINATION);
  const [filters, setFilters] = useState(INITIAL_FILTERS);
  const [packages, setPackages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [packageModal, setPackageModal] = useState(undefined);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [reportRefreshKey, setReportRefreshKey] = useState(0);
  const t = translations[language];

  useEffect(() => {
    localStorage.setItem("emc-language", language);
    document.documentElement.lang = language === "mm" ? "my" : "en";
  }, [language]);

  const loadOrders = useCallback(async (nextFilters = INITIAL_FILTERS) => {
    setLoading(true);
    setError("");
    try {
      const orderData = await adminApi.orders(nextFilters);
      setOrders(orderData.orders || []);
      setPagination(orderData.pagination || EMPTY_PAGINATION);
    } catch {
      setError(t.adminUnavailable);
    } finally {
      setLoading(false);
    }
  }, [t.adminUnavailable]);

  const loadDashboard = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [orderData, packageData] = await Promise.all([adminApi.orders(INITIAL_FILTERS), adminApi.packages()]);
      setOrders(orderData.orders || []);
      setPagination(orderData.pagination || EMPTY_PAGINATION);
      setPackages(packageData.packages || []);
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

  useEffect(() => {
    const handlePopState = () => setTab(tabFromPath());
    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  const switchTab = (nextTab) => {
    setTab(nextTab);
    setMenuOpen(false);
    window.history.pushState({}, "", adminPath(nextTab));
  };

  const applyFilters = (nextFilters) => {
    setFilters(nextFilters);
    loadOrders(nextFilters);
  };

  const refreshCurrent = () => {
    if (tab === "orders") loadOrders(filters);
    else if (tab === "packages") adminApi.packages().then((data) => setPackages(data.packages || [])).catch(() => setError(t.adminUnavailable));
    else setReportRefreshKey((current) => current + 1);
  };

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
    loadOrders(filters);
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

  if (checking) return <div className="admin-splash"><AdminLogo /><span>{t.loading}</span></div>;
  if (!admin) return <AdminLogin language={language} setLanguage={setLanguage} t={t} onLogin={(nextAdmin) => { setAdmin(nextAdmin); loadDashboard(); }} />;

  return (
    <div className={language === "mm" ? "admin-app myanmar" : "admin-app"}>
      <aside className={menuOpen ? "admin-sidebar open" : "admin-sidebar"}>
        <AdminLogo />
        <nav>
          <button className={tab === "orders" ? "active" : ""} onClick={() => switchTab("orders")}><ClipboardList />{t.adminOrders}<span>{pagination.total}</span></button>
          <button className={tab === "packages" ? "active" : ""} onClick={() => switchTab("packages")}><Box />{t.adminPackages}</button>
          <button className={tab === "reports" ? "active" : ""} onClick={() => switchTab("reports")}><BarChart3 />{t.adminReports}</button>
        </nav>
        <div className="admin-user"><span><UserRound /></span><div><strong>{admin.displayName}</strong><small>{admin.username}</small></div><button onClick={logout} aria-label={t.logout}><LogOut /></button></div>
      </aside>
      {menuOpen && <button className="admin-sidebar-shade" onClick={() => setMenuOpen(false)} aria-label={t.close} />}
      <main className="admin-main">
        <header className="admin-topbar"><button className="admin-menu" onClick={() => setMenuOpen(true)} aria-label={t.menu}><Menu /></button><div><span>{t.adminPortal}</span><strong>{t.dashboard}</strong></div><div><button onClick={() => setLanguage(language === "en" ? "mm" : "en")}><Languages />{t.languageName}</button><button onClick={refreshCurrent} disabled={loading} aria-label={t.refresh}><RefreshCw className={loading ? "spinning" : ""} /></button></div></header>
        <div className="admin-content">
          <div className="admin-welcome"><div><p>{t.welcomeAdmin}, {admin.displayName}</p><h1>{tab === "orders" ? t.adminOrders : tab === "packages" ? t.adminPackages : t.adminReports}</h1></div>{tab === "packages" && <button className="admin-primary" onClick={() => setPackageModal(null)}><PackagePlus />{t.createPackage}</button>}</div>
          {error && <p className="admin-error page-message" role="alert">{error}</p>}
          {notice && <p className="admin-notice page-message" role="status"><Check />{notice}</p>}

          {tab === "orders" && <OrdersPanel orders={orders} packages={packages} pagination={pagination} filters={filters} language={language} loading={loading} t={t} onFilter={applyFilters} onPage={(page) => applyFilters({ ...filters, page })} onOpen={openOrder} />}
          {tab === "packages" && <PackagesPanel packages={packages} language={language} t={t} onEdit={setPackageModal} onArchive={archivePackage} />}
          {tab === "reports" && <ReportsPanel packages={packages} language={language} refreshKey={reportRefreshKey} t={t} onError={setError} />}
        </div>
      </main>
      {packageModal !== undefined && <PackageModal packageItem={packageModal} t={t} onClose={() => setPackageModal(undefined)} onSaved={packageSaved} />}
      {selectedOrder && <OrderModal order={selectedOrder} language={language} t={t} onClose={() => setSelectedOrder(null)} onUpdated={orderUpdated} />}
    </div>
  );
}

function PackagesPanel({ packages, language, t, onEdit, onArchive }) {
  return (
    <section className="admin-package-grid">
      {packages.length === 0 && <div className="admin-empty"><Box /><p>{t.noPackages}</p></div>}
      {packages.map((item) => <article className={!item.active ? "inactive" : ""} key={item.id}><div className="admin-package-top"><span><Box /></span><em>{item.active ? t.activePackage : t.archive}</em></div><h3>{item.name}</h3><p>{item.description}</p><strong>{adminPrice(item.priceKs, language)} <small>{t.ks}</small></strong><div><button onClick={() => onEdit(item)}><Pencil />{t.edit}</button>{item.active && <button className="archive" onClick={() => onArchive(item)}><Archive />{t.archive}</button>}</div></article>)}
    </section>
  );
}

export default AdminApp;
