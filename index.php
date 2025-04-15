<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SPA Importer - Mondus Properties</title>
  <script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="styles.css" />
</head>

<body class="bg-gray-50 text-gray-800 font-sans">
  <div class="max-w-5xl mx-auto py-10 px-4">
    <h1 class="text-3xl font-bold mb-6 text-center">SPA Importer - Mondus Properties</h1>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-xl shadow-lg">
        <div class="flex items-center">
          <svg class="animate-spin h-6 w-6 mr-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
      <input type="file" id="csvFile" accept=".csv" class="w-full p-2 border rounded-xl shadow" />
    </div>

    <!-- Step 3: Field Mapping -->
    <div id="mappingSection" class="mb-6 hidden">
      <h2 class="text-xl font-semibold mb-4">Map CSV Fields to SPA Fields</h2>
      <div id="mappingFields" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
      <div class="mt-6 flex flex-col md:flex-row gap-4">
        <button id="importButton" class="px-6 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex-1">Import Data</button>
        <button id="resetButton" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-xl hover:bg-gray-300 flex-1">Reset</button>
      </div>
    </div>

    <!-- Result -->
    <div id="resultSection" class="mt-8">
      <div id="importSummary" class="hidden mb-4 p-4 rounded-xl border"></div>
      <div id="importResults" class="max-h-96 overflow-y-auto border rounded-xl p-4 hidden"></div>
    </div>
  </div>

  <footer class="mt-10 text-center text-sm text-gray-500">
    Powered by <a href="https://vortexweb.cloud/" class="underline hover:text-gray-700">VortexWeb</a>
  </footer>

  <script src="app.js"></script>
</body>

</html>