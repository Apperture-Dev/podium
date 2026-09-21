import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  {
    // Los únicos <img> de la app son los logos SVG locales de public/. next/image
    // no optimiza SVG —su propia documentación recomienda `unoptimized`—, así que
    // no aportaría nada, y el wrapper que añade rompe las variantes
    // `group-data-[collapsible=icon]:*` con las que el sidebar alterna entre el
    // lockup y la marca. La regla sigue activa en el resto del proyecto, para que
    // un <img> raster futuro sí se avise.
    files: ["src/app/login/page.tsx", "src/components/shell/app-sidebar.tsx"],
    rules: { "@next/next/no-img-element": "off" },
  },
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
]);

export default eslintConfig;
