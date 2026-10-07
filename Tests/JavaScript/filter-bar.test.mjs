import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { nothing } from "lit";
import { __setLabels } from "./stubs/labels.mjs";
import { registerFilterType } from "@digitalzombies/be_filter/filter-types.js";
import { BeFilterBar } from "@digitalzombies/be_filter/filter-bar.js";
import { BeFilterSelect } from "@digitalzombies/be_filter/select-filter.js";

const build = (overrides = {}) => {
  const element = new BeFilterBar();
  element.configuration = {
    table: "tx_test",
    pageId: 0,
    autoRefresh: false,
    values: {},
    visible: [],
    columns: [],
    filters: [
      { name: "manufacturer", type: "select", dependsOn: [] },
      { name: "model", type: "select", dependsOn: ["manufacturer"] },
      { name: "dealer", type: "select", dependsOn: ["manufacturer", "model"] },
      { name: "fuel", type: "select", dependsOn: [] },
    ],
    ...overrides,
  };
  element.values = {};

  return element;
};

test("changing a filter clears what depends on it, through the whole chain", () => {
  const element = build();
  element.values = { manufacturer: ["3"], model: ["5"], dealer: ["2"], fuel: ["diesel"] };

  const remaining = element.clearDependents(element.values, "manufacturer");

  assert.deepEqual(Object.keys(remaining).sort(), ["fuel", "manufacturer"]);
});

test("a value that is set and removed leaves no trace", () => {
  const element = build();

  element.setValue("fuel", ["diesel"]);
  assert.deepEqual(element.values, { fuel: ["diesel"] });

  element.setValue("fuel", null);
  assert.deepEqual(element.values, {});
});

test("the bar is dirty only while it differs from what was applied", () => {
  const element = build({ values: { fuel: ["diesel"] } });
  element.values = { fuel: ["diesel"] };

  assert.equal(element.isDirty(), false);

  element.setValue("fuel", ["petrol"]);
  assert.equal(element.isDirty(), true);
});

test("auto refresh schedules the reload instead of waiting for the button", () => {
  const element = build({ autoRefresh: true });

  element.setValue("fuel", ["diesel"]);
  assert.notEqual(element.applyTimer, 0, "A reload is pending");
  clearTimeout(element.applyTimer);

  const staged = build({ autoRefresh: false });
  staged.setValue("fuel", ["diesel"]);
  assert.equal(staged.applyTimer, 0, "Without auto refresh nothing is scheduled");
});

test("a missing label never takes the bar down", () => {
  __setLabels({});
  const select = new BeFilterSelect();
  select.filter = { name: "fuel", label: "Fuel", multiple: true, selected: [] };
  select.value = ["diesel", "petrol"];

  assert.equal(select.summary(), "2 selected", "The English source is used as the fallback");
});

test("a select control survives a filter that is not set", () => {
  const select = new BeFilterSelect();
  select.filter = { name: "fuel", label: "Fuel", multiple: true, selected: [] };
  select.value = null; // what the bar hands every control when nothing is chosen

  assert.deepEqual(select.selection, []);
  assert.deepEqual(select.entries(), []);
  assert.doesNotThrow(() => select.rememberOrder());
});

/**
 * The backend loads every module as its own async script, so the control of a filter may
 * register after the bar has been rendered. It was lost when that happened.
 */
test("a filter whose control arrives late is rendered once it does", () => {
  const element = build({
    filters: [{ name: "rating", type: "rating-late", dependsOn: [] }],
    visible: ["rating"],
  });
  element.connectedCallback();

  const filter = { name: "rating", type: "rating-late" };
  assert.equal(element.renderFilter(filter), nothing, "Nothing can render it yet");

  const before = element.updateCount;
  registerFilterType("rating-late", "my-late-control");

  assert.ok(element.updateCount > before, "The bar renders again when the control arrives");
  assert.notEqual(element.renderFilter(filter), nothing, "And the filter is there");
});

test("a bar that is gone stops listening for controls", () => {
  const element = build();
  element.connectedCallback();
  element.disconnectedCallback();

  const before = element.updateCount;
  registerFilterType("rating-gone", "my-gone-control");

  assert.equal(element.updateCount, before, "No update is requested any more");
});

