import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { __setLabels } from "./stubs/labels.mjs";
import { PresetOpen } from "@digitalzombies/be_filter/preset-open.js";

const presets = [
  { uid: 1, title: "Audi in stock", own: true, owner: "claude", created: 1789000000, visibility: "private", groups: [] },
  { uid: 2, title: "Sold this year", own: false, owner: "editor", created: 1789000000, visibility: "public", groups: [] },
];

const build = () => {
  __setLabels({});
  const element = new PresetOpen();
  element.configuration = { table: "tx_test", presets };
  element.presets = presets;
  element.search = "";
  element.selected = null;

  return element;
};

test("the search covers name, creator and audience", () => {
  const element = build();

  element.search = "audi";
  assert.deepEqual(element.matches().map((p) => p.uid), [1]);

  element.search = "editor";
  assert.deepEqual(element.matches().map((p) => p.uid), [2]);

  element.search = "everyone";
  assert.deepEqual(element.matches().map((p) => p.uid), [2], "The audience is searchable too");

  element.search = "me";
  assert.deepEqual(element.matches().map((p) => p.uid), [1], "Your own filters answer to 'me'");

  element.search = "";
  assert.equal(element.matches().length, 2);
});

test("picking a filter keeps the click inside the dialog", () => {
  const element = build();
  let prevented = false;
  let stopped = false;
  const event = {
    preventDefault: () => { prevented = true; },
    stopPropagation: () => { stopped = true; },
  };

  element.pick(event, presets[0]);

  assert.ok(prevented, "The backend must not treat the click as one of its own");
  assert.ok(stopped);
  assert.equal(element.selected?.uid, 1);
  assert.equal(element.events.at(-1)?.detail?.preset?.uid, 1);
});

test("a double click asks for the filter to be applied right away", () => {
  const element = build();
  const event = { preventDefault() {}, stopPropagation() {} };

  element.pick(event, presets[1], true);

  assert.equal(element.events.at(-1)?.detail?.immediate, true);
});
