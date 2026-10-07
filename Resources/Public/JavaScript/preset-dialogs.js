import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal, { Size } from "@typo3/backend/modal.js";
import Notification from "@typo3/backend/notification.js";
import { SeverityEnum } from "@typo3/backend/enum/severity.js";
import { topLevelModuleImport } from "@typo3/backend/utility/top-level-module-import.js";
import { html } from "lit";
import { openWizardModal } from "@digitalzombies/be_filter/wizard/wizard-modal.js";
import { translate } from "@digitalzombies/be_filter/wizard/support.js";

const SAVE_WIZARD = "@digitalzombies/be_filter/wizard/preset-save-wizard.js";
const OPEN_DIALOG = "@digitalzombies/be_filter/preset-open.js";

const loadPresets = async (table, pageId) => {
  const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.be_filter_presets)
    .withQueryArguments({ table, id: pageId ?? 0 })
    .get();
  const result = await response.resolve();

  return { presets: result.presets ?? [], groups: result.groups ?? [] };
};

/**
 * Saving is a wizard: the name, then who the filter is shared with.
 */
export const openPresetSave = async (context) => {
  try {
    const { groups } = await loadPresets(context.table, context.pageId);

    await openWizardModal({
      module: SAVE_WIZARD,
      element: "befilter-preset-save-wizard",
      title: translate("presets.saveTitle", "Save filter"),
      configuration: {
        table: context.table,
        values: context.values,
        visible: context.visible,
        groups,
      },
    });
  } catch (error) {
    Notification.error(translate("error", "The change could not be saved."), error?.message ?? "");
  }
};

/**
 * Opening is a dialog: search, select, apply.
 */
export const openPresetOpen = async (context) => {
  try {
    const { presets } = await loadPresets(context.table, context.pageId);
    await topLevelModuleImport(OPEN_DIALOG);

    const element = document.createElement("befilter-preset-open");
    element.configuration = { table: context.table, presets };

    const modal = Modal.advanced({
      title: translate("presets.open", "Open a saved filter"),
      content: html`${element}`,
      severity: SeverityEnum.notice,
      size: Size.medium,
      buttons: [
        {
          text: translate("button.cancel", "Cancel"),
          btnClass: "btn-default",
          name: "cancel",
          trigger: (event, openModal) => openModal.hideModal(),
        },
        {
          text: translate("apply", "Apply"),
          btnClass: "btn-primary",
          name: "apply",
          trigger: (event, openModal) => {
            if (element.selected === null) {
              return;
            }
            openModal.hideModal();
            context.onApply(element.selected);
          },
        },
      ],
    });

    // Nothing to apply until a filter is picked, a double click applies right away.
    const syncApply = () => toggleApply(modal, element.selected != null);
    modal.addEventListener("typo3-modal-shown", syncApply);
    // The footer may already be rendered when the listener above is attached.
    window.setTimeout(syncApply, 0);

    element.addEventListener("befilter-preset-selected", (event) => {
      syncApply();
      if (event.detail.immediate && event.detail.preset !== null) {
        modal.hideModal();
        context.onApply(event.detail.preset);
      }
    });
  } catch (error) {
    Notification.error(translate("error", "The change could not be saved."), error?.message ?? "");
  }
};

const toggleApply = (modal, enabled) => {
  const button = modal.querySelector('button[name="apply"]');
  if (button) {
    button.disabled = !enabled;
  }
};
