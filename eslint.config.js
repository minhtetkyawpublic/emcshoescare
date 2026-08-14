import js from "@eslint/js";
import globals from "globals";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";
import jsxA11y from "eslint-plugin-jsx-a11y";

export default [
  { ignores: ["public/build/**", "node_modules/**", "vendor/**"] },
  js.configs.recommended,
  {
    files: ["**/*.{js,jsx}"],
    plugins: { react },
    rules: { "react/jsx-uses-vars": "error" },
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
      parserOptions: { ecmaVersion: "latest", ecmaFeatures: { jsx: true }, sourceType: "module" },
    },
  },
  { ...reactHooks.configs.flat["recommended-latest"], files: ["src/**/*.{js,jsx}"] },
  { ...reactRefresh.configs.vite, files: ["src/**/*.{js,jsx}"] },
  { ...jsxA11y.flatConfigs.recommended, files: ["src/**/*.{js,jsx}"] },
];
