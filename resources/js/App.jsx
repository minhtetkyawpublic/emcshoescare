import { useEffect, useRef, useState } from "react";
import {
  ArrowRight,
  Check,
  ChevronRight,
  CircleCheck,
  ClipboardList,
  Clock3,
  House,
  ImagePlus,
  Languages,
  Menu,
  PackageCheck,
  ShieldCheck,
  Sparkles,
  SprayCan,
  Store,
  Truck,
  Upload,
  UserRound,
  Wrench,
  X,
} from "lucide-react";
import { packageDefinitions, translations } from "./i18n/translations";
import { accountApi } from "./api/client";
import AccountModal from "./components/AccountModal";
import InstallGuide, { NetworkSignals } from "./components/AppSignals";
import { appBaseFromModuleUrl } from "./api/baseUrl";

const emcIcon = `${appBaseFromModuleUrl(import.meta.url)}/emcicon.jpg`;
import { clearOrderDraft, loadOrderDraft, newRequestId, saveOrderDraft } from "./orderDraft";

const MAX_PHOTOS = 10;
const MAX_SOURCE_PHOTO_BYTES = 20 * 1024 * 1024;

const fallbackPackages = packageDefinitions.map((pkg, index) => ({
  ...pkg,
  id: index + 1,
  priceKs: pkg.price,
  nameEn: translations.en[pkg.nameKey],
  nameMm: translations.mm[pkg.nameKey],
  descriptionEn: translations.en[pkg.descKey],
  descriptionMm: translations.mm[pkg.descKey],
}));

function localizedPackage(packageItem, language) {
  return {
    name: language === "mm" ? packageItem.nameMm : packageItem.nameEn,
    description: language === "mm" ? packageItem.descriptionMm : packageItem.descriptionEn,
  };
}

function formatPrice(value, language) {
  return new Intl.NumberFormat(language === "mm" ? "my-MM" : "en-US").format(value);
}

function compressImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = reject;
    reader.onload = () => {
      const img = new Image();
      img.onerror = reject;
      img.onload = () => {
        const maxSide = 1600;
        const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(img.width * scale));
        canvas.height = Math.max(1, Math.round(img.height * scale));
        const context = canvas.getContext("2d");
        context.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
          (blob) => {
            if (!blob) return reject(new Error("Image compression failed"));
            const extension = blob.type === "image/webp" ? "webp" : blob.type === "image/png" ? "png" : "jpg";
            const name = file.name.replace(/\.[^/.]+$/, "") + `-compressed.${extension}`;
            resolve(new File([blob], name, { type: blob.type, lastModified: Date.now() }));
          },
          "image/webp",
          0.76,
        );
      };
      img.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

function Logo({ compact = false }) {
  return (
    <div className="brand-lockup">
      <span className="logo-frame"><img src={emcIcon} alt="EMC" /></span>
      {!compact && <span><strong>EMC</strong><small>Shoes Care Myanmar</small></span>}
    </div>
  );
}

