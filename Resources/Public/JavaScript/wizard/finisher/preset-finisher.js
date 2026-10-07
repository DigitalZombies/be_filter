import { html } from "lit";

/**
 * Closes a filter wizard. Applying a saved filter changes the listing, so the module
 * is refreshed afterwards; saving one leaves the listing as it is.
 */
export default class PresetFinisher {
  setConfig(config) {
    this.config = config;
  }

  async render() {
    return html`
      <typo3-backend-alert
        severity="0"
        heading=${this.config.labels.successTitle}
        message=${this.config.labels.successDescription}
        show-icon
      ></typo3-backend-alert>`;
  }

  async execute() {
    if (!this.config.data?.reload) {
      return;
    }

    const container = window.TYPO3?.Backend?.ContentContainer ?? window.top?.TYPO3?.Backend?.ContentContainer;
    if (container) {
      container.refresh();
      return;
    }

    window.location.reload();
  }
}
