import "./setup.mjs";
import { test } from "node:test";
import assert from "node:assert/strict";
import { __setLabels } from "./stubs/labels.mjs";
import { describeVisibility, formatDate, translate } from "@digitalzombies/be_filter/wizard/support.js";

test("translate falls back to the English source when the bundle does not know the key", () => {
  __setLabels({});

  assert.equal(translate("presets.me", "Me"), "Me");
});

test("translate uses the bundle when the key is there", () => {
  __setLabels({ "presets.me": "Ich" });

  assert.equal(translate("presets.me", "Me"), "Ich");
});

test("formatDate leaves an empty date empty", () => {
  assert.equal(formatDate(0), "");
  assert.notEqual(formatDate(1789000000), "");
});

test("describeVisibility names the audience of a saved filter", () => {
  __setLabels({});

  assert.equal(describeVisibility({ visibility: "public", groups: [] }), "Everyone");
  assert.equal(describeVisibility({ visibility: "private", groups: [] }), "Only me");
  assert.equal(describeVisibility({ visibility: "groups", groups: ["Editors", "Sales"] }), "Editors, Sales");
  assert.equal(
    describeVisibility({ visibility: "groups", groups: [] }),
    "Selected backend groups",
    "A group filter without groups still says what it is"
  );
});