function App() {
  const [language, setLanguage] = useState(() => localStorage.getItem("emc-language") || "en");
  const [menuOpen, setMenuOpen] = useState(false);
  const [mobilePage, setMobilePage] = useState("home");
  const [packages, setPackages] = useState(fallbackPackages);
  const [selectedPackage, setSelectedPackage] = useState(String(fallbackPackages[1]?.id || ""));
  const [pickupFee, setPickupFee] = useState(0);
  const [handover, setHandover] = useState("dropoff");
  const [photos, setPhotos] = useState([]);
  const [photoError, setPhotoError] = useState("");
  const [formError, setFormError] = useState("");
  const [formNotice, setFormNotice] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [submittedOrder, setSubmittedOrder] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [installPrompt, setInstallPrompt] = useState(null);
  const [installGuideOpen, setInstallGuideOpen] = useState(false);
  const [installed, setInstalled] = useState(() => window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true);
  const [customer, setCustomer] = useState(null);
  const [accountMode, setAccountMode] = useState(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [orderContact, setOrderContact] = useState({ name: "", phone: "", address: "" });
  const [orderNotes, setOrderNotes] = useState("");
  const [clientRequestId, setClientRequestId] = useState(newRequestId);
  const [recoveredDraft, setRecoveredDraft] = useState(null);
  const fileInput = useRef(null);
  const mainScroll = useRef(null);
  const t = translations[language];

  useEffect(() => {
    localStorage.setItem("emc-language", language);
    document.documentElement.lang = language === "mm" ? "my" : "en";
  }, [language]);

  useEffect(() => {
    document.documentElement.classList.add("customer-app-shell");
    document.body.classList.add("customer-app-shell");
    return () => {
      document.documentElement.classList.remove("customer-app-shell");
      document.body.classList.remove("customer-app-shell");
    };
  }, []);

  useEffect(() => {
    const handlePrompt = (event) => {
      event.preventDefault();
      setInstallPrompt(event);
    };
    window.addEventListener("beforeinstallprompt", handlePrompt);
    const handleInstalled = () => {
      setInstalled(true);
      setInstallGuideOpen(false);
    };
    window.addEventListener("appinstalled", handleInstalled);
    return () => {
      window.removeEventListener("beforeinstallprompt", handlePrompt);
      window.removeEventListener("appinstalled", handleInstalled);
    };
  }, []);

  useEffect(() => {
    loadOrderDraft().then((draft) => {
      if (draft?.photos?.length) setRecoveredDraft(draft);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    let active = true;
    Promise.all([accountApi.packages(), accountApi.settings()])
      .then(([packageData, settingsData]) => {
        if (!active) return;
        if (Array.isArray(packageData.packages)) {
          setPackages(packageData.packages);
          setSelectedPackage((current) => packageData.packages.some((pkg) => String(pkg.id) === String(current))
            ? String(current)
            : packageData.packages.length ? String(packageData.packages[Math.min(1, packageData.packages.length - 1)].id) : "");
        }
        setPickupFee(settingsData.pickupFeeKs || 0);
      })
      .catch(() => {});
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!customer) return undefined;
    let active = true;
    const refreshUpdates = () => accountApi.orders()
      .then((data) => {
        if (active) setUnreadCount((data.orders || []).filter((order) => order.unreadStatus).length);
      })
      .catch(() => {});
    const interval = window.setInterval(refreshUpdates, 60000);
    window.addEventListener("focus", refreshUpdates);
    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener("focus", refreshUpdates);
    };
  }, [customer]);

  useEffect(() => {
    let active = true;
    accountApi.session()
      .then((data) => {
        if (active && data.authenticated) {
          setCustomer(data.customer);
          setOrderContact({ name: data.customer.fullName, phone: data.customer.phone, address: data.customer.address || "" });
          accountApi.orders().then((ordersData) => {
            if (active) setUnreadCount((ordersData.orders || []).filter((order) => order.unreadStatus).length);
          }).catch(() => {});
        }
      })
      .catch(() => {});
    return () => { active = false; };
  }, []);

  const openMobilePage = (page, targetId = null) => {
    setMobilePage(page);
    setMenuOpen(false);
    window.requestAnimationFrame(() => {
      if (targetId) {
        document.getElementById(targetId)?.scrollIntoView({ behavior: "smooth", block: "start" });
      } else {
        mainScroll.current?.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
  };

  const scrollTo = (id) => {
    if (window.matchMedia("(max-width: 760px)").matches) {
      const page = id === "order" ? "order" : id === "process" ? "process" : id === "top" ? "home" : "services";
      openMobilePage(page, id === "packages" ? "packages" : null);
      return;
    }
    document.getElementById(id)?.scrollIntoView({ behavior: "smooth" });
    setMenuOpen(false);
  };

  const choosePackage = (id) => {
    setSelectedPackage(id);
    scrollTo("order");
  };

  const handleInstall = async () => {
    if (!installPrompt) {
      setInstallGuideOpen(true);
      return;
    }
    installPrompt.prompt();
    const result = await installPrompt.userChoice;
    if (result.outcome !== "accepted") setInstallGuideOpen(true);
    setInstallPrompt(null);
  };

  const handlePhotos = async (event) => {
    const chosen = [...event.target.files];
    event.target.value = "";
    setPhotoError("");
    if (chosen.some((file) => !file.type.startsWith("image/"))) {
      setPhotoError(t.photoTypeError);
      return;
    }
    if (photos.length + chosen.length > MAX_PHOTOS) {
      setPhotoError(t.photoLimitError);
      return;
    }
    if (chosen.some((file) => file.size > MAX_SOURCE_PHOTO_BYTES)) {
      setPhotoError(t.photoSourceSizeError);
      return;
    }
    try {
      const compressed = [];
      for (const file of chosen) compressed.push(await compressImage(file));
      const items = compressed.map((file, index) => ({
        id: `${Date.now()}-${index}`,
        file,
        url: URL.createObjectURL(file),
        originalSize: chosen[index].size,
      }));
      setPhotos((current) => [...current, ...items]);
    } catch {
      setPhotoError(t.photoTypeError);
    }
  };

  const restoreDraft = () => {
    if (!recoveredDraft || !customer || Number(recoveredDraft.customerId) !== Number(customer.id)) return;
    photos.forEach((photo) => URL.revokeObjectURL(photo.url));
    setOrderContact(recoveredDraft.contact);
    setSelectedPackage(String(recoveredDraft.packageId));
    setHandover(recoveredDraft.handover);
    setOrderNotes(recoveredDraft.notes || "");
    setClientRequestId(recoveredDraft.clientRequestId);
    setPhotos(recoveredDraft.photos.map((photo, index) => {
      const file = new File([photo.blob], photo.name, { type: photo.type, lastModified: photo.lastModified });
      return { id: `restored-${Date.now()}-${index}`, file, url: URL.createObjectURL(file), originalSize: photo.originalSize };
    }));
    setRecoveredDraft(null);
    setFormError("");
    setFormNotice(t.draftRestored);
    setTimeout(() => document.getElementById("order")?.scrollIntoView({ behavior: "smooth" }), 0);
  };

  const discardDraft = async () => {
    await clearOrderDraft().catch(() => {});
    setRecoveredDraft(null);
    setClientRequestId(newRequestId());
  };

  const removePhoto = (id) => {
    setPhotos((current) => {
      const removed = current.find((photo) => photo.id === id);
      if (removed) URL.revokeObjectURL(removed.url);
      return current.filter((photo) => photo.id !== id);
    });
  };

  const submitOrder = async (event) => {
    event.preventDefault();
    if (!customer) {
      setFormError(t.signInToOrder);
      setAccountMode("login");
      return;
    }
    if (!orderContact.name || !orderContact.phone || !orderContact.address || !selectedPackage || photos.length === 0) {
      setFormError(t.requiredError);
      return;
    }
    setFormError("");
    setFormNotice("");
    setSubmitting(true);
    const payload = new FormData();
    payload.append("fullName", orderContact.name);
    payload.append("address", orderContact.address);
    payload.append("packageId", selectedPackage);
    payload.append("handover", handover);
    payload.append("notes", orderNotes);
    payload.append("clientRequestId", clientRequestId);
    photos.forEach((photo) => payload.append("photos[]", photo.file, photo.file.name));
    try {
      let draftSaved = false;
      try {
        await saveOrderDraft({
          customerId: customer.id,
          contact: orderContact,
          packageId: selectedPackage,
          handover,
          notes: orderNotes,
          clientRequestId,
          photos: photos.map((photo) => ({
            blob: photo.file,
            name: photo.file.name,
            type: photo.file.type,
            lastModified: photo.file.lastModified,
            originalSize: photo.originalSize,
          })),
        });
        draftSaved = true;
      } catch { /* The upload can still proceed if private browser storage is unavailable. */ }
      if (!navigator.onLine) {
        setFormError(draftSaved ? t.draftSavedOffline : t.orderSubmitError);
        return;
      }
      const data = await accountApi.createOrder(payload);
      await clearOrderDraft().catch(() => {});
      setCustomer(data.customer);
      setOrderContact({ name: data.customer.fullName, phone: data.customer.phone, address: data.customer.address || "" });
      setSubmittedOrder(data.order);
      setSubmitted(true);
      photos.forEach((photo) => URL.revokeObjectURL(photo.url));
      setPhotos([]);
      setOrderNotes("");
      setClientRequestId(newRequestId());
      setTimeout(() => document.getElementById("order-result")?.focus(), 0);
    } catch (error) {
      const messages = {
        authentication_required: t.signInToOrder,
        package_unavailable: t.packageUnavailable,
        photo_count_invalid: t.photoLimitError,
        photo_size_invalid: t.photoSizeError,
        photo_type_invalid: t.photoTypeError,
      };
      setFormError(messages[error?.code] || (navigator.onLine ? t.orderSubmitError : t.draftSavedOffline));
      if (error?.status === 401) {
        setCustomer(null);
        setAccountMode("login");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const heroPackage = packages[Math.min(1, packages.length - 1)];
  const heroPackageCopy = heroPackage ? localizedPackage(heroPackage, language) : null;

  return (
    <div className={language === "mm" ? "app myanmar" : "app"}>
      <NetworkSignals t={t} />
      <header className="site-header">
        <div className="container header-inner">
          <button className="brand-button" onClick={() => scrollTo("top")} aria-label={t.brandName}><Logo /></button>
          <nav className={menuOpen ? "desktop-nav open" : "desktop-nav"} aria-label={t.mainNavigation}>
            <button onClick={() => scrollTo("services")}>{t.navServices}</button>
            <button onClick={() => scrollTo("process")}>{t.navProcess}</button>
            <button onClick={() => scrollTo("order")}>{t.navOrder}</button>
          </nav>
          <div className="header-actions">
            <button className="language-button" onClick={() => setLanguage(language === "en" ? "mm" : "en")}>
              <Languages size={17} /><span>{t.languageName}</span>
            </button>
            <button className="install-button" onClick={handleInstall}><Upload size={16} /><span>{t.install}</span></button>
            {customer ? (
              <button className="account-button signed-in" onClick={() => setAccountMode("profile")}>
                <span><UserRound size={16} />{unreadCount > 0 && <i className="account-unread" aria-label={`${unreadCount} ${t.newUpdates}`}>{unreadCount > 9 ? "9+" : unreadCount}</i>}</span><strong>{customer.fullName.split(" ")[0]}</strong>
              </button>
            ) : (
              <button className="account-button" onClick={() => setAccountMode("login")}><UserRound size={16} /><strong>{t.signIn}</strong></button>
            )}
            <button className="menu-button" onClick={() => setMenuOpen(!menuOpen)} aria-expanded={menuOpen} aria-label={t.toggleMenu}>
              {menuOpen ? <X /> : <Menu />}
            </button>
          </div>
        </div>
      </header>

      <main id="top" className="app-main" ref={mainScroll}>
        {recoveredDraft && customer && Number(recoveredDraft.customerId) === Number(customer.id) && (
          <aside className={`draft-recovery mobile-page mobile-page-order ${mobilePage === "order" ? "mobile-active" : ""}`} aria-live="polite">
            <div><strong>{t.draftFoundTitle}</strong><span>{t.draftFoundBody}</span></div>
            <button type="button" onClick={restoreDraft}>{t.restoreDraft}</button>
            <button type="button" className="draft-discard" onClick={discardDraft}>{t.discardDraft}</button>
          </aside>
        )}
        <section className={`hero-section mobile-page mobile-page-home ${mobilePage === "home" ? "mobile-active" : ""}`}>
          <div className="hero-glow hero-glow-one" />
          <div className="hero-glow hero-glow-two" />
          <div className="container hero-grid">
            <div className="hero-copy">
              <p className="eyebrow"><Sparkles size={15} />{t.eyebrow}</p>
              <h1>{t.heroTitleA}<br /><em>{t.heroTitleB}</em></h1>
              <p className="hero-body">{t.heroBody}</p>
              <div className="hero-actions">
                <button className="primary-button" onClick={() => scrollTo("order")}>{t.orderCta}<ArrowRight size={18} /></button>
                <button className="text-button" onClick={() => scrollTo("packages")}>{t.exploreCta}<ChevronRight size={18} /></button>
              </div>
            </div>
            <div className="hero-visual" aria-hidden="true">
              <div className="orbit orbit-one" />
              <div className="orbit orbit-two" />
              <div className="care-card main-card">
                <span className="card-kicker">EMC CARE / 01</span>
                <div className="shoe-mark"><Sparkles size={52} strokeWidth={1.3} /></div>
                <strong>{heroPackageCopy?.name || t.servicesTitle}</strong>
                {heroPackage && <span>{formatPrice(heroPackage.priceKs, language)} {t.ks}</span>}
                <div className="mini-progress"><i /><i /><i /></div>
              </div>
              <div className="floating-card pickup-card"><Truck size={22} /><span>{t.trustThreeTitle}</span></div>
              <div className="floating-card price-card"><ShieldCheck size={22} /><span>{t.trustTwoTitle}</span></div>
            </div>
          </div>
          <div className="container trust-strip">
            {[
              [ImagePlus, t.trustOneTitle, t.trustOneBody],
              [ShieldCheck, t.trustTwoTitle, t.trustTwoBody],
              [Truck, t.trustThreeTitle, t.trustThreeBody],
            ].map(([Icon, title, body]) => (
              <div className="trust-item" key={title}><span><Icon size={21} /></span><div><strong>{title}</strong><p>{body}</p></div></div>
            ))}
          </div>
        </section>

        <section className={`section services-section mobile-page mobile-page-services ${mobilePage === "services" ? "mobile-active" : ""}`} id="services">
          <div className="container">
            <div className="section-heading centered">
              <p className="eyebrow">{t.servicesEyebrow}</p>
              <h2>{t.servicesTitle}</h2>
              <p>{t.servicesBody}</p>
            </div>
            <div className="services-grid">
              {[
                [SprayCan, "01", t.serviceCleanTitle, t.serviceCleanBody],
                [Wrench, "02", t.serviceRepairTitle, t.serviceRepairBody],
                [Sparkles, "03", t.serviceFinishTitle, t.serviceFinishBody],
              ].map(([Icon, number, title, body]) => (
                <article className="service-card" key={number}>
                  <div className="service-top"><span className="service-icon"><Icon /></span><small>{number}</small></div>
                  <h3>{title}</h3><p>{body}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className={`section packages-section mobile-page mobile-page-services ${mobilePage === "services" ? "mobile-active" : ""}`} id="packages">
          <div className="container">
            <div className="section-heading split-heading">
              <div><p className="eyebrow">{t.packagesEyebrow}</p><h2>{t.packagesTitle}</h2></div>
              <p>{t.packagesBody}</p>
            </div>
            <div className="packages-grid">
              {packages.length === 0 && <p className="packages-empty">{t.noPackagesAvailable}</p>}
              {packages.map((pkg, index) => {
                const localized = localizedPackage(pkg, language);
                const isSelected = String(selectedPackage) === String(pkg.id);
                const featured = index === Math.min(1, packages.length - 1);
                return (
                <article className={`package-card ${featured ? "featured" : ""} ${isSelected ? "active" : ""}`} key={pkg.id}>
                  {featured && <span className="popular-label">{t.popular}</span>}
                  <div className="package-icon"><PackageCheck /></div>
                  <h3>{localized.name}</h3>
                  <p>{localized.description}</p>
                  <div className="price"><strong>{formatPrice(pkg.priceKs, language)}</strong><span>{t.ks}</span></div>
                  <ul><li><Check size={16} />{t.fixedPackagePrice}</li><li><Check size={16} />{t.photoReviewIncluded}</li></ul>
                  <button onClick={() => choosePackage(String(pkg.id))} className={isSelected ? "package-button selected" : "package-button"}>
                    {isSelected ? <><CircleCheck size={18} />{t.selected}</> : <>{t.selectPackage}<ArrowRight size={17} /></>}
                  </button>
                </article>
              )})}
            </div>
          </div>
        </section>

        <section className={`section process-section mobile-page mobile-page-process ${mobilePage === "process" ? "mobile-active" : ""}`} id="process">
          <div className="container process-grid">
            <div className="process-intro">
              <p className="eyebrow">{t.processEyebrow}</p>
              <h2>{t.processTitle}</h2>
              <div className="process-emblem"><Clock3 size={48} /><span>EMC</span></div>
            </div>
            <div className="steps">
              {[
                ["01", ImagePlus, t.stepOneTitle, t.stepOneBody],
                ["02", Truck, t.stepTwoTitle, t.stepTwoBody],
                ["03", CircleCheck, t.stepThreeTitle, t.stepThreeBody],
              ].map(([number, Icon, title, body]) => (
                <article className="step" key={number}>
                  <span className="step-number">{number}</span><span className="step-icon"><Icon /></span>
                  <div><h3>{title}</h3><p>{body}</p></div>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className={`section order-section mobile-page mobile-page-order ${mobilePage === "order" ? "mobile-active" : ""}`} id="order">
          <div className="container order-layout">
            <div className="order-intro">
              <span className="demo-badge">{t.demoBadge}</span>
              <p className="eyebrow">{t.formEyebrow}</p>
              <h2>{t.formTitle}</h2>
              <p>{t.formBody}</p>
              <div className="privacy-note"><ShieldCheck /><span>{t.privacy}</span></div>
            </div>

            <div className="form-card">
              {submitted ? (
                <div className="success-state" id="order-result" tabIndex="-1">
                  <span className="success-icon"><CircleCheck /></span>
                  <p className="eyebrow">EMC / {submittedOrder?.orderNumber}</p>
                  <h3>{t.successTitle}</h3>
                  <p>{t.successBody}</p>
                  {submittedOrder && <div className="submitted-total"><span>{t.orderTotal}</span><strong>{formatPrice(submittedOrder.totalPriceKs, language)} {t.ks}</strong></div>}
                  <button className="secondary-button" onClick={() => { setSubmitted(false); setSubmittedOrder(null); }}>{t.placeAnotherOrder}</button>
                </div>
              ) : (
                <form onSubmit={submitOrder} noValidate>
                  <div className="form-section-heading"><span>01</span><h3>{t.contactSection}</h3></div>
                  <div className="field-grid">
                    <label className="field"><span>{t.fullName} <small>{t.required}</small></span><input name="name" type="text" value={orderContact.name} onChange={(event) => setOrderContact((current) => ({ ...current, name: event.target.value }))} placeholder={t.fullNamePlaceholder} autoComplete="name" /></label>
                    <label className="field"><span>{t.phone} <small>{t.required}</small></span><input name="phone" type="tel" inputMode="tel" value={orderContact.phone} onChange={(event) => setOrderContact((current) => ({ ...current, phone: event.target.value }))} placeholder={t.phonePlaceholder} autoComplete="tel" readOnly={Boolean(customer)} /></label>
                    <label className="field full"><span>{t.address} <small>{t.required}</small></span><textarea name="address" rows="2" value={orderContact.address} onChange={(event) => setOrderContact((current) => ({ ...current, address: event.target.value }))} placeholder={t.addressPlaceholder} autoComplete="street-address" /></label>
                    <label className="field full"><span>{t.packageLabel} <small>{t.required}</small></span>
                      <select value={selectedPackage} onChange={(event) => setSelectedPackage(event.target.value)}>
                        <option value="">{t.choosePackagePlaceholder}</option>
                        {packages.map((pkg) => <option key={pkg.id} value={pkg.id}>{localizedPackage(pkg, language).name} — {formatPrice(pkg.priceKs, language)} {t.ks}</option>)}
                      </select>
                    </label>
                  </div>

                  <div className="form-section-heading spaced"><span>02</span><h3>{t.handoverSection}</h3></div>
                  <div className="handover-grid">
                    <label className={handover === "dropoff" ? "choice-card checked" : "choice-card"}>
                      <input type="radio" name="handover" value="dropoff" checked={handover === "dropoff"} onChange={() => setHandover("dropoff")} />
                      <span className="choice-icon"><Store /></span><span><strong>{t.dropoff}</strong><small>{t.dropoffBody}</small></span><i><Check /></i>
                    </label>
                    <label className={handover === "pickup" ? "choice-card checked" : "choice-card"}>
                      <input type="radio" name="handover" value="pickup" checked={handover === "pickup"} onChange={() => setHandover("pickup")} />
                      <span className="choice-icon"><Truck /></span><span><strong>{t.pickup}</strong><small>{pickupFee > 0 ? `${t.pickupFeeLabel}: ${formatPrice(pickupFee, language)} ${t.ks}` : t.pickupBody}</small></span><i><Check /></i>
                    </label>
                  </div>
                  <label className="field notes-field"><span>{t.notes}</span><textarea name="notes" rows="3" value={orderNotes} onChange={(event) => setOrderNotes(event.target.value)} placeholder={t.notesPlaceholder} /></label>

                  <div className="form-section-heading spaced"><span>03</span><h3>{t.photosSection}</h3></div>
                  <p className="field-hint">{t.photosHint}</p>
                  <button className="upload-zone" type="button" onClick={() => fileInput.current?.click()}>
                    <span><ImagePlus /></span><strong>{t.addPhotos}</strong><small>{t.photoAngles}</small>
                  </button>
                  <input ref={fileInput} className="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={handlePhotos} />
                  {photoError && <p className="error-message" role="alert">{photoError}</p>}
                  {photos.length > 0 && (
                    <div className="photo-summary"><span><CircleCheck size={16} />{photos.length}/10 {t.photosAdded}</span><small>{t.compressed}</small></div>
                  )}
                  <div className="photo-grid">
                    {photos.map((photo, index) => (
                      <div className="photo-preview" key={photo.id}>
                        <img src={photo.url} alt={`${t.photosSection} ${index + 1}`} loading="lazy" />
                        <button type="button" onClick={() => removePhoto(photo.id)} aria-label={t.remove}><X /></button>
                        <span>{Math.max(1, Math.round(photo.file.size / 1024))} KB</span>
                      </div>
                    ))}
                  </div>
                  {formError && <p className="error-message form-error" role="alert">{formError}</p>}
                  {formNotice && <p className="form-notice" role="status">{formNotice}</p>}
                  <div className="submit-row">
                    <button className="primary-button submit-button" type="submit" disabled={submitting}>{submitting ? t.submittingOrder : t.submit}<ArrowRight size={18} /></button>
                    <span><ShieldCheck size={16} />{t.submitNote}</span>
                  </div>
                </form>
              )}
            </div>
          </div>
        </section>
      </main>

      <footer>
        <div className="container footer-inner">
          <Logo />
          <p>{t.footerLine}</p>
          <span>{t.footerPhase}</span>
        </div>
      </footer>
      <nav className="mobile-tabbar" aria-label={t.mainNavigation}>
        <button type="button" className={mobilePage === "home" ? "active" : ""} onClick={() => openMobilePage("home")} aria-current={mobilePage === "home" ? "page" : undefined}>
          <House /><span>{t.navHome}</span>
        </button>
        <button type="button" className={mobilePage === "services" ? "active" : ""} onClick={() => openMobilePage("services")} aria-current={mobilePage === "services" ? "page" : undefined}>
          <PackageCheck /><span>{t.navServices}</span>
        </button>
        <button type="button" className={mobilePage === "process" ? "active" : ""} onClick={() => openMobilePage("process")} aria-label={t.navProcess} aria-current={mobilePage === "process" ? "page" : undefined}>
          <Clock3 /><span>{t.navProcessShort}</span>
        </button>
        <button type="button" className={mobilePage === "order" ? "active" : ""} onClick={() => openMobilePage("order")} aria-label={t.navOrder} aria-current={mobilePage === "order" ? "page" : undefined}>
          <ClipboardList /><span>{t.navOrderShort}</span>
        </button>
      </nav>
      {accountMode && (
        <AccountModal
          mode={accountMode}
          customer={customer}
          t={t}
          onClose={() => setAccountMode(null)}
          onAuthenticated={(nextCustomer) => { setCustomer(nextCustomer); setOrderContact({ name: nextCustomer.fullName, phone: nextCustomer.phone, address: nextCustomer.address || "" }); setAccountMode(null); }}
          onProfileUpdate={(nextCustomer) => { setCustomer(nextCustomer); setOrderContact({ name: nextCustomer.fullName, phone: nextCustomer.phone, address: nextCustomer.address || "" }); }}
          onLogout={() => { setCustomer(null); setUnreadCount(0); setOrderContact({ name: "", phone: "", address: "" }); setAccountMode(null); }}
          onUnreadChange={setUnreadCount}
        />
      )}
      <InstallGuide
        open={installGuideOpen}
        onClose={() => setInstallGuideOpen(false)}
        onInstall={handleInstall}
        canPrompt={Boolean(installPrompt)}
        installed={installed}
        t={t}
      />
    </div>
  );
}

export default App;
