import { html, nothing } from "lit";
import { live } from "lit/directives/live.js";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import {
  AbstractFilterControl,
  failed,
  label,
  registerFilterType,
} from "@digitalzombies/be_filter/filter-types.js";

/**
 * Values of a relation or of the items of a column, searched on the server.
 */
class BeFilterSelect extends AbstractFilterControl {
  static properties = {
    context: { type: Object },
    table: { type: String },
    pageId: { type: Number },
    options: { state: true },
    total: { state: true },
    search: { state: true },
    loading: { state: true },
  };

  constructor() {
    super();
    this.filter = { name: "", label: "", multiple: false, selected: [] };
    this.context = {};
    this.table = "";
    this.pageId = 0;
    this.options = [];
    this.total = 0;
    this.search = "";
    this.loading = false;
    this.searchTimer = 0;
    this.knownLabels = {};
    this.order = [];
  }

  // Controls are handed NULL when the filter is not set.
  get selection() {
    return this.value ?? [];
  }

  willUpdate(changed) {
    if (changed.has("filter")) {
      (this.filter.selected ?? []).forEach((entry) => {
        this.knownLabels[entry.value] = entry.label;
      });
    }
  }

  renderControl() {
    const entries = this.entries();

    return html`
      <div class="be-filter-menu-search">
        <input type="search" class="form-control" placeholder=${label("search")} .value=${this.search} @input=${this.onSearch}>
      </div>
      ${this.loading ? html`<div class="be-filter-menu-info">${label("loading")}</div>` : nothing}
      ${!this.loading && entries.length === 0 ? html`<div class="be-filter-menu-info">${label("noResults")}</div>` : nothing}
      <ul class="be-filter-options">
        ${entries.map((entry) => html`
          <li>
            <label class="be-filter-option form-check form-check-type-toggle">
              <input
                class="form-check-input"
                type=${this.filter.multiple ? "checkbox" : "radio"}
                name=${"be-filter-" + this.filter.name}
                .checked=${live(this.selection.includes(entry.value))}
                @change=${() => this.toggle(entry)}
              >
              <span>${entry.label}</span>
            </label>
          </li>`)}
      </ul>
      ${!this.loading && this.total > this.options.length ? html`
        <div class="be-filter-menu-info">
          ${label("options.truncated").replace("%1", String(this.options.length)).replace("%2", String(this.total))}
        </div>` : nothing}
      ${this.selection.length > 0 ? html`
        <div class="be-filter-menu-footer">
          <button type="button" class="btn btn-link btn-sm" @click=${() => this.emit([])}>${label("clear")}</button>
        </div>` : nothing}`;
  }

  entries() {
    const pool = new Map();
    this.selection.forEach((value) => pool.set(value, { value, label: this.knownLabels[value] ?? value }));
    this.options.forEach((option) => {
      if (!pool.has(option.value)) {
        pool.set(option.value, option);
      }
    });

    const entries = [];
    this.order.forEach((value) => {
      if (pool.has(value)) {
        entries.push(pool.get(value));
        pool.delete(value);
      }
    });

    return [...entries, ...pool.values()];
  }

  // Taken when the list appears: entries must not move under the pointer.
  rememberOrder() {
    this.order = [
      ...this.selection,
      ...this.options.map((option) => option.value).filter((value) => !this.selection.includes(value)),
    ];
  }

  summary() {
    if (this.selection.length === 0) {
      return "";
    }

    return this.selection.length === 1
      ? this.knownLabels[this.selection[0]] ?? this.selection[0]
      : label("selected").replace("%d", String(this.selection.length));
  }

  opened() {
    this.querySelector("input[type=search]")?.focus();
    this.rememberOrder();
    this.loadOptions();
  }

  onSearch(event) {
    this.search = event.target.value;
    window.clearTimeout(this.searchTimer);
    this.searchTimer = window.setTimeout(() => this.loadOptions(), 250);
  }

  loadOptions() {
    this.loading = true;
    new AjaxRequest(TYPO3.settings.ajaxUrls.be_filter_options)
      .withQueryArguments({
        table: this.table,
        filter: this.filter.name,
        id: this.pageId,
        search: this.search,
        values: JSON.stringify(this.context),
      })
      .get()
      .then(async (response) => {
        const result = await response.resolve();
        this.options = result.options ?? [];
        this.total = result.total ?? this.options.length;
        this.options.forEach((option) => {
          this.knownLabels[option.value] = option.label;
        });
        this.rememberOrder();
      })
      .catch(failed)
      .finally(() => {
        this.loading = false;
      });
  }

  toggle(entry) {
    if (!this.filter.multiple) {
      this.emit([entry.value]);
      return;
    }
    this.emit(
      this.selection.includes(entry.value)
        ? this.selection.filter((value) => value !== entry.value)
        : [...this.selection, entry.value]
    );
  }

  // An empty selection is no filter at all.
  emit(value) {
    super.emit(value.length > 0 ? value : null);
  }
}

customElements.define("be-filter-select", BeFilterSelect);
registerFilterType("select", "be-filter-select");

export { BeFilterSelect };
