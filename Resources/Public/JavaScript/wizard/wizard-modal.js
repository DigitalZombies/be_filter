import { topLevelModuleImport } from "@typo3/backend/utility/top-level-module-import.js";
import { html } from "lit";
import Modal, { Size } from "@typo3/backend/modal.js";
import { SeverityEnum } from "@typo3/backend/enum/severity.js";
import { unsafeStatic, html as staticHtml } from "lit/static-html.js";

/**
 * Opens a wizard the way the backend does: the element is defined in the top document,
 * where the modal lives, and the modal itself carries no buttons - the wizard brings
 * its own.
 */
export const openWizardModal = async ({ module, element, title, configuration }) => {
  await topLevelModuleImport(module);

  Modal.advanced({
    title,
    content: staticHtml`<${unsafeStatic(element)} .configuration=${configuration}></${unsafeStatic(element)}>`,
    severity: SeverityEnum.notice,
    size: { width: Size.medium, height: Size.large },
    staticBackdrop: true,
    buttons: [],
  });
};
