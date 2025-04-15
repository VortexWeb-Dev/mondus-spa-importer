// Constants
const API_URL = "https://mondus.group/rest/1/dw9gd4xauhctd7ha";

// DOM Elements
const elements = {
  spaSelect: document.getElementById("spaSelect"),
  csvFile: document.getElementById("csvFile"),
  mappingSection: document.getElementById("mappingSection"),
  mappingFields: document.getElementById("mappingFields"),
  importButton: document.getElementById("importButton"),
  resetButton: document.getElementById("resetButton"),
  resultSection: document.getElementById("resultSection"),
  importResults: document.getElementById("importResults"),
  importSummary: document.getElementById("importSummary"),
  loadingIndicator: document.getElementById("loadingIndicator"),
  loadingText: document.getElementById("loadingText"),
  alertBox: document.getElementById("alertBox"),
  alertContent: document.getElementById("alertContent"),
};

// State
const state = {
  csvData: [],
  csvHeaders: [],
  spaFields: [], // Includes type, enumOptions, and isRequired
  spaId: "",
  importing: false,
};

// Helper functions
const helpers = {
  showLoading: (message = "Processing...") => {
    elements.loadingText.textContent = message;
    elements.loadingIndicator.classList.remove("hidden");
  },

  hideLoading: () => {
    elements.loadingIndicator.classList.add("hidden");
  },

  showAlert: (message, type = "error") => {
    const bgClass =
      type === "error"
        ? "bg-red-100 text-red-800"
        : "bg-green-100 text-green-800";
    elements.alertContent.className = `p-4 rounded-xl shadow ${bgClass}`;
    elements.alertContent.textContent = message;
    elements.alertBox.classList.remove("hidden");

    setTimeout(() => {
      elements.alertBox.classList.add("hidden");
    }, 5000);
  },

  reset: () => {
    elements.csvFile.value = "";
    elements.mappingSection.classList.add("hidden");
    elements.importResults.classList.add("hidden");
    elements.importSummary.classList.add("hidden");
    state.csvData = [];
    state.csvHeaders = [];
    elements.spaSelect.disabled = false;
    elements.csvFile.disabled = false;
    elements.importButton.disabled = false;
  },

  // Validate and sanitize data based on field type
  sanitizeValue: (value, fieldType, fieldName) => {
    if (value === undefined || value === null || value === "") {
      return null;
    }

    switch (fieldType) {
      case "integer":
      case "double":
        const num = parseFloat(value.replace(/[^0-9.-]+/g, ""));
        if (isNaN(num)) {
          console.warn(`Invalid number for ${fieldName}: ${value}`);
          return null;
        }
        return fieldType === "integer" ? Math.floor(num) : num;
      case "date":
      case "datetime":
        const parsedDate = new Date(value);
        if (isNaN(parsedDate.getTime())) {
          console.warn(`Invalid date for ${fieldName}: ${value}`);
          return null;
        }
        return parsedDate.toISOString();
      case "boolean":
        const lowerValue = value.toLowerCase();
        return ["true", "yes", "1", "on"].includes(lowerValue)
          ? true
          : ["false", "no", "0", "off"].includes(lowerValue)
          ? false
          : null;
      default:
        return String(value).trim();
    }
  },
};

