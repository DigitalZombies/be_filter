/**
 * The label bundle of the backend throws for a key it does not know, which is what the
 * guards in the filter modules are there for.
 */
const labels = new Map();

export const __setLabels = (entries) => {
  labels.clear();
  Object.entries(entries).forEach(([key, value]) => labels.set(key, value));
};

export default {
  get(key) {
    if (!labels.has(key)) {
      throw new Error("Label is not defined: " + key);
    }

    return labels.get(key);
  },
};
