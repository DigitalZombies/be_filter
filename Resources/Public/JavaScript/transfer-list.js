import labels from "~labels/be_filter.messages";

/**
 * Two panels: entries are dragged from "available" to "displayed", whose order is the
 * result. A modal lives in the top document, so the list is built there and dragging
 * uses pointer events bound to the entries, which works across both documents.
 */
const STYLES = `
.be-filter-transfer { display: flex; gap: 1rem; align-items: stretch; user-select: none; }
.be-filter-transfer-panel { display: flex; flex: 1 1 0; min-width: 0; flex-direction: column; gap: .5rem; }
.be-filter-transfer-title { font-weight: 600; }
.be-filter-transfer-list { flex: 1 1 auto; min-height: 14rem; max-height: 24rem; overflow-y: auto; margin: 0; padding: .25rem; list-style: none; border: 1px solid var(--typo3-component-border-color, rgba(0, 0, 0, .2)); border-radius: var(--typo3-component-border-radius, 4px); }
.be-filter-transfer-list.is-target { border-color: var(--typo3-component-primary-color, #0078e6); }
.be-filter-transfer-item { touch-action: none; display: flex; align-items: center; gap: .5rem; padding: .35rem .5rem; border-radius: var(--typo3-component-border-radius, 4px); cursor: grab; }
.be-filter-transfer-item:hover { background-color: var(--typo3-component-hover-bg, rgba(0, 0, 0, .06)); }
.be-filter-transfer-item.is-dragging { position: fixed; z-index: 1100; box-sizing: border-box; margin: 0; pointer-events: none; user-select: none; cursor: grabbing; background-color: var(--typo3-component-bg, #fff); border: 1px solid var(--typo3-component-border-color, rgba(0, 0, 0, .2)); box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .25); }
.be-filter-transfer-placeholder { margin: 0 .25rem; border: 1px dashed var(--typo3-component-primary-color, #0078e6); border-radius: var(--typo3-component-border-radius, 4px); background-color: color-mix(in srgb, var(--typo3-component-primary-color, #0078e6) 12%, transparent); }
.be-filter-transfer-item.is-fixed { cursor: default; opacity: .65; }
.be-filter-transfer-item.is-hidden { display: none; }
.be-filter-transfer-name { margin-inline-start: auto; opacity: .6; font-size: .85em; }
.be-filter-transfer-handle { opacity: .5; line-height: 1; }
.be-filter-transfer-item.is-fixed .be-filter-transfer-handle { visibility: hidden; }
.be-filter-transfer-empty { padding: .5rem; opacity: .6; }
.be-filter-transfer-search { margin-bottom: .75rem; }
@media (max-width: 575px) { .be-filter-transfer { flex-direction: column; } }
`;

// A label missing from the cached bundle must not take the popup down.
const translate = (key, fallback) => {
  try {
    return labels.get(key);
  } catch {
    return fallback;
  }
};

const modalDocument = () => {
  try {
    return window.top?.document ?? document;
  } catch {
    return document;
  }
};

export class TransferList {
  constructor(items, { searchable = false } = {}) {
    this.document = modalDocument();
    this.element = this.document.createElement("div");

    const style = this.document.createElement("style");
    style.textContent = STYLES;
    this.element.append(style);

    if (searchable) {
      this.element.append(this.createSearch());
    }

    const panels = this.document.createElement("div");
    panels.className = "be-filter-transfer";
    this.available = this.createPanel(translate("transfer.available", "Available"));
    this.displayed = this.createPanel(translate("transfer.displayed", "Displayed"));
    panels.append(this.available.panel, this.displayed.panel);
    this.element.append(panels);

    items.forEach((item) => {
      const list = item.selected ? this.displayed.list : this.available.list;
      list.append(this.createItem(item));
    });

    this.updateEmptyStates();
  }

  /**
   * Values of the displayed panel, in the order the user arranged them.
   */
  getSelected() {
    return [...this.displayed.list.querySelectorAll(".be-filter-transfer-item")]
      .map((item) => item.dataset.value);
  }

  createPanel(title) {
    const panel = this.document.createElement("div");
    panel.className = "be-filter-transfer-panel";

    const heading = this.document.createElement("div");
    heading.className = "be-filter-transfer-title";
    heading.textContent = title;

    const list = this.document.createElement("ul");
    list.className = "be-filter-transfer-list";

    const empty = this.document.createElement("li");
    empty.className = "be-filter-transfer-empty";
    empty.textContent = translate("transfer.empty", "Drag entries here");
    list.append(empty);

    panel.append(heading, list);

    return { panel, list, empty };
  }

  createItem(item) {
    const element = this.document.createElement("li");
    element.className = "be-filter-transfer-item" + (item.fixed ? " is-fixed" : "");
    element.dataset.value = item.value;
    element.dataset.searchLabel = `${item.label} ${item.value}`.toLowerCase();

    const handle = this.document.createElement("span");
    handle.className = "be-filter-transfer-handle";
    handle.setAttribute("aria-hidden", "true");
    handle.textContent = "⠿";

    const label = this.document.createElement("span");
    label.textContent = item.label;

    element.append(handle, label);

    if (item.description) {
      const description = this.document.createElement("span");
      description.className = "be-filter-transfer-name";
      description.textContent = item.description;
      element.append(description);
    }

    // Entries that cannot be switched off are neither dragged nor moved.
    if (!item.fixed) {
      element.addEventListener("dblclick", () => this.move(element));
      element.addEventListener("pointerdown", (event) => this.startDrag(event, element));
    }

    return element;
  }

