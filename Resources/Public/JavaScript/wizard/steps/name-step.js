import { html } from "lit";
import { live } from "lit/directives/live.js";
import { translate } from "@digitalzombies/be_filter/wizard/support.js";

/**
 * First step of saving: the name the filter is listed under.
 */
export default class NameStep {
  constructor(context) {
    this.context = context;
    this.key = "name";
    this.title = translate("presets.name", "Name");
    this.autoAdvance = false;
    this.name = context.getStoreData(this.key) ?? "";
  }

  isComplete() {
    return this.name.trim() !== "";
  }

  render() {
    const configuration = this.context.configuration;
    const values = Object.keys(configuration.values ?? {}).length;
    const visible = (configuration.visible ?? []).length;

    return html`
      <h2 class="h4">${translate("wizard.save.name.headline", "Name this filter")}</h2>
      <p>${translate("presets.contents", "Saved with the current filter values and the displayed filters.")}</p>
      <div class="form-group">
        <label class="form-label" for="befilter-preset-name">${this.title}</label>
        <input
          type="text"
          class="form-control"
          id="befilter-preset-name"
          autocomplete="off"
          .value=${live(this.name)}
          @input=${(event) => this.setValue(event.target.value)}
        >
      </div>
      <p>
        <small>
          ${translate("preset.values", "Filter values")}: ${values} ·
          ${translate("preset.visibleFilters", "Displayed filters")}: ${visible}
        </small>
      </p>
      <p><small>${translate("presets.overwriteHint", "A saved filter of yours with the same name is replaced.")}</small></p>`;
  }

  setValue(name) {
    this.name = name;
    this.context.wizard.requestUpdate();
  }

  getValue() {
    return this.name.trim();
  }

  reset() {
    this.setValue("");
    this.context.clearStoreData(this.key);
  }

  beforeAdvance() {
    this.context.setStoreData(this.key, this.getValue());
  }

  getSummaryData() {
    return [{ label: this.title, value: this.getValue() }];
  }
}
