import { useEffect, useRef, useState } from "react";
import { ArrowRight, Bell, CalendarDays, Check, CheckCircle2, ChevronRight, Clock3, Eye, EyeOff, Image, LockKeyhole, LogOut, Phone, ShieldCheck, UserRound, X } from "lucide-react";
import { accountApi, apiUrl } from "../api/client";
import { localizedStatusNote, statusLabel } from "../orderStatus";
import useDialogFocus from "./useDialogFocus";
import { appBaseFromModuleUrl } from "../api/baseUrl";
import { disablePush, enablePush, syncPush } from "../pushNotifications";

const emcIcon = `${appBaseFromModuleUrl(import.meta.url)}/emcicon.jpg`;

function translatedError(error, t) {
  const messages = {
    invalid_credentials: t.invalidCredentials,
    phone_in_use: t.phoneInUse,
    too_many_attempts: t.tooManyAttempts,
    csrf_failed: t.sessionChanged,
  };
  return messages[error?.code] || t.accountUnavailable;
}

function AccountModal({ mode: initialMode, customer, t, onClose, onAuthenticated, onProfileUpdate, onLogout, onUnreadChange, embedded = false, mobileSection = "all" }) {
  const [mode, setMode] = useState(customer ? "profile" : initialMode || "login");
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [success, setSuccess] = useState("");
  const [orders, setOrders] = useState([]);
  const [ordersLoading, setOrdersLoading] = useState(Boolean(customer));
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [ordersError, setOrdersError] = useState("");
  const [notificationState, setNotificationState] = useState("checking");
  const [notificationBusy, setNotificationBusy] = useState(false);
  const modalRef = useRef(null);

  useDialogFocus(modalRef, onClose, busy, "[data-initial-focus]", !embedded);

  useEffect(() => {
    if (!embedded) modalRef.current?.querySelector("[data-initial-focus]")?.focus();
  }, [embedded, mode]);

  useEffect(() => {
    if (embedded) setMode(customer ? "profile" : initialMode || "login");
    else if (customer) setMode("profile");
  }, [customer, embedded, initialMode]);

  useEffect(() => {
    if (!customer || (embedded && mobileSection !== "activity")) return;
    let active = true;
    const refreshOrders = async () => {
      try {
        const data = await accountApi.orders();
        if (!active) return;
        const nextOrders = data.orders || [];
        setOrders(nextOrders);
        onUnreadChange(nextOrders.filter((order) => order.unreadStatus).length);
        if (selectedOrder?.id) {
          const detail = await accountApi.order(selectedOrder.id);
          if (active) setSelectedOrder(detail.order);
          if (detail.order?.unreadStatus) await accountApi.markOrderSeen(selectedOrder.id);
        }
        if (active) setOrdersError("");
      } catch {
        if (active) setOrdersError(t.ordersLoadError);
      } finally {
        if (active) setOrdersLoading(false);
      }
    };
    const refreshWhenVisible = () => { if (document.visibilityState === "visible") refreshOrders(); };
    refreshOrders();
    const interval = window.setInterval(refreshWhenVisible, 15000);
    window.addEventListener("focus", refreshWhenVisible);
    document.addEventListener("visibilitychange", refreshWhenVisible);
    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener("focus", refreshWhenVisible);
      document.removeEventListener("visibilitychange", refreshWhenVisible);
    };
  }, [customer, embedded, mobileSection, onUnreadChange, selectedOrder?.id, t.ordersLoadError]);

  useEffect(() => {
    if (!customer) return;
    syncPush((subscription) => accountApi.savePushSubscription(subscription)).then(setNotificationState).catch(() => setNotificationState("disabled"));
  }, [customer]);

  const toggleNotifications = async () => {
    setNotificationBusy(true);
    setMessage("");
    try {
      if (notificationState === "enabled") {
        setNotificationState(await disablePush((endpoint) => accountApi.removePushSubscription(endpoint)));
      } else {
        const configuration = await accountApi.pushConfiguration();
        if (!configuration.enabled) throw new Error("push_unsupported");
        setNotificationState(await enablePush(configuration.publicKey, (subscription) => accountApi.savePushSubscription(subscription)));
      }
    } catch (error) {
      const state = error?.message === "push_denied" ? "denied" : "unsupported";
      setNotificationState(state);
      setMessage(state === "denied" ? t.notificationDenied : t.notificationUnavailable);
    } finally {
      setNotificationBusy(false);
    }
  };

  const switchMode = (nextMode) => {
    setMode(nextMode);
    setMessage("");
    setSuccess("");
  };

  const submitAuth = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const details = {
      phone: String(form.get("phone") || ""),
      password: String(form.get("password") || ""),
      remember: form.get("remember") === "on",
      fullName: String(form.get("fullName") || ""),
      address: String(form.get("address") || ""),
    };
    const validPhone = /^(?:\+?959|09|9)\d{7,9}$/.test(details.phone.replace(/[\s()-]/g, ""));
    if (!validPhone || details.password.length < 8 || (mode === "register" && details.fullName.trim().length < 2)) {
      setMessage(mode === "register" ? t.registerValidation : t.accountValidation);
      return;
    }
    setBusy(true);
    setMessage("");
    try {
      const data = mode === "register" ? await accountApi.register(details) : await accountApi.login(details);
      onAuthenticated(data.customer);
    } catch (error) {
      setMessage(translatedError(error, t));
    } finally {
      setBusy(false);
    }
  };

  const submitProfile = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const details = { fullName: String(form.get("fullName") || ""), address: String(form.get("address") || "") };
    if (details.fullName.trim().length < 2) {
      setMessage(t.registerValidation);
      return;
    }
    setBusy(true);
    setMessage("");
    setSuccess("");
    try {
      const data = await accountApi.updateProfile(details);
      onProfileUpdate(data.customer);
      setSuccess(t.profileSaved);
    } catch (error) {
      setMessage(translatedError(error, t));
    } finally {
      setBusy(false);
    }
  };

  const logout = async () => {
    setBusy(true);
    setMessage("");
    try {
      await accountApi.logout();
      onLogout();
    } catch (error) {
      setMessage(translatedError(error, t));
      setBusy(false);
    }
  };

  const openOrder = async (orderId) => {
    if (selectedOrder?.id === orderId) {
      setSelectedOrder(null);
      return;
    }
    setOrdersError("");
    try {
      const data = await accountApi.order(orderId);
      setSelectedOrder(data.order);
      if (orders.find((order) => order.id === orderId)?.unreadStatus) {
        await accountApi.markOrderSeen(orderId);
        setOrders((current) => {
          const updated = current.map((order) => order.id === orderId ? { ...order, unreadStatus: false } : order);
          onUnreadChange(updated.filter((order) => order.unreadStatus).length);
          return updated;
        });
      }
    } catch {
      setOrdersError(t.ordersLoadError);
    }
  };

  const isMyanmar = t.languageName === "English";
  const orderPrice = (value) => new Intl.NumberFormat(isMyanmar ? "my-MM" : "en-US").format(value);
  const orderDate = (value) => new Intl.DateTimeFormat(isMyanmar ? "my-MM" : "en-GB", { dateStyle: "medium" }).format(new Date(value));
  const orderDateTime = (value) => new Intl.DateTimeFormat(isMyanmar ? "my-MM" : "en-GB", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));

  const closeFromBackdrop = (event) => {
    if (event.target === event.currentTarget && !busy) onClose();
  };

  const accountTitleId = embedded ? `mobile-${mobileSection}-account-title` : "account-title";
  const ordersTitleId = embedded ? `mobile-${mobileSection}-orders-title` : "my-orders-heading";

  const accountContent = (
      <section ref={modalRef} className={embedded ? "account-modal embedded" : "account-modal"} role={embedded ? "region" : "dialog"} aria-modal={embedded ? undefined : "true"} aria-labelledby={embedded && mobileSection === "activity" ? ordersTitleId : accountTitleId} tabIndex="-1">
        {!embedded && <button className="modal-close" type="button" onClick={onClose} disabled={busy} aria-label={t.close}><X /></button>}
        {!embedded && <div className="account-brand"><span><img src={emcIcon} alt="" /></span><strong>EMC</strong></div>}

        {mode === "profile" ? (
          <>
            {mobileSection !== "activity" && <>
            <div className="account-heading">
              <p className="eyebrow"><UserRound size={15} />{t.account}</p>
              <h2 id={accountTitleId}>{t.profileTitle}</h2>
              <p>{t.profileIntro}</p>
            </div>
            <form className="account-form" onSubmit={submitProfile}>
              <label className="account-field"><span>{t.fullName}</span><input name="fullName" defaultValue={customer.fullName} autoComplete="name" data-initial-focus /></label>
              <label className="account-field"><span>{t.phone}</span><div className="locked-field"><Phone size={17} /><input value={customer.phone} readOnly /></div><small>{t.phoneCannotChange}</small></label>
              <label className="account-field"><span>{t.address}</span><textarea name="address" rows="3" defaultValue={customer.address} placeholder={t.addressPlaceholder} /></label>
              {message && <p className="account-error" role="alert">{message}</p>}
              {success && <p className="account-success" role="status"><Check size={16} />{success}</p>}
              <button className="primary-button account-submit" disabled={busy}>{busy ? t.savingProfile : t.saveProfile}<ArrowRight size={17} /></button>
            </form>
            <button className="notification-toggle" type="button" onClick={toggleNotifications} disabled={notificationBusy || notificationState === "checking" || notificationState === "unsupported" || notificationState === "denied"}>
              <Bell size={17} />
              <span><strong>{notificationState === "enabled" ? t.notificationEnabled : t.enableNotifications}</strong><small>{notificationState === "enabled" ? t.disableNotifications : t.notifications}</small></span>
            </button>
            </>}
            {mobileSection !== "account" && <>
            <div className="account-divider" />
            <section className="account-orders" aria-labelledby={ordersTitleId}>
              <div className="account-orders-heading"><div><span>{t.orderHistory}</span><h3 id={ordersTitleId}>{t.myOrders}</h3></div><span>{orders.length}</span></div>
              {ordersLoading && <p className="orders-empty">{t.loadingOrders}</p>}
              {!ordersLoading && orders.length === 0 && <p className="orders-empty">{t.noOrdersYet}</p>}
              {ordersError && <p className="account-error" role="alert">{ordersError}</p>}
              <div className="customer-order-list">
                {orders.map((order) => (
                  <div className={selectedOrder?.id === order.id ? "customer-order active" : "customer-order"} key={order.id}>
                    <button type="button" onClick={() => openOrder(order.id)}>
                      <span className={order.unreadStatus ? "order-list-icon unread" : "order-list-icon"}>{order.unreadStatus ? <Bell /> : <CalendarDays />}</span>
                      <span><strong>{order.orderNumber}</strong><small>{order.package.name} · {orderDate(order.createdAt)}</small></span>
                      <span className="order-list-end">{order.unreadStatus && <b>{t.newUpdate}</b>}<em className={`status-${order.status}`}>{statusLabel(order.status, t)}</em><ChevronRight /></span>
                    </button>
                    {selectedOrder?.id === order.id && (
                      <div className="customer-order-detail">
                        <div className="order-price-row"><span>{t.orderTotal}</span><strong>{orderPrice(selectedOrder.totalPriceKs)} {t.ks}</strong></div>
                        <div className="order-detail-grid"><span><small>{t.handoverLabel}</small><strong>{selectedOrder.handover === "pickup" ? t.pickup : t.dropoff}</strong></span><span><small>{t.photosSection}</small><strong>{selectedOrder.photoCount}</strong></span></div>
                        {selectedOrder.notes && <p><strong>{t.notes}:</strong> {selectedOrder.notes}</p>}
                        <div className="order-photo-strip">
                          {selectedOrder.photos.map((photo, index) => <img key={photo.id} src={apiUrl(photo.url)} alt={`${t.photosSection} ${index + 1}`} />)}
                          {selectedOrder.photos.length === 0 && <span><Image />{t.noPhotos}</span>}
                        </div>
                        <section className="customer-status-section">
                          <div className="customer-status-heading"><Clock3 /><div><small>{t.currentStatus}</small><strong>{statusLabel(selectedOrder.status, t)}</strong></div></div>
                          <div className="customer-timeline">
                            {[...selectedOrder.history].reverse().map((entry, index) => <article key={entry.id} className={index === 0 ? "latest" : ""}><i><CheckCircle2 /></i><div><div><strong>{statusLabel(entry.status, t)}</strong><time>{orderDateTime(entry.createdAt)}</time></div><p>{localizedStatusNote(entry, isMyanmar ? "mm" : "en", t.noStatusNote)}</p><small>{entry.changedBy ? t.updatedByEmc : t.systemUpdate}</small></div></article>)}
                          </div>
                        </section>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </section>
            </>}
            {mobileSection !== "activity" && <>
            <div className="account-divider" />
            <button className="logout-button" type="button" onClick={logout} disabled={busy}><LogOut size={17} />{busy ? t.loggingOut : t.logout}</button>
            </>}
          </>
        ) : (
          <>
            <div className="account-tabs" role="tablist">
              <button type="button" className={mode === "login" ? "active" : ""} onClick={() => switchMode("login")}>{t.signIn}</button>
              <button type="button" className={mode === "register" ? "active" : ""} onClick={() => switchMode("register")}>{t.createAccount}</button>
            </div>
            <div className="account-heading">
              <p className="eyebrow"><LockKeyhole size={15} />{t.noOtp}</p>
              <h2 id={accountTitleId}>{mode === "login" ? t.accountWelcome : t.createAccount}</h2>
              <p>{mode === "login" ? t.accountIntro : t.registerIntro}</p>
            </div>
            <form className="account-form" onSubmit={submitAuth}>
              {mode === "register" && (
                <>
                  <label className="account-field"><span>{t.fullName}</span><input name="fullName" placeholder={t.fullNamePlaceholder} autoComplete="name" data-initial-focus /></label>
                  <label className="account-field"><span>{t.address}</span><textarea name="address" rows="2" placeholder={t.addressPlaceholder} autoComplete="street-address" /></label>
                </>
              )}
              <label className="account-field"><span>{t.phone}</span><input name="phone" type="tel" inputMode="tel" placeholder={t.phonePlaceholder} autoComplete="tel" data-initial-focus={mode === "login" || undefined} /></label>
              <label className="account-field"><span>{t.password}</span><div className="password-field"><input name="password" type={showPassword ? "text" : "password"} placeholder={t.passwordPlaceholder} autoComplete={mode === "login" ? "current-password" : "new-password"} /><button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? t.hidePassword : t.showPassword}>{showPassword ? <EyeOff /> : <Eye />}</button></div></label>
              <label className="remember-row"><input name="remember" type="checkbox" defaultChecked aria-label={t.rememberMe} /><span><strong>{t.rememberMe}</strong><small>{t.rememberHint}</small></span></label>
              {message && <p className="account-error" role="alert">{message}</p>}
              <button className="primary-button account-submit" disabled={busy}>{busy ? (mode === "login" ? t.signingIn : t.creatingAccount) : (mode === "login" ? t.signIn : t.createAccount)}<ArrowRight size={17} /></button>
            </form>
            <p className="account-switch">{mode === "login" ? t.newToEmc : t.alreadyMember} <button type="button" onClick={() => switchMode(mode === "login" ? "register" : "login")}>{mode === "login" ? t.createAccount : t.signIn}</button></p>
            <p className="account-security"><ShieldCheck size={16} />{t.accountSecure}</p>
          </>
        )}
      </section>
  );

  if (embedded) return <div className={`mobile-account-screen ${mobileSection}`}>{accountContent}</div>;

  return <div className="modal-backdrop" role="presentation" onMouseDown={closeFromBackdrop}>{accountContent}</div>;
}

export default AccountModal;
