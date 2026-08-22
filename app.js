"use strict";

const $ = (selector, root = document) => root?.querySelector(selector) ?? null;
const $$ = (selector, root = document) => root ? [...root.querySelectorAll(selector)] : [];
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const VALID_EXTENSIONS = /\.(pdf|jpe?g|png)$/i;

const menuButton = $("#menuButton");
if (menuButton) {
  menuButton.addEventListener("click", () => {
    const sidebar = $("#sidebar");
    const open = sidebar.classList.toggle("open");
    menuButton.setAttribute("aria-expanded", String(open));
  });
}

$$('.toast').forEach(toast => setTimeout(() => toast.remove(), 5000));

function setUploadState(input) {
  const card = input.closest(".upload-card");
  const file = input.files?.[0];
  const error = $(".error", card);
  let message = "";
  if (file && file.size > MAX_FILE_SIZE) message = "File must be 5 MB or smaller.";
  if (file && !VALID_EXTENSIONS.test(file.name)) message = "Use PDF, JPG, or PNG only.";
  card.classList.toggle("has-file", Boolean(file) && !message);
  card.classList.toggle("invalid", Boolean(message));
  $("em", card).textContent = file ? file.name : "Choose file";
  if (error) error.textContent = message;
  return !message;
}

$$('input[type="file"]').forEach(input => input.addEventListener("change", () => setUploadState(input)));

function fieldIsValid(field) {
  if (field.type === "file") {
    if (!setUploadState(field)) return false;
    if (field.required && !field.files?.length) {
      const card = field.closest(".upload-card");
      card.classList.add("invalid");
      $(".error", card).textContent = "This document is required.";
      return false;
    }
    return true;
  }
  if (!field.checkValidity()) {
    field.reportValidity();
    return false;
  }
  return true;
}

function validateStep(stepNumber) {
  const step = $(`[data-form-step="${stepNumber}"]`);
  if (!step) return true;
  const fields = $$('input[required], select[required], input[type="file"]', step);
  for (const field of fields) {
    if (!fieldIsValid(field)) {
      field.focus();
      return false;
    }
  }
  if (stepNumber === 2) {
    const occupancy = $('[name="occupancy_doc"]', step);
    const affidavit = $('[name="occupancy_affidavit_doc"]', step);
    if (occupancy && affidavit && !occupancy.files.length && !affidavit.files.length) {
      const card = occupancy.closest(".upload-card");
      card.classList.add("invalid");
      $(".error", card).textContent = "Upload this permit or the affidavit alternative.";
      occupancy.focus();
      return false;
    }
  }
  return true;
}

function showStep(stepNumber) {
  $$(".form-step").forEach(step => step.classList.toggle("active", Number(step.dataset.formStep) === stepNumber));
  $$('[data-step-indicator]').forEach(indicator => {
    const number = Number(indicator.dataset.stepIndicator);
    indicator.classList.toggle("active", number === stepNumber);
    indicator.classList.toggle("complete", number < stepNumber);
  });
  if (stepNumber === 3) buildReview();
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function humanize(name) {
  return name.replace(/_/g, " ").replace(/\b\w/g, character => character.toUpperCase());
}

function buildReview() {
  const form = $("#applicationForm");
  const review = $("#applicationReview");
  if (!form || !review) return;
  const entries = [];
  $$('input:not([type="hidden"]):not([type="checkbox"]), select', form).forEach(field => {
    const label = field.closest(".field, .upload-card")?.querySelector("strong")?.textContent || humanize(field.name);
    const value = field.type === "file" ? (field.files?.[0]?.name || "Not provided — if applicable") : field.value;
    entries.push([label, value || "—"]);
  });
  review.replaceChildren(...entries.map(([label, value]) => {
    const wrapper = document.createElement("div");
    const term = document.createElement("dt");
    const description = document.createElement("dd");
    term.textContent = label;
    description.textContent = value;
    wrapper.append(term, description);
    return wrapper;
  }));
}

const applicationForm = $("#applicationForm");
if (applicationForm) {
  applicationForm.addEventListener("click", event => {
    const next = event.target.closest("[data-next-step]");
    const previous = event.target.closest("[data-prev-step]");
    if (next) {
      const current = Number(next.closest(".form-step").dataset.formStep);
      if (validateStep(current)) showStep(Number(next.dataset.nextStep));
    }
    if (previous) showStep(Number(previous.dataset.prevStep));
  });
  applicationForm.addEventListener("submit", event => {
    if (!validateStep(1) || !validateStep(2) || !applicationForm.checkValidity()) event.preventDefault();
  });
}
