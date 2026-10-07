/**
 * Every @typo3/... module a filter module imports. The default export stands in for
 * AjaxRequest, Modal and Notification alike, the named ones for the rest.
 */
export const notifications = [];

export const __clearNotifications = () => {
  notifications.length = 0;
};

export default class Typo3Stub {
  constructor(url) {
    this.url = url;
  }

  withQueryArguments() {
    return this;
  }

  async get() {
    return { resolve: async () => ({ success: true, options: [], total: 0 }) };
  }

  async post() {
    return { resolve: async () => ({ success: true }) };
  }

  static error(title, message) { notifications.push({ level: "error", title, message }); }
  static warning(title, message) { notifications.push({ level: "warning", title, message }); }
  static success(title, message) { notifications.push({ level: "success", title, message }); }
  static advanced() {
    return { addEventListener() {}, querySelector: () => null, hideModal() {} };
  }

  static dismiss() {}
}

export const Size = { small: "small", medium: "medium", large: "large" };
export const SeverityEnum = { notice: 0, info: 1, warning: 2, error: 3 };
export const topLevelModuleImport = async () => {};
