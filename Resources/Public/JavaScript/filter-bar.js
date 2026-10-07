import { LitElement, html, nothing } from "lit";
import { html as staticHtml, unsafeStatic } from "lit/static-html.js";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal from "@typo3/backend/modal.js";
import { SeverityEnum } from "@typo3/backend/enum/severity.js";
import {
  failed,
  label,
  onFilterTypeRegistered,
  resolveFilterTag,
  warn,
} from "@digitalzombies/be_filter/filter-types.js";
import { TransferList } from "@digitalzombies/be_filter/transfer-list.js";
import { openPresetOpen, openPresetSave } from "@digitalzombies/be_filter/preset-dialogs.js";
import "@typo3/backend/element/icon-element.js";

const send = (route, payload) =>
  new AjaxRequest(TYPO3.settings.ajaxUrls[route]).post(payload).then(async (response) => {
    const result = await response.resolve();
    if (result.success !== true) {
      throw new Error(result.message ?? "");
    }
    return result;
  });

// Values are kept on the server, so the list is reloaded. Paging starts over.
const reload = () => {
  const url = new URL(window.location.href);
  url.hash = "";
  url.searchParams.delete("pointer");
  if (url.toString() === window.location.href.split("#")[0]) {
    window.location.reload();
  } else {
    window.location.href = url.toString();
  }
};

const ADJUSTED_KEY = "be_filter.adjusted";

const fingerprint = (values) =>
  JSON.stringify(Object.keys(values ?? {}).sort().map((name) => [name, values[name]]));

class BeFilterBar extends LitElement {
  static properties = {
    configuration: { type: Object },
    values: { state: true },
  };

  constructor() {
    super();
    this.configuration = { table: "", pageId: 0, filters: [], visible: [], values: {}, columns: [], autoRefresh: false };
    this.values = {};
    this.initialized = false;
    this.applyTimer = 0;
    this.stopListening = undefined;
  }

  createRenderRoot() {
    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    this.addEventListener("be-filter-change", (event) => {
      event.stopPropagation();
      this.setValue(event.detail.name, event.detail.value);
    });
    // A control whose module runs after this one would otherwise never be rendered.
    this.stopListening = onFilterTypeRegistered(() => this.requestUpdate());
    this.reportAdjustment();
  }

  disconnectedCallback() {
    this.stopListening?.();
    this.stopListening = undefined;
    super.disconnectedCallback();
  }

  willUpdate(changed) {
    if (!this.initialized && changed.has("configuration")) {
      this.values = { ...(this.configuration.values ?? {}) };
      this.initialized = true;
    }
  }

  render() {
    const filters = this.visibleFilters();
    const hasFilters = this.configuration.filters.length > 0;

    return html`
      <div class="be-filter-bar">
        <div class="be-filter-controls">
          ${filters.map((filter) => this.renderFilter(filter))}
          ${hasFilters && filters.length === 0 ? html`<span class="be-filter-menu-info">${label("filters.empty")}</span>` : nothing}
        </div>
        <div class="be-filter-actions">
          <button
            type="button"
            class="btn btn-default"
            ?disabled=${Object.keys(this.values).length === 0}
            @click=${this.reset}
          >${label("reset")}</button>
          ${!this.configuration.autoRefresh && this.isDirty()
            ? html`<button type="button" class="btn btn-primary" @click=${this.apply}>${label("apply")}</button>`
            : nothing}
          <div class="be-filter-actions-end">
            ${hasFilters ? html`
              <button type="button" class="btn btn-default" @click=${this.openSave}>
                <typo3-backend-icon identifier="actions-save" size="small"></typo3-backend-icon>
                ${label("presets.saveButton")}
              </button>
              <button type="button" class="btn btn-default" @click=${this.openLoad}>
                <typo3-backend-icon identifier="actions-bookmark" size="small"></typo3-backend-icon>
                ${label("presets.openButton")}
              </button>` : nothing}
            <button
              type="button"
              class="btn btn-default"
              ?disabled=${!hasFilters}
              title=${hasFilters ? label("filters.title") : label("filters.none")}
              @click=${this.configureFilters}
            >
              <typo3-backend-icon identifier="actions-filter" size="small"></typo3-backend-icon>
              ${label("filters")}
            </button>
            <button type="button" class="btn btn-default" @click=${this.configureColumns}>
              <typo3-backend-icon identifier="actions-options" size="small"></typo3-backend-icon>
              ${label("columns")}
            </button>
          </div>
        </div>
      </div>`;
  }

  // A type without a registered control is skipped, the rest of the bar keeps working.
  renderFilter(filter) {
    const tag = resolveFilterTag(filter.type);
    if (tag === null) {
      return nothing;
    }

    const control = unsafeStatic(tag);

    return staticHtml`<${control}
      .filter=${filter}
      .value=${this.values[filter.name] ?? null}
      .context=${this.values}
      .table=${this.configuration.table}
      .pageId=${this.configuration.pageId}
    ></${control}>`;
  }

  visibleFilters() {
    return this.configuration.visible
      .map((name) => this.configuration.filters.find((filter) => filter.name === name))
      .filter((filter) => filter !== undefined);
  }

