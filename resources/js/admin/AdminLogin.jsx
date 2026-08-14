import { useState } from "react";
import { ArrowRight, Eye, EyeOff, Languages, ShieldCheck } from "lucide-react";
import { adminApi } from "../api/adminClient";
import AdminLogo from "./AdminLogo";

function AdminLogin({ language, setLanguage, t, onLogin }) {
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const submit = async (event) => {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    setBusy(true);
    setError("");
    try {
      const result = await adminApi.login({ username: data.get("username"), password: data.get("password") });
      onLogin(result.admin);
    } catch (requestError) {
      setError(requestError?.code === "invalid_admin_credentials" ? t.invalidAdminCredentials : t.adminUnavailable);
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="admin-login-page">
      <div className="admin-login-language"><button onClick={() => setLanguage(language === "en" ? "mm" : "en")}><Languages />{t.languageName}</button></div>
      <section className="admin-login-card">
        <AdminLogo />
        <span className="admin-portal-badge"><ShieldCheck />{t.adminPortal}</span>
        <h1>{t.adminLoginTitle}</h1>
        <p>{t.adminLoginIntro}</p>
        <form onSubmit={submit}>
          <label><span>{t.username}</span><input name="username" autoComplete="username" placeholder={t.usernamePlaceholder} required /></label>
          <label><span>{t.password}</span><div className="admin-password"><input name="password" type={showPassword ? "text" : "password"} autoComplete="current-password" placeholder={t.adminPasswordPlaceholder} required /><button type="button" onClick={() => setShowPassword(!showPassword)} aria-label={showPassword ? t.hidePassword : t.showPassword}>{showPassword ? <EyeOff /> : <Eye />}</button></div></label>
          {error && <p className="admin-error" role="alert">{error}</p>}
          <button className="admin-primary" disabled={busy}>{busy ? t.adminSigningIn : t.adminSignIn}<ArrowRight /></button>
        </form>
        <p className="admin-security-note"><ShieldCheck />{t.adminSecurity}</p>
      </section>
      <div className="admin-login-decoration" aria-hidden="true"><span>EMC</span></div>
    </main>
  );
}

export default AdminLogin;
