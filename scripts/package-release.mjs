import { cpSync, existsSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
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

rmSync(join(releaseRoot, "api"), { recursive: true, force: true });
rmSync(join(releaseRoot, "storage"), { recursive: true, force: true });
copy("hosting/shared-hosting.htaccess", ".htaccess");
copy("hosting/laravel-api.htaccess", "api/.htaccess");
copy("hosting/laravel-api-index.php", "api/index.php");

writeFileSync(join(releaseRoot, ".release.json"), `${JSON.stringify({
  format: 1,
  application: "EMC Shoes Care Myanmar",
  shortName: "EMC",
  version: packageMetadata.version,
  entrypoint: "index.html",
  api: "api/index.php",
  framework: "Laravel 12",
  runtime: "private backend/ checkout",
  migrations: "backend/database/migrations",
}, null, 2)}\n`, "utf8");

process.stdout.write("Packaged shared-hosting release in dist/ with Laravel API bridge.\n");
