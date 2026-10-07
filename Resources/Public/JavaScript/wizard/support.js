import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import labels from "~labels/be_filter.messages";

/**
 * A label missing from the cached bundle, right after an update for example, must
 * not take a wizard down.
 */
export const translate = (key, fallback) => {
  try {
    return labels.get(key);
  } catch {
    return fallback;
  }
};

export const request = (route, payload) =>
  new AjaxRequest(TYPO3.settings.ajaxUrls[route]).post(payload).then(async (response) => {
    const result = await response.resolve();
    if (result.success !== true) {
      throw new Error(result.message ?? "");
    }

    return result;
  });

export const formatDate = (timestamp) =>
  timestamp > 0 ? new Date(timestamp * 1000).toLocaleDateString(document.documentElement.lang || undefined) : "";

export const describeVisibility = (preset) => {
  if (preset.visibility === "public") {
    return translate("visibility.public", "Everyone");
  }
  if (preset.visibility === "groups") {
    return preset.groups.length > 0
      ? preset.groups.join(", ")
      : translate("visibility.groups", "Selected backend groups");
  }

  return translate("visibility.private", "Only me");
};
