"use strict";

const STORAGE_KEY = "permitflow-applications-v1";
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const VALID_FILE_TYPES = ["application/pdf", "image/jpeg", "image/png"];
const DOCUMENT_FIELDS = [
  ["registrationDoc", "DTI / SEC / CDA Registration", true],
  ["bfpApplicationDoc", "BFP Application Form", true],
  ["bfpQuestionnaireDoc", "BFP Questionnaire", true],
  ["consentFormDoc", "Consent Form", true],
  ["leaseContractDoc", "Lease Contract for Private Building", false],
  ["fsicOccupancyDoc", "FSIC of Occupancy Valid for 9 Months", false],
  ["occupancyDoc", "Occupancy Permit", false],
  ["taxDeclarationDoc", "Tax Declaration — Current Year", false],
  ["healthResultsDoc", "X-Ray Result and Stool Examination", false],
  ["ngaClearanceDoc", "NGA Clearance", false],
  ["occupancyAffidavitDoc", "Affidavit in Absence of Occupancy", false],
  ["buildingOwnerPermitDoc", "Building Owner’s Business Permit", false],
  ["currentFsicDoc", "FSIC — Current Year", false],
  ["sanitaryPermitDoc", "Sanitary Permit — Current Year", false]
];

const seedApplications = [
  { reference: "BPL-2026-00124", permitNumber: "BP-2026-01482", businessName: "Sunrise Demo Café", owner: "Demo Applicant", type: "Renewal", businessType: "Food and Beverage", submitted: "2026-08-19", status: "For Review", stage: 2, address: "Demo District, Sample City", email: "applicant@example.invalid", contact: "Demo contact" },
  { reference: "BPL-2026-00098", permitNumber: "BP-2026-01106", businessName: "Sample Digital Services", owner: "Demo Applicant", type: "New", businessType: "Professional Services", submitted: "2026-08-12", status: "Approved", stage: 3, address: "Demo District, Sample City", email: "applicant@example.invalid", contact: "Demo contact" },
  { reference: "BPL-2026-00081", permitNumber: "BP-2025-00972", businessName: "Example Trading", owner: "Sample Applicant A", type: "Renewal", businessType: "Retail", submitted: "2026-08-08", status: "Needs Revision", stage: 1, address: "Sample District A, Sample City", email: "sample-a@example.invalid", contact: "Demo contact" },
  { reference: "BPL-2026-00072", permitNumber: "BP-2026-00851", businessName: "Demo Printshop", owner: "Sample Applicant B", type: "New", businessType: "Professional Services", submitted: "2026-08-06", status: "Released", stage: 4, address: "Sample District B, Sample City", email: "sample-b@example.invalid", contact: "Demo contact" }
];

const $ = (selector, root = document) => root ? root.querySelector(selector) : null;
const $$ = (selector, root = document) => root ? [...root.querySelectorAll(selector)] : [];

function safeLoadApplications() {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
    return Array.isArray(stored) && stored.length ? stored : structuredClone(seedApplications);
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return structuredClone(seedApplications);
  }
}

let applications = safeLoadApplications();
let activeRole = "applicant";

function persist() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify(applications)); }
  catch { showToast("Changes are available for this session but could not be saved in this browser.", true); }
}

