<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SPA Importer - Mondus Properties</title>
  <script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-800 font-sans">
  <div class="max-w-5xl mx-auto py-10 px-4">
    <h1 class="text-3xl font-bold mb-6 text-center">
      SPA Importer - Mondus Properties
    </h1>

    <!-- Loading indicator -->
    <div
      id="loadingIndicator"
      class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-xl shadow-lg">
        <div class="flex items-center">
          <svg
            class="animate-spin h-6 w-6 mr-3 text-blue-600"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24">
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span id="loadingText">Processing...</span>
        </div>
      </div>
    </div>

    <!-- Alert message component -->
    <div id="alertBox" class="mb-4 hidden">
      <div id="alertContent" class="p-4 rounded-xl shadow"></div>
    </div>

    <!-- Step 1: Select SPA -->
    <div class="mb-6">
      <label for="spaSelect" class="block text-lg font-semibold mb-2">Select Target SPA</label>
      <select id="spaSelect" class="w-full p-2 border rounded-xl shadow">
        <option value="">Loading SPAs...</option>
      </select>
    </div>

    <!-- Step 2: Upload CSV -->
    <div class="mb-6">
      <label for="csvFile" class="block text-lg font-semibold mb-2">Upload CSV File</label>
      <input
        type="file"
        id="csvFile"
        accept=".csv"
        class="w-full p-2 border rounded-xl shadow" />
    </div>

    <!-- Step 3: Field Mapping -->
    <div id="mappingSection" class="mb-6 hidden">
      <h2 class="text-xl font-semibold mb-4">Map CSV Fields to SPA Fields</h2>
      <div
        id="mappingFields"
        class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      <div class="mt-6 flex flex-col md:flex-row gap-4">
        <button
          id="importButton"
          class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex-1">
          Import Data
        </button>
        <button
          id="resetButton"
          class="px-6 py-2 bg-gray-200 text-gray-800 rounded-xl hover:bg-gray-300 flex-1">
          Reset
        </button>
      </div>
    </div>

    <!-- Result -->
    <div id="resultSection" class="mt-8">
      <div id="importSummary" class="hidden mb-4 p-4 rounded-xl border"></div>
      <div
        id="importResults"
        class="max-h-96 overflow-y-auto border rounded-xl p-4 hidden"></div>
    </div>
  </div>

  <footer class="mt-10 text-center text-sm text-gray-500">
    Powered by
    <a href="https://vortexweb.cloud/" class="underline hover:text-gray-700">VortexWeb</a>
  </footer>

  <script>
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
      spaFields: [],
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
          type === "error" ?
          "bg-red-100 text-red-800" :
          "bg-green-100 text-green-800";
        elements.alertContent.className = `p-4 rounded-xl shadow ${bgClass}`;
        elements.alertContent.textContent = message;
        elements.alertBox.classList.remove("hidden");

        // Auto hide after 5 seconds
        setTimeout(() => {
          elements.alertBox.classList.add("hidden");
        }, 5000);
      },

      reset: () => {
        // Reset form
        elements.csvFile.value = "";
        elements.mappingSection.classList.add("hidden");
        elements.importResults.classList.add("hidden");
        elements.importSummary.classList.add("hidden");

        // Reset state
        state.csvData = [];
        state.csvHeaders = [];

        // Enable fields
        elements.spaSelect.disabled = false;
        elements.csvFile.disabled = false;
        elements.importButton.disabled = false;
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
          elements.spaSelect.innerHTML =
            '<option value="">Select SPA</option>';
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
          const titles = Object.entries(fields).map(([key, field]) => ({
            id: key,
            title: field.title,
          }));
          return titles;
        } catch (error) {
          helpers.showAlert(`Failed to load SPA fields: ${error.message}`);
          console.error("Failed to load SPA fields", error);
          return [];
        } finally {
          helpers.hideLoading();
        }
      },

      async importItem(spaId, item) {
        console.log("Importing item:", item);
        try {
          const response = await fetch(
            `${API_URL}/crm.item.add?entityTypeId=${spaId}`, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                fields: item
              }),
            }
          );

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          const data = await response.json();

          if (data.error) {
            throw new Error(data.error);
          }

          return {
            success: true,
            id: data.result.item.id,
          };
        } catch (error) {
          return {
            success: false,
            error: error.message,
          };
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
            // Auto-map fields with the same name
            const selected =
              header.toLowerCase() === field.title.toLowerCase() ? "selected" : "";
            selectHTML += `<option value="${header}" ${selected}>${header}</option>`;
          });
          selectHTML += "</select>";

          elements.mappingFields.innerHTML += `
            <div>
              <label class="block font-medium mb-1">${field.title}</label>
              ${selectHTML}
            </div>`;
        });
      },

      renderResults(results) {
        const successful = results.filter((r) => r.success).length;
        const failed = results.length - successful;

        // Show summary
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

        // Show detailed results
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
                  result.item
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

        // Enable CSV upload if fields were retrieved
        if (state.spaFields && state.spaFields.length) {
          elements.csvFile.disabled = false;

          // If CSV is already loaded, show mapping
          if (state.csvData.length > 0) {
            ui.renderFieldMapping();
            elements.mappingSection.classList.remove("hidden");
          }
        } else {
          elements.csvFile.disabled = true;
          helpers.showAlert(
            "No fields available for the selected SPA.",
            "error"
          );
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
          complete: function(results) {
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
          error: function(error) {
            helpers.hideLoading();
            helpers.showAlert(`CSV parsing error: ${error.message}`, "error");
          },
        });
      },

      async onImport() {
        if (state.importing) return;

        // Collect field mappings
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

        // Disable UI during import
        state.importing = true;
        elements.importButton.disabled = true;
        elements.spaSelect.disabled = true;
        elements.csvFile.disabled = true;
        helpers.showLoading("Importing data...");

        // Process each row
        const results = [];
        const totalRows = state.csvData.length;

        for (let i = 0; i < totalRows; i++) {
          const row = state.csvData[i];
          const item = {};

          // Map fields according to the mapping
          for (const [spaField, csvField] of Object.entries(fieldMap)) {
            const value = row[csvField];

            if (value === undefined || value === null) {
              console.warn(`Missing value for field: ${csvField}`);
              continue;
            }

            // Assign only if valid
            item[spaField] = value;
          }

          // Import the item
          const result = await api.importItem(state.spaId, item);
          result.item = item; // Add the item data for reference in error reporting
          results.push(result);
        }

        // Re-enable UI
        state.importing = false;
        elements.importButton.disabled = false;
        helpers.hideLoading();

        // Show results
        ui.renderResults(results);
      },

      onReset() {
        helpers.reset();
      },
    };

    // Initialize
    function init() {
      // Fetch SPAs on load
      api.fetchSPAs();

      // Add event listeners
      elements.spaSelect.addEventListener("change", handlers.onSpaSelect);
      elements.csvFile.addEventListener("change", handlers.onCsvUpload);
      elements.importButton.addEventListener("click", handlers.onImport);
      elements.resetButton.addEventListener("click", handlers.onReset);

      // Disable CSV upload initially
      elements.csvFile.disabled = true;
    }

    // Start the application
    document.addEventListener("DOMContentLoaded", init);
  </script>
</body>

</html>