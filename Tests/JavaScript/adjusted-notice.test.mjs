import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { notifications, __clearNotifications } from "./stubs/typo3.mjs";
import { BeFilterBar } from "@digitalzombies/be_filter/filter-bar.js";

const build = () => {
  const element = new BeFilterBar();
  element.configuration = { table: "tx_test", pageId: 0, autoRefresh: false, values: {}, visible: [], columns: [], filters: [] };
  element.values = {};

  return element;
};

const reset = () => {
  __clearNotifications();
  globalThis.sessionStorage.clear();
};

test("nothing dropped means nothing to say", () => {
  reset();
  const element = build();

  element.rememberAdjustment([]);
  element.reportAdjustment();

  assert.equal(notifications.length, 0);
});

test("a notice about dropped values survives the reload", () => {
  reset();

  // the bar that sends the values remembers what was dropped ...
  build().rememberAdjustment(["Manufacturer", "Dealer"]);
  assert.equal(notifications.length, 0, "not shown before the reload, it would be lost");

  // ... and the bar that comes up after the reload says it
  build().reportAdjustment();

  assert.equal(notifications.length, 1);
  assert.equal(notifications[0].level, "warning");
  assert.match(notifications[0].message, /Manufacturer, Dealer/);
});

test("the notice is said once, not on every page", () => {
  reset();
  build().rememberAdjustment(["Manufacturer"]);

  build().reportAdjustment();
  build().reportAdjustment();

  assert.equal(notifications.length, 1, "the second bar has nothing left to report");
});

test("a bar coming up with nothing stored stays quiet", () => {
  reset();

  build().reportAdjustment();

  assert.equal(notifications.length, 0);
});

test("storage that refuses to answer does not take the bar down", () => {
  reset();
  const element = build();
  const real = globalThis.sessionStorage.setItem;
  globalThis.sessionStorage.setItem = () => { throw new Error("denied"); };

  assert.doesNotThrow(() => element.rememberAdjustment(["Manufacturer"]));
  assert.equal(notifications.length, 1, "it says it straight away instead");

  globalThis.sessionStorage.setItem = real;
});
