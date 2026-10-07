import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { BeFilterNumber } from "@digitalzombies/be_filter/number-filter.js";

const emitted = (element) => element.events.at(-1)?.detail?.value ?? null;

const build = (number) => {
  const element = new BeFilterNumber();
  element.filter = { name: "price", label: "Price", number };
  element.value = null;
  element.range = {};

  return element;
};

test("two handles fill the two ends of the range", () => {
  const element = build({ min: 0, max: 100000, step: 500, render: "slider", mode: "range" });

  element.setHandle("from", "20000", "range");
  element.setHandle("to", "60000", "range");

  assert.deepEqual(emitted(element), { from: "20000", to: "60000" });
});

test("one handle bounds the range at the top", () => {
  const element = build({ min: 0, max: 300000, step: 1000, render: "slider", mode: "max" });

  element.setHandle("to", "50000", "max");

  assert.deepEqual(emitted(element), { to: "50000" }, "Everything up to the value");
});

test("one handle bounds the range at the bottom", () => {
  const element = build({ min: 0, max: 300000, step: 1000, render: "slider", mode: "min" });

  element.setHandle("from", "50000", "min");

  assert.deepEqual(emitted(element), { from: "50000" });
});

test("an exact slider asks for the value itself", () => {
  const element = build({ min: 1, max: 9, step: 1, render: "slider", mode: "exact" });

  element.setHandle("to", "5", "exact");

  assert.deepEqual(emitted(element), { from: "5", to: "5" });
  assert.equal(element.summary(), "5", "One value reads as one value, not as a range");
});

test("clearing the slider filters nothing", () => {
  const element = build({ min: 0, max: 100, step: 1, render: "slider", mode: "range" });

  element.setHandle("from", "10", "range");
  element.clear();

  assert.equal(emitted(element), null);
  assert.deepEqual(element.range, {});
});

test("a slider without both ends falls back to the two fields", () => {
  const bounded = build({ min: 0, max: 100, step: 1, render: "slider", mode: "range" });
  const unbounded = build({ min: 0, max: null, step: 1, render: "slider", mode: "range" });

  assert.ok(bounded.renderSlider().strings.join("").includes("be-filter-slider"));
  assert.ok(
    unbounded.renderSlider().strings.join("").includes("be-filter-range"),
    "Without an upper bound there is nothing to slide along"
  );
});

test("the captions of a slider come from the filter when it configures them", () => {
  const element = build({ min: 0, max: 100000, step: 500, render: "slider", mode: "range", labels: { from: "At least", to: "At most" } });

  assert.equal(element.caption("from"), "At least");
  assert.equal(element.caption("to"), "At most");

  const plain = build({ min: 0, max: 100, step: 1, render: "slider", mode: "range" });
  assert.ok(plain.caption("from").length > 0, "Without configuration the translation stands");
});
