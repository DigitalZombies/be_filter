import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { BeFilterCheckbox } from "@digitalzombies/be_filter/checkbox-filter.js";
import { AbstractFilterControl } from "@digitalzombies/be_filter/filter-types.js";

const build = (checkbox) => {
  const element = new BeFilterCheckbox();
  element.filter = { name: "available", label: "Available", checkbox };

  return element;
};

test("the checkbox stands in the bar itself, so it brings its own shell", () => {
  assert.notEqual(
    BeFilterCheckbox.prototype.renderShell,
    AbstractFilterControl.prototype.renderShell,
    "It overrides the dropdown shell"
  );

  const content = { itIs: "the control" };
  assert.equal(build(false).renderShell(content), content);
});

test("both renderings are built without a dropdown around them", () => {
  assert.doesNotThrow(() => build(false).renderControl(), "the yes / no dropdown");
  assert.doesNotThrow(() => build(true).renderControl(), "the switch");
});

test("it hands the bar the state that was picked, and NULL to clear it", () => {
  const element = build(false);

  element.emit("1");
  assert.deepEqual(element.events.at(-1).detail, { name: "available", value: "1" });

  element.emit("0");
  assert.equal(element.events.at(-1).detail.value, "0");

  element.emit(null);
  assert.equal(element.events.at(-1).detail.value, null);
});
