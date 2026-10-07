/**
 * The globals the modules expect from a browser. Imported first by every test.
 */
globalThis.customElements ??= { define() {}, get: () => undefined, whenDefined: async () => {} };
globalThis.window ??= globalThis;
globalThis.sessionStorage ??= (() => {
  let store = {};
  return {
    getItem: (k) => (k in store ? store[k] : null),
    setItem: (k, v) => { store[k] = String(v); },
    removeItem: (k) => { delete store[k]; },
    clear: () => { store = {}; },
  };
})();
globalThis.window.sessionStorage ??= globalThis.sessionStorage;
globalThis.document ??= { documentElement: { lang: "en" }, createElement: () => ({ style: {}, classList: { add() {}, remove() {} } }) };
globalThis.TYPO3 ??= { settings: { ajaxUrls: { be_filter_options: "/options", be_filter_apply: "/apply", be_filter_layout: "/layout", be_filter_presets: "/presets", be_filter_preset_save: "/save", be_filter_preset_delete: "/delete", be_filter_columns: "/columns" } } };