function escapeHTML(value = "") {
  return String(value).replace(/[&<>'"]/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[char]));
}

function formatDate(dateString) {
  const date = new Date(`${dateString}T00:00:00`);
  return Number.isNaN(date.valueOf()) ? dateString : new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(date);
}

function statusClass(status) {
  return ({ "For Review": "review", "Approved": "approved", "Needs Revision": "revision", "Released": "released" })[status] || "review";
}

function showToast(message, isError = false) {
  const toast = document.createElement("div");
  toast.className = `toast${isError ? " error-toast" : ""}`;
  toast.textContent = message;
  $("#toastRegion").append(toast);
  setTimeout(() => toast.remove(), 4200);
}

function route() {
  const allowed = activeRole === "staff" ? ["staff-dashboard", "review-queue"] : ["dashboard", "apply", "renew", "track"];
  let target = location.hash.slice(1);
  if (!allowed.includes(target)) target = activeRole === "staff" ? "staff-dashboard" : "dashboard";
  $$(".page").forEach(page => page.classList.toggle("active", page.id === target));
  $$('[data-route]').forEach(link => link.classList.toggle("active", link.dataset.route === target));
  const page = document.getElementById(target);
  $("#pageTitle").textContent = page?.dataset.title || "PermitFlow";
  $("#sidebar").classList.remove("open");
  $("#menuButton").setAttribute("aria-expanded", "false");
  window.scrollTo({ top: 0, behavior: "smooth" });
  if (location.hash.slice(1) !== target) history.replaceState(null, "", `#${target}`);
}

function setRole(role) {
  activeRole = role;
  $("#applicantNav").hidden = role !== "applicant";
  $("#staffNav").hidden = role !== "staff";
  $("#profileRole").textContent = role === "staff" ? "BPLO Evaluator" : "Applicant";
  location.hash = role === "staff" ? "staff-dashboard" : "dashboard";
  renderAll();
}

function renderApplicantDashboard() {
  const mine = applications.filter(item => item.owner === "Demo Applicant");
  const cards = [
    ["▤", mine.length, "Total applications"],
    ["◷", mine.filter(item => item.status === "For Review").length, "Under review"],
    ["✓", mine.filter(item => ["Approved", "Released"].includes(item.status)).length, "Approved permits"],
    ["!", mine.filter(item => item.status === "Needs Revision").length, "Needs attention"]
  ];
  $("#applicantStats").innerHTML = cards.map(([icon, value, label]) => `<article class="stat-card"><span>${icon}</span><strong>${value}</strong><small>${label}</small></article>`).join("");
  $("#recentApplications").innerHTML = mine.slice(0, 3).map(item => `
    <div class="application-item">
      <div class="application-icon">${item.type === "Renewal" ? "↻" : "＋"}</div>
      <div class="application-copy"><strong>${escapeHTML(item.businessName)}</strong><small>${escapeHTML(item.reference)} · ${formatDate(item.submitted)}</small></div>
      <span class="status ${statusClass(item.status)}">${escapeHTML(item.status)}</span>
    </div>`).join("") || '<p class="empty-state">No applications yet.</p>';
}

function renderStaffDashboard() {
  const cards = [
    ["▤", applications.length, "Total applications"],
    ["◷", applications.filter(item => item.status === "For Review").length, "Pending review"],
    ["!", applications.filter(item => item.status === "Needs Revision").length, "For revision"],
    ["✓", applications.filter(item => ["Approved", "Released"].includes(item.status)).length, "Approved / released"]
  ];
  $("#staffStats").innerHTML = cards.map(([icon, value, label]) => `<article class="stat-card"><span>${icon}</span><strong>${value}</strong><small>${label}</small></article>`).join("");
  const stages = ["Submitted", "Validation", "Assessment", "Release"];
  $("#stageChart").innerHTML = stages.map((label, index) => {
    const count = applications.filter(item => item.stage === index + 1).length;
    const percent = applications.length ? Math.max(8, Math.round(count / applications.length * 100)) : 0;
    return `<div class="bar-row"><span>${label}</span><div class="bar-track"><div class="bar-fill" style="width:${percent}%"></div></div><strong>${count}</strong></div>`;
  }).join("");
}

function renderQueue() {
  const query = $("#queueSearch").value.trim().toLowerCase();
  const filter = $("#queueFilter").value;
  const visible = applications.filter(item => {
    const matchesQuery = !query || `${item.reference} ${item.businessName} ${item.owner}`.toLowerCase().includes(query);
    return matchesQuery && (filter === "all" || item.status === filter);
  });
  $("#queueBody").innerHTML = visible.map(item => `<tr>
    <td><strong>${escapeHTML(item.reference)}</strong><small>${escapeHTML(item.permitNumber || "Pending permit no.")}</small></td>
    <td><strong>${escapeHTML(item.businessName)}</strong><small>${escapeHTML(item.owner)}</small></td>
    <td>${escapeHTML(item.type)}</td><td>${formatDate(item.submitted)}</td>
    <td><span class="status ${statusClass(item.status)}">${escapeHTML(item.status)}</span></td>
    <td><button class="table-action" type="button" data-review="${escapeHTML(item.reference)}">Review →</button></td>
  </tr>`).join("") || '<tr><td colspan="6" class="empty-state">No matching applications.</td></tr>';
}

function renderAll() {
  renderApplicantDashboard();
  renderStaffDashboard();
  renderQueue();
}

function validateField(field) {
  const wrapper = field.closest(".field, .upload-card");
  const error = $(".error", wrapper);
  let message = "";
  if (field.type === "file") {
    const file = field.files[0];
    if (field.required && !file) message = "This document is required.";
    else if (file && file.size > MAX_FILE_SIZE) message = "File must be 5 MB or smaller.";
    else if (file && !VALID_FILE_TYPES.includes(file.type) && !/\.(pdf|jpe?g|png)$/i.test(file.name)) message = "Use PDF, JPG, or PNG only.";
  } else if (!field.value.trim()) message = "This field is required.";
  else if (field.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) message = "Enter a valid email address.";
  else if (field.name === "tin" && !/^\d{3}-?\d{3}-?\d{3}(?:-?\d{3})?$/.test(field.value.replace(/\s/g, ""))) message = "Enter a valid 9 or 12-digit TIN.";
  else if (field.name === "contact" && field.value.replace(/\D/g, "").length < 10) message = "Enter a valid contact number.";
  wrapper?.classList.toggle("invalid", Boolean(message));
  if (error) error.textContent = message;
  return !message;
}

function validateStep(stepNumber) {
  const step = $(`[data-form-step="${stepNumber}"]`);
  const fields = $$('input[required], select[required], input[type="file"]', step).filter(field => field.name !== "declaration");
  const results = fields.map(validateField);
  let occupancyChoiceValid = true;
  if (stepNumber === 2) {
    const occupancy = step.querySelector('[name="occupancyDoc"]');
    const affidavit = step.querySelector('[name="occupancyAffidavitDoc"]');
    occupancyChoiceValid = Boolean(occupancy.files[0] || affidavit.files[0]);
    if (!occupancyChoiceValid) {
      const wrapper = occupancy.closest(".upload-card");
      wrapper.classList.add("invalid");
      $(".error", wrapper).textContent = "Upload this document or the affidavit alternative.";
    }
  }
  const firstInvalid = fields[results.indexOf(false)] || (!occupancyChoiceValid ? step.querySelector('[name="occupancyDoc"]') : null);
  firstInvalid?.focus();
  return results.every(Boolean) && occupancyChoiceValid;
}

function showFormStep(stepNumber) {
  $$(".form-step").forEach(step => step.classList.toggle("active", Number(step.dataset.formStep) === stepNumber));
  $$('[data-step-indicator]').forEach(item => {
    const itemStep = Number(item.dataset.stepIndicator);
    item.classList.toggle("active", itemStep === stepNumber);
    item.classList.toggle("complete", itemStep < stepNumber);
  });
  if (stepNumber === 3) renderApplicationReview();
  $("#apply").scrollIntoView({ behavior: "smooth" });
}

function renderApplicationReview() {
  const data = new FormData($("#applicationForm"));
  const businessEntries = [
    ["Business name", data.get("businessName")], ["Business type", data.get("businessType")],
    ["Organization", data.get("organizationType")], ["TIN", data.get("tin")],
    ["Contact", data.get("contact")], ["Email", data.get("email")], ["Address", data.get("address")]
  ];
  const documentEntries = DOCUMENT_FIELDS.map(([name, label, required]) => {
    const file = data.get(name);
    return [label, file?.name || (required ? "Required document missing" : "Not provided — if applicable")];
  });
  const entries = [...businessEntries, ...documentEntries];
  $("#applicationReview").innerHTML = entries.map(([label, value]) => `<div><dt>${label}</dt><dd>${escapeHTML(value || "—")}</dd></div>`).join("");
}

function createReference() {
  const serial = String(Math.floor(10000 + Math.random() * 89999));
  return `BPL-${new Date().getFullYear()}-${serial}`;
}

function handleApplicationSubmit(event) {
  event.preventDefault();
  const declaration = event.currentTarget.elements.declaration;
  if (!declaration.checked) { showToast("Please confirm the accuracy declaration before submitting.", true); declaration.focus(); return; }
  const data = new FormData(event.currentTarget);
  const reference = createReference();
  applications.unshift({
    reference, permitNumber: "Pending", businessName: data.get("businessName").trim(), owner: "Demo Applicant",
    type: "New", businessType: data.get("businessType"), submitted: new Date().toISOString().slice(0, 10), status: "For Review", stage: 1,
    address: data.get("address").trim(), email: data.get("email").trim(), contact: data.get("contact").trim()
  });
  persist();
  event.currentTarget.reset();
  $$(".upload-card").forEach(card => { card.classList.remove("has-file", "invalid"); $("em", card).textContent = "Choose file"; });
  showFormStep(1);
  renderAll();
  $("#trackingReference").value = reference;
  location.hash = "track";
  setTimeout(() => { renderTracking(reference); showToast(`Application submitted. Reference: ${reference}`); }, 120);
}

function renderTracking(reference) {
  const normalized = reference.trim().toUpperCase();
  const item = applications.find(app => app.reference.toUpperCase() === normalized);
  if (!item) { $("#trackingResult").innerHTML = '<div class="panel result-card empty-state"><strong>No application found.</strong><p>Check the reference number and try again.</p></div>'; return; }
  const stages = ["Submitted", "Validation", "Assessment", "Permit release"];
  $("#trackingResult").innerHTML = `<article class="panel result-card">
    <div class="record-summary"><div><p class="eyebrow">${escapeHTML(item.reference)}</p><h3>${escapeHTML(item.businessName)}</h3><p class="muted">${escapeHTML(item.type)} application · Submitted ${formatDate(item.submitted)}</p></div><span class="status ${statusClass(item.status)}">${escapeHTML(item.status)}</span></div>
    <ol class="timeline">${stages.map((stage, index) => `<li class="${index + 1 < item.stage ? "done" : index + 1 === item.stage ? "current" : ""}"><span>${index + 1 <= item.stage ? "✓" : index + 1}</span>${stage}</li>`).join("")}</ol>
  </article>`;
}

function renderRenewal(permitNumber) {
  const normalized = permitNumber.trim().toUpperCase();
  const item = applications.find(app => app.permitNumber?.toUpperCase() === normalized);
  if (!item) { $("#renewResult").innerHTML = '<div class="panel result-card empty-state"><strong>No active permit found.</strong><p>Check the permit number or contact the BPLO Help Desk.</p></div>'; return; }
  $("#renewResult").innerHTML = `<article class="panel result-card">
    <div class="record-summary"><div><p class="eyebrow">Permit record found</p><h3>${escapeHTML(item.businessName)}</h3><p class="muted">${escapeHTML(item.permitNumber)} · ${escapeHTML(item.address)}</p></div><span class="status approved">Active record</span></div>
    <form id="renewalForm" class="renew-form">
      <div class="form-grid"><label class="field">Gross sales for previous year<input name="grossSales" inputmode="decimal" required placeholder="₱ 0.00"><small class="error"></small></label><label class="field">Renewal contact email<input name="renewalEmail" type="email" value="${escapeHTML(item.email)}" required><small class="error"></small></label></div>
      <div class="form-actions"><span></span><button class="button" type="submit">Submit renewal</button></div>
    </form>
  </article>`;
  $("#renewalForm").addEventListener("submit", event => handleRenewalSubmit(event, item));
}

function handleRenewalSubmit(event, source) {
  event.preventDefault();
  const fields = $$('input[required]', event.currentTarget);
  if (!fields.map(validateField).every(Boolean)) return;
  const reference = createReference();
  applications.unshift({ ...source, reference, type: "Renewal", submitted: new Date().toISOString().slice(0, 10), status: "For Review", stage: 1 });
  persist(); renderAll();
  $("#trackingReference").value = reference;
  location.hash = "track";
  setTimeout(() => { renderTracking(reference); showToast(`Renewal submitted. Reference: ${reference}`); }, 120);
}

function openReview(reference) {
  const item = applications.find(app => app.reference === reference);
  if (!item) return;
  $("#modalTitle").textContent = item.businessName;
  $("#modalContent").innerHTML = `<div class="modal-body"><div class="detail-list">
    <div><small>Reference</small><strong>${escapeHTML(item.reference)}</strong></div><div><small>Applicant</small><strong>${escapeHTML(item.owner)}</strong></div>
    <div><small>Application</small><strong>${escapeHTML(item.type)}</strong></div><div><small>Business type</small><strong>${escapeHTML(item.businessType)}</strong></div>
    <div><small>Contact</small><strong>${escapeHTML(item.contact)}</strong></div><div><small>Email</small><strong>${escapeHTML(item.email)}</strong></div>
    <div style="grid-column:1/-1"><small>Business address</small><strong>${escapeHTML(item.address)}</strong></div>
  </div></div><div class="modal-actions"><button class="button button-danger" type="button" data-decision="Needs Revision" data-reference="${escapeHTML(item.reference)}">Request revision</button><button class="button" type="button" data-decision="Approved" data-reference="${escapeHTML(item.reference)}">Approve application</button></div>`;
  $("#modalBackdrop").hidden = false;
  $("#closeModal").focus();
}

function decide(reference, decision) {
  const item = applications.find(app => app.reference === reference);
  if (!item) return;
  item.status = decision;
  item.stage = decision === "Approved" ? 3 : 1;
  persist(); renderAll(); closeModal();
  showToast(decision === "Approved" ? "Application approved successfully." : "Revision request recorded.");
}

function closeModal() { $("#modalBackdrop").hidden = true; }

window.addEventListener("hashchange", route);
$("#roleSelect").addEventListener("change", event => setRole(event.target.value));
$("#menuButton").addEventListener("click", () => {
  const isOpen = $("#sidebar").classList.toggle("open");
  $("#menuButton").setAttribute("aria-expanded", String(isOpen));
});
$("#notificationButton").addEventListener("click", () => showToast("You have one application currently under review."));
$("#applicationForm").addEventListener("click", event => {
  const next = event.target.closest("[data-next-step]");
  const previous = event.target.closest("[data-prev-step]");
  if (next) { const currentStep = Number(next.closest(".form-step").dataset.formStep); if (validateStep(currentStep)) showFormStep(Number(next.dataset.nextStep)); }
  if (previous) showFormStep(Number(previous.dataset.prevStep));
});
$("#applicationForm").addEventListener("input", event => { if (event.target.matches("input, select")) validateField(event.target); });
$("#applicationForm").addEventListener("change", event => {
  if (event.target.type !== "file") return;
  validateField(event.target);
  const card = event.target.closest(".upload-card");
  const file = event.target.files[0];
  card.classList.toggle("has-file", Boolean(file));
  $("em", card).textContent = file ? file.name : "Choose file";
  if (["occupancyDoc", "occupancyAffidavitDoc"].includes(event.target.name)) {
    const pair = [$("[name=\"occupancyDoc\"]"), $("[name=\"occupancyAffidavitDoc\"]")];
    if (pair.some(input => input.files[0])) pair.forEach(input => {
      const wrapper = input.closest(".upload-card");
      wrapper.classList.remove("invalid");
      $(".error", wrapper).textContent = "";
    });
  }
});
$("#applicationForm").addEventListener("submit", handleApplicationSubmit);
$("#trackingForm").addEventListener("submit", event => { event.preventDefault(); renderTracking($("#trackingReference").value); });
$("#renewLookupForm").addEventListener("submit", event => { event.preventDefault(); renderRenewal($("#renewPermitNumber").value); });
$("#queueSearch").addEventListener("input", renderQueue);
$("#queueFilter").addEventListener("change", renderQueue);
$("#queueBody").addEventListener("click", event => { const button = event.target.closest("[data-review]"); if (button) openReview(button.dataset.review); });
$("#closeModal").addEventListener("click", closeModal);
$("#modalBackdrop").addEventListener("click", event => { if (event.target === event.currentTarget) closeModal(); });
$("#modalContent").addEventListener("click", event => { const button = event.target.closest("[data-decision]"); if (button) decide(button.dataset.reference, button.dataset.decision); });
document.addEventListener("keydown", event => { if (event.key === "Escape") closeModal(); });
document.addEventListener("click", async event => {
  const copyButton = event.target.closest("[data-copy]");
  if (!copyButton) return;
  const value = copyButton.dataset.copy;
  try { await navigator.clipboard.writeText(value); showToast(`${value} copied.`); }
  catch { showToast(`Reference: ${value}`); }
});

$("#todayLabel").textContent = new Intl.DateTimeFormat("en-PH", { dateStyle: "long" }).format(new Date());
renderAll();
route();
