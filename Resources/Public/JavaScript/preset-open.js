import { LitElement, html, nothing } from "lit";
import { live } from "lit/directives/live.js";
import Notification from "@typo3/backend/notification.js";
import { describeVisibility, formatDate, request, translate } from "@digitalzombies/be_filter/wizard/support.js";

/**
 * Opening a saved filter: search, select, apply. The list of filters is short lived
 * and the choice is a single one, so this is a dialog and not a wizard.
 */
class PresetOpen extends LitElement {
  static properties = {
    configuration: { type: Object },
    presets: { state: true },
    search: { state: true },
    selected: { state: true },
  };

  constructor() {
    super();
    this.configuration = null;
    this.presets = [];
    this.search = "";
    this.selected = null;
  }

  createRenderRoot() {
    return this;
  }

  willUpdate(changed) {
    if (changed.has("configuration") && this.configuration !== null) {
      this.presets = this.configuration.presets ?? [];
    }
  }

  render() {
    const matches = this.matches();

    return html`
      <div class="input-group mb-3">
        <label for="befilter-preset-search" class="visually-hidden">${translate("presets.search", "Search")}</label>
        <input
          type="search"
          autocomplete="off"
          id="befilter-preset-search"
          class="form-control"
          placeholder=${translate("presets.search", "Search by name, creator or date")}
          .value=${live(this.search)}
          @input=${(event) => { this.search = event.target.value; }}
        >
      </div>
      ${this.presets.length === 0
        ? html`<div role="alert" class="alert alert-info">${translate("presets.empty", "No saved filters")}</div>`
        : nothing}
      ${this.presets.length > 0 && matches.length === 0
        ? html`<div role="alert" class="alert alert-info">${translate("presets.noMatch", "No saved filter matches")}</div>`
        : nothing}
      <div class="form-check-card-container">
        ${matches.map((preset) => this.renderPreset(preset))}
      </div>`;
  }

  renderPreset(preset) {
    const owner = preset.own ? translate("presets.me", "Me") : preset.owner;
    const created = formatDate(preset.created);

    return html`
      <div
        class="form-check form-check-type-card"
        role="radio"
        tabindex="0"
        aria-checked=${this.selected?.uid === preset.uid ? "true" : "false"}
        @click=${(event) => this.pick(event, preset)}
        @dblclick=${(event) => this.pick(event, preset, true)}
        @keydown=${(event) => this.onKey(event, preset)}
      >
        <input
          class="form-check-input"
          type="radio"
          name="befilter-preset"
          id=${"befilter-preset-" + preset.uid}
          tabindex="-1"
          aria-hidden="true"
          .checked=${live(this.selected?.uid === preset.uid)}
        >
        <label class="form-check-label" for=${"befilter-preset-" + preset.uid}>
          <span class="form-check-label-header form-check-label-header-inherit">
            <typo3-backend-icon identifier="actions-filter" size="small"></typo3-backend-icon>
            ${preset.title}
          </span>
          <span class="form-check-label-body">
            <small>${owner}${created !== "" ? " · " + created : ""} · ${describeVisibility(preset)}</small>
          </span>
        </label>
        ${preset.editable ? html`
          <button
            type="button"
            class="btn btn-link"
            title=${translate("presets.delete", "Delete saved filter")}
            @click=${(event) => { event.stopPropagation(); this.remove(preset); }}
          >
            <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
          </button>` : nothing}
      </div>`;
  }

  matches() {
    const term = this.search.trim().toLowerCase();
    if (term === "") {
      return this.presets;
    }

    return this.presets.filter((preset) => {
      const owner = preset.own ? translate("presets.me", "Me") : preset.owner;

      return `${preset.title} ${owner} ${formatDate(preset.created)} ${describeVisibility(preset)}`
        .toLowerCase()
        .includes(term);
    });
  }

  /**
   * The backend listens for clicks on the document and turns some of them into modals
   * of its own. A pick stays inside the dialog, and the radio is only the indicator.
   */
  pick(event, preset, immediate = false) {
    event.preventDefault();
    event.stopPropagation();
    this.select(preset, immediate);
  }

  onKey(event, preset) {
    if (event.key === "Enter" || event.key === " ") {
      this.pick(event, preset, event.key === "Enter");
    }
  }

  select(preset, immediate = false) {
    this.selected = preset;
    this.dispatchEvent(new CustomEvent("befilter-preset-selected", {
      bubbles: true,
      detail: { preset, immediate },
    }));
  }

  async remove(preset) {
    try {
      const result = await request("be_filter_preset_delete", {
        table: this.configuration.table,
        preset: preset.uid,
      });
      this.presets = result.presets ?? [];
      if (this.selected?.uid === preset.uid) {
        this.select(null);
      }
    } catch (error) {
      Notification.error(translate("error", "The change could not be saved."), error?.message ?? "");
    }
  }
}

customElements.define("befilter-preset-open", PresetOpen);

export { PresetOpen };
