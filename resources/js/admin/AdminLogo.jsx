import { appBaseFromModuleUrl } from "../api/baseUrl";

const emcIcon = `${appBaseFromModuleUrl(import.meta.url)}/emcicon.jpg`;

function AdminLogo() {
  return (
    <div className="admin-logo">
      <span><img src={emcIcon} alt="" /></span>
      <div><strong>EMC</strong><small>Shoes Care Myanmar</small></div>
    </div>
  );
}

export default AdminLogo;