  isDirty() {
    return fingerprint(this.values) !== fingerprint(this.configuration.values);
  }

  setValue(name, value) {
    const values = { ...this.values };
    if (value === null) {
      delete values[name];
    } else {
      values[name] = value;
    }
    this.values = this.clearDependents(values, name);

    if (this.configuration.autoRefresh) {
      this.scheduleApply();
    }
  }

  // Debounced, so typing in a range does not send a request per keystroke.
  scheduleApply() {
    window.clearTimeout(this.applyTimer);
    this.applyTimer = window.setTimeout(() => this.apply(), 400);
  }

  // A dependent filter is narrowed by its parents, so its value cannot survive them.
  clearDependents(values, name) {
    const cleared = { ...values };
    const queue = [name];
    while (queue.length > 0) {
      const parent = queue.shift();
      this.configuration.filters
        .filter((filter) => (filter.dependsOn ?? []).includes(parent) && cleared[filter.name] !== undefined)
        .forEach((filter) => {
          delete cleared[filter.name];
          queue.push(filter.name);
        });
    }

    return cleared;
  }

  openSave() {
    openPresetSave({
      table: this.configuration.table,
      pageId: this.configuration.pageId,
      values: this.values,
      visible: this.visibleFilters().map((filter) => filter.name),
    });
  }

  openLoad() {
    openPresetOpen({
      table: this.configuration.table,
      pageId: this.configuration.pageId,
      onApply: (preset) => this.applyPreset(preset),
    });
  }

  /**
   * A saved filter restores its values and the filters it was displayed with.
   */
  applyPreset(preset) {
    send("be_filter_apply", {
      table: this.configuration.table,
      id: this.configuration.pageId,
      values: JSON.stringify(preset.values ?? {}),
      visible: JSON.stringify(preset.visible ?? []),
    })
      .then((result) => {
        this.rememberAdjustment(result?.adjusted ?? []);
        reload();
      })
      .catch(failed);
  }

  /**
   * The listing is reloaded, so a notice about dropped values has to survive this
   * document. It is read once, by the bar that comes up next.
   */
  rememberAdjustment(adjusted) {
    if (adjusted.length === 0) {
      return;
    }
    try {
      window.sessionStorage.setItem(ADJUSTED_KEY, JSON.stringify(adjusted));
    } catch {
      warn(adjusted.join(", "));
    }
  }

  reportAdjustment() {
    let adjusted = null;
    try {
      adjusted = window.sessionStorage.getItem(ADJUSTED_KEY);
      window.sessionStorage.removeItem(ADJUSTED_KEY);
    } catch {
      return;
    }
    if (adjusted === null) {
      return;
    }
    try {
      const filters = JSON.parse(adjusted);
      if (Array.isArray(filters) && filters.length > 0) {
        warn(filters.join(", "));
      }
    } catch {
      // a notice nobody can read is not worth a broken bar
    }
  }

  apply() {
    send("be_filter_apply", { table: this.configuration.table, id: this.configuration.pageId, values: JSON.stringify(this.values) })
      .then(reload)
      .catch(failed);
  }

  reset() {
    this.values = {};
    this.apply();
  }

  configureFilters() {
    const visible = this.configuration.visible;
    const items = [...this.configuration.filters]
      .sort((a, b) => this.visibleIndex(visible, a) - this.visibleIndex(visible, b))
      .map((filter) => ({ value: filter.name, label: filter.label, selected: visible.includes(filter.name) }));
    const transfer = new TransferList(items);

    this.openTransfer(label("filters.title"), transfer, () =>
      send("be_filter_layout", {
        table: this.configuration.table,
        visible: JSON.stringify(transfer.getSelected()),
      })
    );
  }

  configureColumns() {
    const transfer = new TransferList(
      this.configuration.columns.map((column) => ({
        value: column.name,
        label: column.label,
        description: column.name,
        selected: column.selected,
        fixed: column.fixed,
      })),
      { searchable: true }
    );

    this.openTransfer(label("columns.title"), transfer, () =>
      send("be_filter_columns", {
        table: this.configuration.table,
        columns: JSON.stringify(transfer.getSelected()),
      })
    );
  }

  openTransfer(title, transfer, save) {
    Modal.advanced({
      title,
      content: transfer.element,
      severity: SeverityEnum.notice,
      size: Modal.sizes.large,
      buttons: [
        {
          text: label("button.cancel"),
          btnClass: "btn-default",
          name: "cancel",
          trigger: (event, modal) => modal.hideModal(),
        },
        {
          text: label("button.save"),
          btnClass: "btn-primary",
          name: "save",
          trigger: (event, modal) => {
            save()
              .then(() => {
                modal.hideModal();
                reload();
              })
              .catch(failed);
          },
        },
      ],
    });
  }

  visibleIndex(visible, filter) {
    const index = visible.indexOf(filter.name);

    return index === -1 ? Number.MAX_SAFE_INTEGER : index;
  }
}

customElements.define("be-filter-bar", BeFilterBar);

// The class is exported so it can be tested without a browser.
export { BeFilterBar };