  startDrag(event, element) {
    if (event.button !== 0) {
      return;
    }
    event.preventDefault();

    // No pointer capture: moving the entry in the DOM would release it, and the
    // browser would end the drag right after the first reorder. Document level
    // listeners survive that.
    const start = { x: event.clientX, y: event.clientY };
    const placeholder = this.createPlaceholder();

    // The last list the pointer was over. A coarse pointer stream can jump across
    // the gap between the panels, and such a move must not be lost.
    let target = element.parentElement;
    let offset = null;

    // The entry leaves the flow and follows the pointer, the placeholder keeps the
    // position it would be dropped at. Lifting waits for an actual move, so a click
    // and a double click stay untouched.
    const lift = () => {
      const rect = element.getBoundingClientRect();
      offset = { x: start.x - rect.left, y: start.y - rect.top };

      placeholder.style.height = `${rect.height}px`;
      element.before(placeholder);

      element.style.width = `${rect.width}px`;
      element.style.height = `${rect.height}px`;
      element.classList.add("is-dragging");
      this.dragLayer().append(element);
      this.document.body.style.cursor = "grabbing";
    };

    const place = (x, y) => {
      element.style.left = `${x - offset.x}px`;
      element.style.top = `${y - offset.y}px`;

      const list = this.listAt(x, y) ?? target;
      if (list === null) {
        return;
      }
      target = list;
      this.scrollIntoDirection(list, y);

      const before = this.itemAt(list, y, element);
      if (before !== null) {
        if (before !== placeholder.nextElementSibling) {
          list.insertBefore(placeholder, before);
        }
      } else if (placeholder.parentElement !== list || placeholder.nextElementSibling !== null) {
        list.append(placeholder);
      }

      this.highlight(list);
    };

    const onMove = (moveEvent) => {
      if (moveEvent.buttons === 0) {
        onUp(moveEvent);
        return;
      }

      if (offset === null) {
        if (Math.abs(moveEvent.clientX - start.x) + Math.abs(moveEvent.clientY - start.y) < 4) {
          return;
        }
        lift();
      }

      place(moveEvent.clientX, moveEvent.clientY);
    };

    const onUp = (upEvent) => {
      this.document.removeEventListener("pointermove", onMove);
      this.document.removeEventListener("pointerup", onUp);
      this.document.removeEventListener("pointercancel", onUp);

      if (offset !== null) {
        // Commit at the position the pointer was released, so a drag that produced
        // no usable move still lands where the user dropped it.
        if (upEvent && typeof upEvent.clientX === "number") {
          place(upEvent.clientX, upEvent.clientY);
        }

        element.classList.remove("is-dragging");
        element.removeAttribute("style");
        placeholder.replaceWith(element);
        this.document.body.style.removeProperty("cursor");
      }

      placeholder.remove();
      this.highlight(null);
      this.updateEmptyStates();
    };

    this.document.addEventListener("pointermove", onMove);
    this.document.addEventListener("pointerup", onUp);
    this.document.addEventListener("pointercancel", onUp);
  }

  /**
   * A modal is a dialog, painted in the top layer: nothing outside it can be drawn
   * above it, whatever its z-index, so the lifted entry stays inside.
   */
  dragLayer() {
    return this.element.closest("dialog") ?? this.document.body;
  }

  createPlaceholder() {
    const placeholder = this.document.createElement("li");
    placeholder.className = "be-filter-transfer-placeholder";

    return placeholder;
  }

  /**
   * A long list follows the pointer as soon as it rests near an edge.
   */
  scrollIntoDirection(list, y) {
    const rect = list.getBoundingClientRect();

    if (y < rect.top + 24) {
      list.scrollTop -= 12;
    } else if (y > rect.bottom - 24) {
      list.scrollTop += 12;
    }
  }

  /**
   * The list under the pointer, NULL while the pointer is outside both panels.
   */
  listAt(x, y) {
    return [this.available.list, this.displayed.list].find((list) => {
      const rect = list.getBoundingClientRect();

      return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
    }) ?? null;
  }

  /**
   * The entry the dragged one has to be placed before, NULL for the end of the list.
   */
  itemAt(list, y, dragged) {
    const candidates = [...list.querySelectorAll(".be-filter-transfer-item")]
      .filter((item) => item !== dragged && !item.classList.contains("is-fixed"));

    return candidates.find((item) => {
      const rect = item.getBoundingClientRect();

      return y < rect.top + rect.height / 2;
    }) ?? null;
  }

  highlight(list) {
    [this.available.list, this.displayed.list].forEach((candidate) => {
      candidate.classList.toggle("is-target", candidate === list);
    });
  }

  move(element) {
    const target = element.parentElement === this.displayed.list ? this.available.list : this.displayed.list;
    target.append(element);
    this.updateEmptyStates();
  }

  createSearch() {
    const search = this.document.createElement("input");
    search.type = "search";
    search.className = "form-control be-filter-transfer-search";
    search.placeholder = translate("search", "Search");
    search.addEventListener("input", () => {
      const term = search.value.trim().toLowerCase();
      this.element.querySelectorAll(".be-filter-transfer-item").forEach((item) => {
        item.classList.toggle("is-hidden", term !== "" && !item.dataset.searchLabel.includes(term));
      });
    });

    return search;
  }

  updateEmptyStates() {
    [this.available, this.displayed].forEach(({ list, empty }) => {
      empty.hidden = list.querySelectorAll(".be-filter-transfer-item").length > 0;
    });
  }
}
