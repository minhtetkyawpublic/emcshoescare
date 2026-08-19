import { appBaseFromModuleUrl } from "../api/baseUrl";

const emcIcon = `${appBaseFromModuleUrl(import.meta.url)}/emcicon.jpg`;

function AdminLogo() {
  return (
    <div className="admin-logo brand-lockup">
      <span className="logo-frame"><img src={emcIcon} alt="EMC" /></span>
      <div><strong>EMC</strong><small>Shoes Care Myanmar</small></div>
    </div>
  );
}

export default AdminLogo;
