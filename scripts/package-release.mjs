import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const releaseRoot = join(projectRoot, "dist");
const packageMetadata = JSON.parse(readFileSync(join(projectRoot, "package.json"), "utf8"));

if (!existsSync(join(releaseRoot, "index.html"))) {
  throw new Error("Vite output is missing. Run this script after vite build.");
}

function copy(source, destination, filter = undefined) {
  cpSync(join(projectRoot, source), join(releaseRoot, destination), {
    recursive: true,
    force: true,
    filter,
  });
}

copy("hosting/shared-hosting.htaccess", ".htaccess");
copy("api", "api", (source) => !source.endsWith(`${sep}config.local.php`));
copy("database/migrations", "api/cli/migrations");
copy("storage/.htaccess", "storage/.htaccess");
mkdirSync(join(releaseRoot, "storage/order-photos"), { recursive: true });
writeFileSync(join(releaseRoot, "storage/order-photos/.gitkeep"), "", "utf8");

// Remove files that should never be published if they survived a non-clean local copy.
for (const forbidden of ["api/config.local.php", "storage/order-photos/.htaccess"]) {
  rmSync(join(releaseRoot, forbidden), { force: true });
}

writeFileSync(join(releaseRoot, ".release.json"), `${JSON.stringify({
  format: 1,
  application: "EMC Shoes Care Myanmar",
  shortName: "EMC",
  version: packageMetadata.version,
  entrypoint: "index.html",
  api: "api/index.php",
  migrations: "api/cli/migrations",
}, null, 2)}\n`, "utf8");

const packagedApi = relative(projectRoot, join(releaseRoot, "api")).replaceAll(sep, "/");
process.stdout.write(`Packaged shared-hosting release in dist/ (${packagedApi}).\n`);
