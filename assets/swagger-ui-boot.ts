/**
 * Swagger UI boot without inline script (CSP: /admin/api/doc still allows unsafe-eval).
 * Expects #swagger-ui-boot type=application/json with swagger_ui_config.
 */
function boot(): void {
  const el = document.getElementById('swagger-ui-boot');
  if (!(el instanceof HTMLScriptElement)) {
    return;
  }
  let config: unknown = {};
  try {
    config = JSON.parse(el.textContent ?? '{}');
  } catch {
    config = {};
  }
  const load = (window as unknown as { loadSwaggerUI?: (cfg: unknown) => void }).loadSwaggerUI;
  if (typeof load === 'function') {
    load(config);
  }
}

if (document.readyState === 'complete') {
  boot();
} else {
  window.addEventListener('load', boot);
}
