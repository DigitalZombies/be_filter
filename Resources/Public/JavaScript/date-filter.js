import { html, nothing } from "lit";
import {
  AbstractFilterControl,
  label,
  registerFilterType,
} from "@digitalzombies/be_filter/filter-types.js";

/**
 * A from / to range of dates with quick ranges above it.
 */
class BeFilterDate extends AbstractFilterControl {
  static properties = {
    range: { state: true },
  };

  constructor() {
    super();
    this.range = {};
  }

  willUpdate(changed) {
    if (changed.has("value")) {
      this.range = { ...(this.value ?? {}) };
    }
  }

  renderControl() {
    const value = this.range;

    return html`
      <div class="be-filter-dates">
        ${(this.filter.presets ?? []).map((preset) => html`
          <button
            type="button"
            class="btn btn-sm ${value.preset === preset.key ? "btn-primary" : "btn-default"}"
            @click=${() => this.emit({ preset: preset.key })}
          >${preset.label}</button>`)}
      </div>
      <div class="be-filter-range">
        ${["from", "to"].map((boundary) => html`
          <label>
            <span>${this.caption(boundary)}</span>
            <input
              type="date"
              class="form-control"
              .value=${value[boundary] ?? ""}
              @change=${(event) => this.setBoundary(boundary, event.target.value)}
            >
          </label>`)}
      </div>
      ${Object.keys(this.range).length > 0 ? html`
        <div class="be-filter-menu-footer">
          <button type="button" class="btn btn-link btn-sm" @click=${() => this.emit(null)}>${label("clear")}</button>
        </div>` : nothing}`;
  }

  caption(boundary) {
    return this.filter.labels?.[boundary] ?? label(boundary);
  }

  setBoundary(boundary, date) {
    // Entering a date replaces a selected preset.
    this.range = { ...(this.range.preset ? {} : this.range), [boundary]: date };
    const cleaned = Object.fromEntries(Object.entries(this.range).filter(([, entry]) => entry !== ""));
    this.emit(Object.keys(cleaned).length > 0 ? cleaned : null);
  }

  summary() {
    const value = this.range;
    if (value.preset) {
      return (this.filter.presets ?? []).find((preset) => preset.key === value.preset)?.label ?? value.preset;
    }
    if (!value.from && !value.to) {
      return "";
    }

    return `${this.formatDate(value.from)} – ${this.formatDate(value.to)}`;
  }

  formatDate(date) {
    return date ? new Date(date).toLocaleDateString(document.documentElement.lang || undefined) : "…";
  }
}

customElements.define("be-filter-date", BeFilterDate);
registerFilterType("date", "be-filter-date");

export { BeFilterDate };
