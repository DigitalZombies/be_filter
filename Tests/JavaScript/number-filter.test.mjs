import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { BeFilterNumber } from "@digitalzombies/be_filter/number-filter.js";

const emitted = (element) => element.events.at(-1)?.detail?.value ?? null;

const build = () => {
  const element = new BeFilterNumber();
  element.filter = { name: "price", label: "Price", number: { min: 0, step: 500, decimals: 0 } };
  element.value = null;
  element.range = {};

  return element;
};

test("both ends of a range survive, also when the second is entered before the answer arrives", () => {
  const element = build();

  element.setBoundary("from", "20000");
  element.setBoundary("to", "40000");

  assert.deepEqual(emitted(element), { from: "20000", to: "40000" });
});

test("clearing both ends filters nothing", () => {
  const element = build();

  element.setBoundary("from", "20000");
  element.setBoundary("from", "");
  assert.equal(emitted(element), null);
});

test("a range open on one side is kept", () => {
  const element = build();

  element.setBoundary("from", "60000");

  assert.deepEqual(emitted(element), { from: "60000" });
});

test("the summary shows the range, an open end as an ellipsis", () => {
  const element = build();

  assert.equal(element.summary(), "");

  element.range = { from: "10", to: "20" };
  assert.equal(element.summary(), "10 – 20");

  element.range = { from: "10" };
  assert.equal(element.summary(), "10 – …");
});
