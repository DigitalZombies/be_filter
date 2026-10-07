import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { BeFilterSelect } from "@digitalzombies/be_filter/select-filter.js";

const emitted = (element) => element.events.at(-1)?.detail?.value ?? null;

const build = (multiple = true) => {
  const element = new BeFilterSelect();
  element.filter = { name: "fuel", label: "Fuel", multiple, selected: [] };
  element.value = [];

  return element;
};

test("a single select replaces whatever was chosen before", () => {
  const element = build(false);
  element.value = ["diesel"];

  element.toggle({ value: "petrol", label: "Petrol" });

  assert.deepEqual(emitted(element), ["petrol"]);
});

test("a multiple select adds a value and takes it away again", () => {
  const element = build();
  element.value = ["diesel"];

  element.toggle({ value: "petrol", label: "Petrol" });
  assert.deepEqual(emitted(element), ["diesel", "petrol"]);

  element.value = ["diesel", "petrol"];
  element.toggle({ value: "diesel", label: "Diesel" });
  assert.deepEqual(emitted(element), ["petrol"]);
});

test("taking the last value away clears the filter", () => {
  const element = build();
  element.value = ["diesel"];

  element.toggle({ value: "diesel", label: "Diesel" });

  assert.equal(emitted(element), null, "An empty selection is no filter at all");
});

test("chosen entries stay at the top, in the order the list was opened with", () => {
  const element = build();
  element.options = [
    { value: "a", label: "A" },
    { value: "b", label: "B" },
    { value: "c", label: "C" },
  ];
  element.value = ["c"];
  element.rememberOrder();

  assert.deepEqual(element.entries().map((entry) => entry.value), ["c", "a", "b"]);
});

test("a chosen value stays in the list even when the options no longer carry it", () => {
  const element = build();
  element.knownLabels = { hidden: "Hidden one" };
  element.value = ["hidden"];
  element.options = [{ value: "a", label: "A" }];
  element.rememberOrder();

  assert.deepEqual(element.entries()[0], { value: "hidden", label: "Hidden one" });
});

test("the summary names one value and counts several", () => {
  const element = build();
  element.knownLabels = { diesel: "Diesel", petrol: "Petrol" };

  element.value = [];
  assert.equal(element.summary(), "", "Nothing chosen leaves the label alone");

  element.value = ["diesel"];
  assert.equal(element.summary(), "Diesel");

  element.value = ["diesel", "petrol"];
  assert.equal(element.summary(), "2 selected");
});

test("a filter that is not set behaves like an empty selection", () => {
  const element = build();
  element.value = null;

  assert.deepEqual(element.selection, []);
  assert.equal(element.summary(), "");
  assert.doesNotThrow(() => element.rememberOrder());
});
