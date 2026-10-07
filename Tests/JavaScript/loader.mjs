/**
 * Resolves the specifiers of the backend to the stubs, and the own package prefix to
 * the sources, so the modules can be imported outside TYPO3.
 */
import { pathToFileURL } from "node:url";
import { dirname, join, resolve as resolvePath } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const sources = resolvePath(here, "../../Resources/Public/JavaScript");
const PREFIX = "@digitalzombies/be_filter/";

const stub = (name) => ({ url: pathToFileURL(join(here, "stubs", name)).href, shortCircuit: true });

export async function resolve(specifier, context, nextResolve) {
  if (specifier === "lit" || specifier.startsWith("lit/")) {
    return stub("lit.mjs");
  }
  if (specifier.startsWith("~labels/")) {
    return stub("labels.mjs");
  }
  if (specifier.startsWith("@typo3/")) {
    return stub("typo3.mjs");
  }
  if (specifier.startsWith(PREFIX)) {
    return { url: pathToFileURL(join(sources, specifier.slice(PREFIX.length))).href, shortCircuit: true };
  }

  return nextResolve(specifier, context);
}
