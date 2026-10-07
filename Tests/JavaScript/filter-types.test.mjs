import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import {
  AbstractFilterControl,
  onFilterTypeRegistered,
  registerFilterType,
  registeredFilterTypes,
  resolveFilterTag,
} from "@digitalzombies/be_filter/filter-types.js";
import "@digitalzombies/be_filter/select-filter.js";
import "@digitalzombies/be_filter/date-filter.js";
import "@digitalzombies/be_filter/number-filter.js";
import "@digitalzombies/be_filter/checkbox-filter.js";

test("the built in controls register themselves", () => {
  assert.equal(resolveFilterTag("select"), "be-filter-select");
  assert.equal(resolveFilterTag("date"), "be-filter-date");
  assert.equal(resolveFilterTag("number"), "be-filter-number");
  assert.equal(resolveFilterTag("checkbox"), "be-filter-checkbox");
});

test("a type nobody registered a control for resolves to nothing", () => {
  assert.equal(resolveFilterTag("rating"), null);
});

test("anybody can add a control for a type of their own", () => {
  registerFilterType("rating", "my-rating-filter");

  assert.equal(resolveFilterTag("rating"), "my-rating-filter");
  assert.ok(registeredFilterTypes().includes("rating"));
});

test("a control has to say what it renders", () => {
  const control = new AbstractFilterControl();
  control.filter = { name: "rating", label: "Rating" };

  assert.throws(() => control.renderControl(), /renderControl/);
  assert.equal(control.summary(), "", "Without a summary the label stands alone");
});

test("a control hands its value to the bar under the name of its filter", () => {
  const control = new AbstractFilterControl();
  control.filter = { name: "rating", label: "Rating" };

  control.emit(4);

  assert.deepEqual(control.events.at(-1).detail, { name: "rating", value: 4 });
});

test("an extension can take a built in type over", () => {
  assert.equal(resolveFilterTag("select"), "be-filter-select");

  registerFilterType("select", "my-own-select");
  assert.equal(resolveFilterTag("select"), "my-own-select", "The later registration wins");

  registerFilterType("select", "be-filter-select");
});

test("every built in control is built on the same base as any other", async () => {
  const modules = await Promise.all([
    import("@digitalzombies/be_filter/select-filter.js"),
    import("@digitalzombies/be_filter/date-filter.js"),
    import("@digitalzombies/be_filter/number-filter.js"),
    import("@digitalzombies/be_filter/checkbox-filter.js"),
  ]);

  for (const module of modules) {
    const Control = Object.values(module)[0];
    assert.ok(
      Control.prototype instanceof AbstractFilterControl,
      `${Control.name} extends AbstractFilterControl`
    );
  }
});

test("a control that is not a dropdown supplies its own shell", async () => {
  const { BeFilterCheckbox } = await import("@digitalzombies/be_filter/checkbox-filter.js");
  const control = new BeFilterCheckbox();

  assert.notEqual(
    BeFilterCheckbox.prototype.renderShell,
    AbstractFilterControl.prototype.renderShell,
    "The checkbox overrides only the shell"
  );
  assert.equal(BeFilterCheckbox.prototype.renderControl === AbstractFilterControl.prototype.renderControl, false);
  assert.ok(control instanceof AbstractFilterControl);
});

test("whoever renders filters is told when a control arrives, until it stops listening", () => {
  const seen = [];
  const stopListening = onFilterTypeRegistered((type, tagName) => seen.push([type, tagName]));

  registerFilterType("arrives", "arrives-control");
  assert.deepEqual(seen, [["arrives", "arrives-control"]]);

  stopListening();
  registerFilterType("later", "later-control");

  assert.equal(seen.length, 1, "Nothing more after it stopped listening");
});

