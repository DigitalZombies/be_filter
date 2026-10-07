import { html } from "lit";
import { live } from "lit/directives/live.js";
import {
  AbstractFilterControl,
  label,
  registerFilterType,
} from "@digitalzombies/be_filter/filter-types.js";

/**
 * A single checkbox, as a yes / no dropdown or a switch. It needs no dropdown of its own.
 */
class BeFilterCheckbox extends AbstractFilterControl {
  constructor() {
    super();
    this.filter = { name: "", label: "", checkbox: false };
  }

  renderShell(content) {
    return content;
  }

  renderControl() {
    const value = this.value ?? "";

    if (this.filter.checkbox) {
      return html`
        <div class="be-filter-control form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id=${"be-filter-" + this.filter.name}
            .checked=${live(value === "1")}
            @change=${(event) => this.emit(event.target.checked ? "1" : null)}
          >
          <label class="form-check-label" for=${"be-filter-" + this.filter.name}>${this.filter.label}</label>
        </div>`;
    }

    return html`
      <div class="be-filter-control input-group">
        <span class="input-group-text">${this.filter.label}</span>
        <select class="form-select" @change=${(event) => this.emit(event.target.value === "" ? null : event.target.value)}>
          <option value="" ?selected=${value === ""}>${label("any")}</option>
          <option value="1" ?selected=${value === "1"}>${label("yes")}</option>
          <option value="0" ?selected=${value === "0"}>${label("no")}</option>
        </select>
      </div>`;
  }
}

customElements.define("be-filter-checkbox", BeFilterCheckbox);
registerFilterType("checkbox", "be-filter-checkbox");

export { BeFilterCheckbox };
