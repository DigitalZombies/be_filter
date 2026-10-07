import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { BeFilterDate } from "@digitalzombies/be_filter/date-filter.js";

const emitted = (element) => element.events.at(-1)?.detail?.value ?? null;

const build = () => {
  const element = new BeFilterDate();
  element.filter = {
    name: "crdate",
    label: "Created",
    presets: [{ key: "week", label: "1 week" }, { key: "thisMonth", label: "This month" }],
  };
  element.value = null;
  element.range = {};

  return element;
};

test("both ends of a date range survive being entered in one go", () => {
  const element = build();

  element.setBoundary("from", "2026-01-01");
  element.setBoundary("to", "2026-03-31");

  assert.deepEqual(emitted(element), { from: "2026-01-01", to: "2026-03-31" });
});

test("entering a date replaces a quick range", () => {
  const element = build();
  element.range = { preset: "thisMonth" };

  element.setBoundary("from", "2026-01-01");

  assert.deepEqual(emitted(element), { from: "2026-01-01" }, "The preset is gone");
});

test("the summary prefers the label of the quick range", () => {
  const element = build();

  element.range = { preset: "thisMonth" };
  assert.equal(element.summary(), "This month");

  element.range = { preset: "unknown" };
  assert.equal(element.summary(), "unknown", "An unknown key still says something");
});

test("a configured caption replaces the translation of an end", () => {
  const element = build();
  element.filter = { ...element.filter, labels: { from: "Registered from" } };

  assert.equal(element.caption("from"), "Registered from");
  assert.ok(element.caption("to").length > 0, "The other end keeps its translation");
});
