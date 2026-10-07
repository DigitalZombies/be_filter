import { html, nothing } from "lit";
import { live } from "lit/directives/live.js";
import {
  AbstractFilterControl,
  label,
  registerFilterType,
} from "@digitalzombies/be_filter/filter-types.js";

/**
 * A range of numbers, as two fields or as a slider.
 */
class BeFilterNumber extends AbstractFilterControl {
  static properties = {
    range: { state: true },
  };

  constructor() {
    super();
    this.filter = { name: "", label: "", number: {} };
    this.range = {};
  }

  // The second end may be entered before the parent answers, so the range is kept here.
  willUpdate(changed) {
    if (changed.has("value")) {
      this.range = { ...(this.value ?? {}) };
    }
  }

  get options() {
    return this.filter.number ?? {};
  }

  renderControl() {
    const control = this.options.render === "slider"
      ? this.renderSlider()
      : html`
        <div class="be-filter-range">
          ${this.renderBoundary("from", this.range.from)}
          ${this.renderBoundary("to", this.range.to)}
        </div>`;

    return html`
      ${control}
      ${Object.keys(this.range).length > 0 ? html`
        <div class="be-filter-menu-footer">
          <button type="button" class="btn btn-link btn-sm" @click=${() => this.clear()}>${label("clear")}</button>
        </div>` : nothing}`;
  }

  caption(boundary) {
    return this.options.labels?.[boundary] ?? label(boundary);
  }

  renderBoundary(boundary, current) {
    const options = this.options;

    return html`
      <label>
        <span>${this.caption(boundary)}</span>
        <input
          type="number"
          class="form-control"
          inputmode=${(options.decimals ?? 0) > 0 ? "decimal" : "numeric"}
          min=${options.min ?? nothing}
          max=${options.max ?? nothing}
          step=${options.step ?? nothing}
          .value=${live(current ?? "")}
          @change=${(event) => this.setBoundary(boundary, event.target.value)}
        >
      </label>`;
  }

  renderSlider() {
    const options = this.options;
    const mode = options.mode ?? "range";

    // Without both ends there is nothing to slide along, so the fields take over.
    if (options.min === null || options.min === undefined || options.max === null || options.max === undefined) {
      return html`
        <div class="be-filter-range">
          ${this.renderBoundary("from", this.range.from)}
          ${this.renderBoundary("to", this.range.to)}
        </div>`;
    }

    if (mode === "range") {
      return html`
        <div class="be-filter-slider">
          ${this.renderHandle("from", this.range.from ?? options.min, this.caption("from"), mode)}
          ${this.renderHandle("to", this.range.to ?? options.max, this.caption("to"), mode)}
        </div>`;
    }

    const boundary = mode === "min" ? "from" : "to";
    const fallback = mode === "min" ? options.min : options.max;

    return html`
      <div class="be-filter-slider">
        ${this.renderHandle(boundary, this.range[boundary] ?? fallback, this.filter.label, mode)}
      </div>`;
  }

  renderHandle(boundary, current, caption, mode) {
    const options = this.options;
    const value = current ?? options.min ?? 0;

    return html`
      <label>
        <span class="be-filter-slider-caption">${caption}</span>
        <input
          type="range"
          class="form-range"
          min=${options.min ?? nothing}
          max=${options.max ?? nothing}
          step=${options.step ?? nothing}
          .value=${live(String(value))}
          @input=${(event) => this.setHandle(boundary, event.target.value, mode)}
        >
        <output>${value}</output>
      </label>`;
  }

  setHandle(boundary, entry, mode) {
    if (mode !== "exact") {
      this.setBoundary(boundary, entry);
      return;
    }

    this.range = { from: entry, to: entry };
    this.emit({ from: entry, to: entry });
  }

  setBoundary(boundary, entry) {
    this.range = { ...this.range, [boundary]: entry };
    const cleaned = Object.fromEntries(Object.entries(this.range).filter(([, item]) => item !== "" && item !== null && item !== undefined));

    this.emit(Object.keys(cleaned).length > 0 ? cleaned : null);
  }

  clear() {
    this.range = {};
    this.emit(null);
  }

  summary() {
    const value = this.range;
    if (!value.from && !value.to) {
      return "";
    }
    if (value.from === value.to) {
      return String(value.from);
    }

    return `${value.from ?? "…"} – ${value.to ?? "…"}`;
  }
}

customElements.define("be-filter-number", BeFilterNumber);
registerFilterType("number", "be-filter-number");

export { BeFilterNumber };
