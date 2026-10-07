/**
 * Enough of lit to construct the components and call their logic: rendering is not
 * exercised here, the templates are kept as data.
 */
export class LitElement {
  constructor() {
    this.updateCount = 0;
    this.events = [];
  }

  requestUpdate() {
    this.updateCount++;
  }

  dispatchEvent(event) {
    this.events.push(event);
    return true;
  }

  connectedCallback() {}
  disconnectedCallback() {}

  addEventListener() {}
  removeEventListener() {}
  querySelector() {
    return null;
  }

  querySelectorAll() {
    return [];
  }

  createRenderRoot() {
    return this;
  }
}

export const html = (strings, ...values) => ({ strings, values });
export const nothing = Symbol("nothing");

// lit/directives/live.js and lit/static-html.js resolve here as well.
export const live = (value) => value;
export const unsafeStatic = (value) => value;
