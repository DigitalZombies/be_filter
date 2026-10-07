import { LitElement, html, nothing } from "lit";
import Notification from "@typo3/backend/notification.js";
import labels from "~labels/be_filter.messages";

/**
 * The controls of the filter bar, by filter type. See the README for how to add one.
 */
const controls = new Map();
const listeners = new Set();

export const registerFilterType = (type, tagName) => {
  controls.set(type, tagName);
  listeners.forEach((listener) => listener(type, tagName));
};

export const resolveFilterTag = (type) => controls.get(type) ?? null;

export const registeredFilterTypes = () => [...controls.keys()];

/**
 * The backend loads every module as its own async script, so a control registers whenever
 * its module happens to run — before or after the bar was rendered. Whoever renders
 * filters listens here and renders again. Returns the function that stops listening.
 */
export const onFilterTypeRegistered = (listener) => {
  listeners.add(listener);

  return () => listeners.delete(listener);
};

// A missing label must not take the bar down, the English source stands in.
const FALLBACKS = {
  "any": "Any",
  "apply": "Apply",
  "button.cancel": "Cancel",
  "button.save": "Save",
  "clear": "Clear",
  "columns": "Columns",
  "columns.title": "Displayed columns",
  "error": "The change could not be saved.",
  "filters": "Filters",
  "filters.empty": "No filters displayed",
  "filters.none": "This table has no filters",
  "filters.title": "Displayed filters",
  "from": "From",
  "loading": "Loading",
  "no": "No",
  "noResults": "No results",
  "options.truncated": "%1 of %2 shown, search to narrow them down",
  "presets.adjusted": "Not everything in this filter is available to you",
  "presets.openButton": "Open",
  "presets.saveButton": "Save",
  "reset": "Reset",
  "search": "Search",
  "selected": "%d selected",
  "to": "To",
  "yes": "Yes",
};

export const label = (key) => {
  try {
    return labels.get(key);
  } catch {
    return FALLBACKS[key] ?? key;
  }
};

export const failed = (error) => Notification.error(label("error"), error?.message ?? "");

export const warn = (message) => Notification.warning(label("presets.adjusted"), message);

/**
 * What every control is built on: fill in renderControl(), the rest is optional.
 */
export class AbstractFilterControl extends LitElement {
  static properties = {
    filter: { type: Object },
    value: { type: Object },
  };

  constructor() {
    super();
    this.filter = { name: "", label: "" };
    this.value = null;
  }

  createRenderRoot() {
    return this;
  }

  renderControl() {
    throw new Error(`The filter control of "${this.filter.name}" has to implement renderControl()`);
  }

  summary() {
    return "";
  }

  opened() {}

  emit(value) {
    this.dispatchEvent(new CustomEvent("be-filter-change", {
      bubbles: true,
      detail: { name: this.filter.name, value },
    }));
  }

  render() {
    return this.renderShell(this.renderControl());
  }

  // A control that is not a dropdown, a switch for example, returns its own markup.
  renderShell(content) {
    const summary = this.summary();

    return html`
      <div class="dropdown be-filter-control">
        <button
          type="button"
          class="btn btn-default dropdown-toggle"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          aria-expanded="false"
        >
          <span>${this.filter.label}</span>${summary !== "" ? html`<span class="be-filter-control-value">${summary}</span>` : nothing}
        </button>
        <div class="dropdown-menu be-filter-menu" @toggle=${(event) => this.onToggle(event)}>
          ${content}
        </div>
      </div>`;
  }

  // The backend renders dropdowns as native popovers, which report their state here.
  onToggle(event) {
    if (event.newState === "open") {
      this.opened(event);
    }
  }
}
