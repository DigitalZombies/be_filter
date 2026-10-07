import { html, nothing } from "lit";
import { live } from "lit/directives/live.js";
import { translate } from "@digitalzombies/be_filter/wizard/support.js";

/**
 * Second step of saving: who the filter is shared with. Backend groups are only
 * asked for when the filter is shared with groups.
 */
export default class VisibilityStep {
  constructor(context) {
    this.context = context;
    this.key = "visibility";
    this.title = translate("presets.visibility", "Visible for");
    this.autoAdvance = false;

    const stored = context.getStoreData(this.key) ?? {};
    this.visibility = stored.visibility ?? "private";
    this.groups = stored.groups ?? [];
  }

  get options() {
    return [
      ["private", translate("visibility.private", "Only me"), "actions-lock"],
      ["groups", translate("visibility.groups", "Selected backend groups"), "status-user-group-backend"],
      ["public", translate("visibility.public", "Everyone"), "actions-users"],
    ];
  }

  isComplete() {
    return this.visibility !== "groups" || this.groups.length > 0;
  }

  render() {
    const groups = this.context.configuration.groups ?? [];

    return html`
      <h2 class="h4">${translate("wizard.save.share.headline", "Who can use this filter")}</h2>
      <div class="form-check-card-container">
        ${this.options.map(([value, caption, icon]) => html`
          <div class="form-check form-check-type-card">
            <input
              class="form-check-input"
              type="radio"
              name="befilter-visibility"
              id=${"befilter-visibility-" + value}
              value=${value}
              .checked=${live(this.visibility === value)}
              @change=${() => this.setVisibility(value)}
            >
            <label class="form-check-label" for=${"befilter-visibility-" + value}>
              <span class="form-check-label-header form-check-label-header-inherit">
                <typo3-backend-icon identifier=${icon} size="small"></typo3-backend-icon>
                ${caption}
              </span>
            </label>
          </div>`)}
      </div>
      ${this.visibility === "groups" ? this.renderGroups(groups) : nothing}`;
  }

  renderGroups(groups) {
    if (groups.length === 0) {
      return html`
        <div role="alert" class="alert alert-info">
          ${translate("presets.groupsEmpty", "You are not a member of any backend group")}
        </div>`;
    }

    return html`
      <fieldset class="form-group">
        <legend class="form-legend">${translate("presets.groups", "Backend groups")}</legend>
        ${groups.map((group) => html`
          <div class="form-check form-check-type-toggle">
            <input
              class="form-check-input"
              type="checkbox"
              id=${"befilter-group-" + group.uid}
              value=${group.uid}
              .checked=${live(this.groups.includes(String(group.uid)))}
              @change=${(event) => this.toggleGroup(String(group.uid), event.target.checked)}
            >
            <label class="form-check-label" for=${"befilter-group-" + group.uid}>${group.title}</label>
          </div>`)}
      </fieldset>`;
  }

  setVisibility(visibility) {
    this.visibility = visibility;
    this.context.wizard.requestUpdate();
  }

  toggleGroup(uid, checked) {
    this.groups = checked ? [...this.groups, uid] : this.groups.filter((group) => group !== uid);
    this.context.wizard.requestUpdate();
  }

  getValue() {
    return { visibility: this.visibility, groups: this.visibility === "groups" ? this.groups : [] };
  }

  setValue(value) {
    this.visibility = value?.visibility ?? "private";
    this.groups = value?.groups ?? [];
  }

  reset() {
    this.setValue(null);
    this.context.clearStoreData(this.key);
  }

  beforeAdvance() {
    this.context.setStoreData(this.key, this.getValue());
  }

  getSummaryData() {
    const groups = this.context.configuration.groups ?? [];
    const names = groups
      .filter((group) => this.groups.includes(String(group.uid)))
      .map((group) => group.title);

    return [{
      label: this.title,
      value: this.visibility === "groups"
        ? names.join(", ")
        : this.options.find(([value]) => value === this.visibility)?.[1] ?? this.visibility,
    }];
  }
}
