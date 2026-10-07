import { LitElement, html } from "lit";
import { AutoAdvanceEvent } from "@typo3/backend/wizard/events/auto-advance-event.js";
import "@typo3/backend/wizard/wizard.js";
import NameStep from "@digitalzombies/be_filter/wizard/steps/name-step.js";
import VisibilityStep from "@digitalzombies/be_filter/wizard/steps/visibility-step.js";
import { request, translate } from "@digitalzombies/be_filter/wizard/support.js";

const FINISHER = "@digitalzombies/be_filter/wizard/finisher/preset-finisher.js";

class SaveSubmissionService {
  constructor(context) {
    this.context = context;
  }

  async execute() {
    const data = this.context.getDataStore();
    const configuration = this.context.configuration;
    const visibility = data.visibility ?? { visibility: "private", groups: [] };

    try {
      await request("be_filter_preset_save", {
        table: configuration.table,
        title: data.name ?? "",
        values: JSON.stringify(configuration.values ?? {}),
        visible: JSON.stringify(configuration.visible ?? []),
        visibility: visibility.visibility,
        groups: JSON.stringify(visibility.groups ?? []),
      });
    } catch (error) {
      return { success: false, errors: [error?.message ?? ""] };
    }

    return {
      success: true,
      finisher: {
        module: FINISHER,
        labels: {
          successTitle: translate("wizard.save.success.title", "Filter saved"),
          successDescription: translate("wizard.save.success.description", "The filter is available under its name."),
        },
        data: { reload: false },
      },
    };
  }
}

/**
 * Saving a filter, as a backend wizard: name, then who it is shared with.
 */
class PresetSaveWizard extends LitElement {
  static properties = {
    configuration: { type: Object },
    steps: { state: true },
    submissionService: { state: true },
  };

  constructor() {
    super();
    this.configuration = null;
    this.steps = [];
    this.submissionService = null;
  }

  createRenderRoot() {
    return this;
  }

  firstUpdated(changed) {
    super.firstUpdated(changed);

    const wizard = this.querySelector("typo3-backend-wizard");
    const context = {
      wizard,
      configuration: this.configuration,
      getStoreData: wizard.getStoreData.bind(wizard),
      setStoreData: wizard.setStoreData.bind(wizard),
      clearStoreData: wizard.clearStoreData.bind(wizard),
      getDataStore: wizard.getDataStore.bind(wizard),
      dispatchAutoAdvance: () => wizard.dispatchEvent(new AutoAdvanceEvent()),
    };

    this.steps = [new NameStep(context), new VisibilityStep(context)];
    this.submissionService = new SaveSubmissionService(context);
  }

  render() {
    return html`
      <typo3-backend-wizard
        .steps=${this.steps}
        .submissionService=${this.submissionService}
        confirm-button-label=${translate("button.save", "Save")}
      ></typo3-backend-wizard>`;
  }
}

customElements.define("befilter-preset-save-wizard", PresetSaveWizard);

export { PresetSaveWizard };