// API functions
const api = {
  async fetchSPAs() {
    helpers.showLoading("Loading SPAs...");
    try {
      const response = await fetch(`${API_URL}/crm.type.list`);
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error || "Failed to fetch SPAs");
      }

      if (result.error) {
        throw new Error(result.error);
      }

      const types = result.result.types;
      elements.spaSelect.innerHTML = '<option value="">Select SPA</option>';
      types.forEach((type) => {
        elements.spaSelect.innerHTML += `<option value="${type.entityTypeId}">${type.title}</option>`;
      });

      return types;
    } catch (error) {
      helpers.showAlert(`Failed to load SPAs: ${error.message}`);
      console.error("Failed to load SPAs", error);
      return [];
    } finally {
      helpers.hideLoading();
    }
  },

  async fetchSPAFields(spaId) {
    helpers.showLoading("Loading SPA fields...");
    try {
      const response = await fetch(
        `${API_URL}/crm.item.fields?entityTypeId=${spaId}`
      );

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (result.error) {
        throw new Error(result.error);
      }

      const fields = result.result.fields || {};
      const fieldPromises = Object.entries(fields).map(async ([key, field]) => {
        const fieldData = {
          id: key,
          title: field.title,
          type: field.type,
          isRequired: field.isRequired || false,
        };
        console.log("fieldData:", fieldData);

        // Handle enumeration fields
        if (field.type === "enumeration" && field.items) {
          fieldData.enumOptions = field.items.map((item) => ({
            id: item.ID,
            value: item.VALUE,
          }));
        }

        return fieldData;
      });

      const fieldData = await Promise.all(fieldPromises);
      return fieldData;
    } catch (error) {
      helpers.showAlert(`Failed to load SPA fields: ${error.message}`);
      console.error("Failed to load SPA fields", error);
      return [];
    } finally {
      helpers.hideLoading();
    }
  },

  async importItem(spaId, item) {
    console.log("Importing item:", JSON.stringify(item, null, 2));
    try {
      const response = await fetch(
        `${API_URL}/crm.item.add?entityTypeId=${spaId}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            fields: item,
          }),
        }
      );

      const data = await response.json();

      if (!response.ok) {
        throw new Error(
          `HTTP error! status: ${response.status}, message: ${
            data.error_description || data.error || "Unknown error"
          }`
        );
      }

      if (data.error) {
        throw new Error(data.error_description || data.error);
      }

      return {
        success: true,
        id: data.result.item.id,
      };
    } catch (error) {
      console.error("Import error:", error);
      return {
        success: false,
        error: error.message,
        item: item,
      };
    }
  },

  async fetchFileAsBase64(url) {
    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`Failed to fetch file: ${response.status}`);
      }
      const blob = await response.blob();
      const base64 = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result.split(",")[1]);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
      return base64;
    } catch (error) {
      console.error(`Failed to fetch file from URL: ${url}`, error);
      return null;
    }
  },
};

// UI rendering functions
const ui = {
  renderFieldMapping() {
    elements.mappingFields.innerHTML = "";

    if (!state.spaFields || !state.spaFields.length) {
      elements.mappingFields.innerHTML = `<div class="col-span-2 text-red-600">No fields available for the selected SPA.</div>`;
      return;
    }

    state.spaFields.forEach((field) => {
      let selectHTML = `<select class="w-full p-2 border rounded-xl shadow" data-field="${field.id}">
        <option value="">-- Map to CSV --</option>`;
      state.csvHeaders.forEach((header) => {
        const selected =
          header.toLowerCase() === field.title.toLowerCase() ? "selected" : "";
        selectHTML += `<option value="${header}" ${selected}>${header}</option>`;
      });
      selectHTML += "</select>";

      elements.mappingFields.innerHTML += `
        <div>
          <label class="block font-medium mb-1">${field.title} (${field.type})${
        field.isRequired ? " (Required)" : ""
      }</label>
          ${selectHTML}
        </div>`;
    });
  },

  renderResults(results) {
    const successful = results.filter((r) => r.success).length;
    const failed = results.length - successful;

    elements.importSummary.innerHTML = `
      <div class="flex justify-between items-center">
        <h3 class="text-lg font-semibold">Import Results</h3>
        <div>
          <span class="text-green-600 font-medium">${successful} Successful</span>
          <span class="mx-2">|</span>
          <span class="text-red-600 font-medium">${failed} Failed</span>
        </div>
      </div>
    `;
    elements.importSummary.classList.remove("hidden");

    elements.importResults.innerHTML = results
      .map((result) => {
        if (result.success) {
          return `<div class="text-green-600 mb-2 p-2 bg-green-50 rounded">
            <span class="font-medium">✓ Success:</span> Item imported with ID ${result.id}
          </div>`;
        } else {
          return `<div class="text-red-600 mb-2 p-2 bg-red-50 rounded">
            <span class="font-medium">✗ Error:</span> ${result.error}
            <div class="mt-1 text-sm text-gray-700">Item: ${JSON.stringify(
              result.item,
              null,
              2
            )}</div>
          </div>`;
        }
      })
      .join("");

    elements.importResults.classList.remove("hidden");
  },
};

// Event handlers
const handlers = {
  async onSpaSelect() {
    const spaId = elements.spaSelect.value;
    if (!spaId) return;

    state.spaId = spaId;
    state.spaFields = await api.fetchSPAFields(spaId);

    if (state.spaFields && state.spaFields.length) {
      elements.csvFile.disabled = false;
      if (state.csvData.length > 0) {
        ui.renderFieldMapping();
        elements.mappingSection.classList.remove("hidden");
      }
    } else {
      elements.csvFile.disabled = true;
      helpers.showAlert("No fields available for the selected SPA.", "error");
    }
  },

  onCsvUpload() {
    const file = elements.csvFile.files[0];
    if (!file) return;

    if (!state.spaId) {
      helpers.showAlert("Please select a SPA first.", "error");
      elements.csvFile.value = "";
      return;
    }

    helpers.showLoading("Parsing CSV...");

    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: function (results) {
        helpers.hideLoading();

        if (results.errors && results.errors.length > 0) {
          helpers.showAlert(
            `CSV parsing error: ${results.errors[0].message}`,
            "error"
          );
          return;
        }

        if (!results.data || results.data.length === 0) {
          helpers.showAlert("The CSV file appears to be empty.", "error");
          return;
        }

        state.csvData = results.data;
        state.csvHeaders = results.meta.fields;

        ui.renderFieldMapping();
        elements.mappingSection.classList.remove("hidden");
      },
      error: function (error) {
        helpers.hideLoading();
        helpers.showAlert(`CSV parsing error: ${error.message}`, "error");
      },
    });
  },

  async onImport() {
    if (state.importing) return;

    const selects = elements.mappingFields.querySelectorAll("select");
    const fieldMap = {};

    let hasMapping = false;
    selects.forEach((select) => {
      if (select.value) {
        fieldMap[select.dataset.field] = select.value;
        hasMapping = true;
      }
    });

    if (!hasMapping) {
      helpers.showAlert(
        "Please map at least one field before importing.",
        "error"
      );
      return;
    }

    // Check for required fields
    const missingRequired = state.spaFields.filter(
      (f) => f.isRequired && !fieldMap[f.id]
    );
    if (missingRequired.length > 0) {
      helpers.showAlert(
        `Missing required fields: ${missingRequired
          .map((f) => f.title)
          .join(", ")}`,
        "error"
      );
      return;
    }

    state.importing = true;
    elements.importButton.disabled = true;
    elements.spaSelect.disabled = true;
    elements.csvFile.disabled = true;
    helpers.showLoading("Importing data...");

    const results = [];
    const totalRows = state.csvData.length;

    console.log("Field Map:", fieldMap);

    for (let i = 0; i < totalRows; i++) {
      const row = state.csvData[i];
      const item = {};

      for (const [spaFieldId, csvField] of Object.entries(fieldMap)) {
        let value = row[csvField];

        if (value === undefined || value === null || value === "") {
          console.warn(`Missing value for field: ${csvField}`);
          if (state.spaFields.find((f) => f.id === spaFieldId).isRequired) {
            console.error(`Required field ${csvField} is empty`);
            results.push({
              success: false,
              error: `Required field ${csvField} is empty`,
              item: row,
            });
            continue;
          }
          continue;
        }

        const spaField = state.spaFields.find((f) => f.id === spaFieldId);

        // Sanitize value based on field type
        value = helpers.sanitizeValue(value, spaField.type, spaField.title);
        if (value === null && spaField.isRequired) {
          console.error(`Invalid value for required field ${spaField.title}`);
          results.push({
            success: false,
            error: `Invalid value for required field ${spaField.title}`,
            item: row,
          });
          continue;
        } else if (value === null) {
          continue;
        }

        if (spaField.type === "enumeration" && spaField.enumOptions) {
          const enumOption = spaField.enumOptions.find(
            (opt) => opt.value.toLowerCase() === value.toLowerCase()
          );
          if (enumOption) {
            item[spaFieldId] = enumOption.id;
          } else {
            console.warn(
              `No matching enum option for value: ${value} in field: ${csvField}`
            );
            results.push({
              success: false,
              error: `No matching enum option for ${csvField}: ${value}`,
              item: row,
            });
            continue;
          }
        } else if (
          typeof value === "string" &&
          (value.includes("http://") || value.includes("https://"))
        ) {
          const fileUrls = value
            .split(/[,|]/)
            .map((link) => link.trim())
            .filter((link) => link);

          const files = await Promise.all(
            fileUrls.map(async (url) => {
              const fileName = url.split("/").pop().split("?")[0];
              const uniqueName = `${Date.now()}_${Math.floor(
                Math.random() * 10000
              )}_${fileName}`;
              const base64 = await api.fetchFileAsBase64(url);
              if (base64) {
                return {
                  name: uniqueName,
                  base64: base64,
                };
              }
              return null;
            })
          );

          const validFiles = files.filter((f) => f !== null);
          if (validFiles.length > 0) {
            item[spaFieldId] = validFiles;
          } else {
            console.warn(`No valid files for field: ${csvField}`);
            if (spaField.isRequired) {
              results.push({
                success: false,
                error: `No valid files for required field ${csvField}`,
                item: row,
              });
              continue;
            }
          }
        } else {
          item[spaFieldId] = value;
        }
      }

      if (Object.keys(item).length === 0) {
        results.push({
          success: false,
          error: "No valid fields mapped for this row",
          item: row,
        });
        continue;
      }

      const result = await api.importItem(state.spaId, item);
      result.item = item;
      results.push(result);
    }

    state.importing = false;
    elements.importButton.disabled = false;
    elements.spaSelect.disabled = false;
    elements.csvFile.disabled = false;
    helpers.hideLoading();

    console.log("Import results:", results);
    ui.renderResults(results);
  },

  onReset() {
    helpers.reset();
  },
};

// Initialize
function init() {
  api.fetchSPAs();
  elements.spaSelect.addEventListener("change", handlers.onSpaSelect);
  elements.csvFile.addEventListener("change", handlers.onCsvUpload);
  elements.importButton.addEventListener("click", handlers.onImport);
  elements.resetButton.addEventListener("click", handlers.onReset);
  elements.csvFile.disabled = true;
}

// Start the application
document.addEventListener("DOMContentLoaded", init);
