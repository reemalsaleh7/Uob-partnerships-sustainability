<?php
$embeddedMap = isset($_GET['embed']) && $_GET['embed'] === '1';

$pageTitle = "خريطة الاتفاقيات";
$hidePageHeader = true;
$mainContainer = false;

if (!$embeddedMap) {
  $extraCss = ['partnership/styles.css'];
  $extraHead = '
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.0.0/css/flag-icons.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  ';

  require_once __DIR__ . '/../header.php';
}

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isRtl = ($lang === 'ar');

if (!function_exists('ph')) {
  function ph($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

$mapLabel = $isRtl ? 'خريطة الاتفاقيات' : 'Partnership Map';
?>
<?php if ($embeddedMap): ?>
<link rel="stylesheet" href="styles.css?v=20">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.0.0/css/flag-icons.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  body {
    margin: 0 !important;
    background: #ffffff !important;
    font-family: "Cairo", system-ui, Arial, sans-serif !important;
  }

  .partnership-hero {
    display: none !important;
  }

  .partnership-page-wrapper {
    background: #ffffff !important;
    padding-top: 0 !important;
  }
</style>
<?php endif; ?>
<div class="partnership-page-wrapper" style="background:#ffffff !important;">

  <section class="partnership-hero"
    style="background:#ffffff !important; background-image:none !important; min-height:330px !important; padding:70px 0 45px !important;">

    <div class="partnership-hero-inner"
      style="background:#ffffff !important; background-image:none !important;">

      <div class="partnership-hero-content"
        style="text-align:center !important; margin:0 auto !important;">

        <h1 style="color:#1a1a1a !important;">
          <?= ph($mapLabel) ?>
        </h1>

        <p style="color:#b8860b !important;">
    </div>
  </div>
</section>

  <main class="partnership-page" dir="ltr">



    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <h2 class="section-title-pro">Partnership Network</h2>

            <!-- Search and Filter Controls -->
            <div class="search-filter-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Search by institution name, country, or keyword..."
                        class="search-input">
                </div>

                <div class="filter-controls">
                    <!-- Filters Dropdown -->
                    <div class="dropdown-filter">
                        <button class="dropdown-filter-btn" id="advancedFiltersBtn">
                            <i class="fas fa-filter" style="font-size: 20px; color: #003366;"></i> Filters
                            <span class="filter-badge" id="filter-count" style="display: none;">0</span>
                        </button>
                        <div class="dropdown-filter-content" id="filter-dropdown">
                            <!-- All filters will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Clear Filters Button -->
                    <button class="clear-filters-btn" id="clearAllFiltersBtn">
                        <i class="fas fa-times" style="font-size: 20px; color: #b82e1f;"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Active Filters Display (Hidden) -->
            <div id="activeFiltersBar" class="active-filters-bar" style="display: none;"></div>

            <!-- Map Container -->
            <div id="mapContainerWrapper" class="map-container">
                <div id="partnershipMap"></div>
                <div class="map-legend">
                    <!-- Will be populated dynamically -->
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-bar-section">
        <div class="container">
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-label">Countries</span>
                    <span class="stat-number" id="countriesCount">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Relations</span>
                    <span class="stat-number" id="relationsCount">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Programmes</span>
                    <span class="stat-number" id="programmesCount">0</span>
                </div>
                <div class="map-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" id="showMapToggle" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Show Map</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Partnerships List Section -->
    <section class="partnerships-list-section">
        <div class="container-full">
            <div class="partnerships-layout">
                <!-- Left Sidebar - Countries List -->
                <div class="sidebar-container">
                    <div id="countriesSidebar" class="countries-sidebar">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>

                <!-- Right Content - Partnership Details -->
                <div class="content-container">
                    <div id="partnershipDetail" class="partnership-detail-container">
                        <div class="empty-state">
                            <div class="empty-icon">🤝</div>
                            <h3>Select a partnership to view details</h3>
                            <p>Click on any institution from the list on the left to see detailed information</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // ============================================
        // FILTER SYSTEM
        // ============================================

        let advancedFilters = {};
        let allFilterOptions = {};

        const FILTER_CONFIG = [
            {
                id: 'status',
                label: 'Agreement Status',
                field: 'Status',
            },
            {
                id: 'agreementType',
                label: 'Agreement Type',
                field: 'Agreement Type',
            },
            {
                id: 'sdgs',
                label: 'Sustainable Development Goals',
                field: 'SDGs',
            },
            {
                id: 'partnerType',
                label: 'Partner Type',
                field: 'Partner Type',
            },
            {
                id: 'implementingUnit',
                label: 'Implementing Unit',
                field: 'Implementing Unit',
            },
            {
                id: 'qsRanking',
                label: 'Supports QS Ranking',
                field: 'Supports QS Ranking',
                type: 'boolean',
            },
            {
                id: 'greenMetric',
                label: 'Supports UI GreenMetric',
                field: 'Supports UI GreenMetric',
                type: 'boolean',
            }
        ];

        // Initialize advanced filters
        function initializeAdvancedFilters() {
            setupAdvancedFilterListeners();
        }

        // Setup event listeners for advanced filters
        function setupAdvancedFilterListeners() {
            const advancedFiltersBtn = document.getElementById('advancedFiltersBtn');
            const filterDropdown = document.getElementById('filter-dropdown');
            const clearAllFiltersBtn = document.getElementById('clearAllFiltersBtn');

            if (advancedFiltersBtn && filterDropdown) {
                advancedFiltersBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    filterDropdown.classList.toggle('show');
                    advancedFiltersBtn.classList.toggle('active');
                });
            }

            if (clearAllFiltersBtn) {
                clearAllFiltersBtn.addEventListener('click', clearAllAdvancedFilters);
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown-filter')) {
                    filterDropdown?.classList.remove('show');
                    advancedFiltersBtn?.classList.remove('active');
                }
            });
        }

        // Generate filter options from data
        function generateFilterOptions(data) {
            allFilterOptions = {};
            const filterDropdown = document.getElementById('filter-dropdown');

            if (!filterDropdown) return;

            filterDropdown.innerHTML = '';

            FILTER_CONFIG.forEach(filter => {
                const options = new Set();

                data.forEach(item => {
                    let value = item[filter.field];

                    if (value !== null && value !== undefined && value !== '') {
                        if (filter.type === 'boolean') {
                            // Convert boolean values to Yes/No
                            if (value === true || value === 'Yes' || value === 'TRUE' || value === 'yes') {
                                options.add('Yes');
                            } else {
                                options.add('No');
                            }
                        } else {
                            // Handle multiple values separated by commas or semicolons
                            const values = String(value).split(/[,;]/).map(v => v.trim()).filter(v => v);
                            values.forEach(v => options.add(v));
                        }
                    }
                });

                // Sort options with special handling for SDGs
                if (filter.id === 'sdgs') {
                    // Sort SDGs numerically
                    allFilterOptions[filter.id] = Array.from(options).sort((a, b) => {
                        const numA = parseInt(a.match(/\d+/)?.[0] || 0);
                        const numB = parseInt(b.match(/\d+/)?.[0] || 0);
                        return numA - numB;
                    });
                } else {
                    // Sort alphabetically for other filters
                    allFilterOptions[filter.id] = Array.from(options).sort();
                }

                // Only create section if there are options
                if (allFilterOptions[filter.id].length > 0) {
                    const filterSection = document.createElement('div');
                    filterSection.className = 'filter-section';

                    const filterHeader = document.createElement('h4');
                    filterHeader.textContent = filter.label;
                    filterSection.appendChild(filterHeader);

                    const filterOptions = document.createElement('div');
                    filterOptions.className = 'filter-options';

                    allFilterOptions[filter.id].forEach(option => {
                        const optionId = `${filter.id}_${option.replace(/\s+/g, '_').replace(/[^\w-]/g, '')}`;
                        const isChecked = advancedFilters[filter.id]?.includes(option) ? 'checked' : '';

                        const optionDiv = document.createElement('div');
                        optionDiv.className = 'filter-option';
                        optionDiv.innerHTML = `
                    <input type="checkbox" 
                           id="${optionId}" 
                           value="${option}" 
                           data-filter="${filter.id}"
                           ${isChecked}>
                    <label for="${optionId}">${option}</label>
                `;

                        // Add change event listener to each checkbox
                        const checkbox = optionDiv.querySelector('input[type="checkbox"]');
                        checkbox.addEventListener('change', (e) => {
                            const filterId = e.target.dataset.filter;
                            const value = e.target.value;

                            if (!advancedFilters[filterId]) {
                                advancedFilters[filterId] = [];
                            }

                            if (e.target.checked) {
                                if (!advancedFilters[filterId].includes(value)) {
                                    advancedFilters[filterId].push(value);
                                }
                            } else {
                                advancedFilters[filterId] = advancedFilters[filterId].filter(v => v !== value);
                                if (advancedFilters[filterId].length === 0) {
                                    delete advancedFilters[filterId];
                                }
                            }

                            updateFilterCount();
                            applyFilters();
                        });

                        filterOptions.appendChild(optionDiv);
                    });

                    filterSection.appendChild(filterOptions);
                    filterDropdown.appendChild(filterSection);
                }
            });
        }

        // Update filter count badge
        function updateFilterCount() {
            const filterCount = document.getElementById('filter-count');
            const totalFilters = Object.values(advancedFilters).reduce((sum, arr) => sum + arr.length, 0);

            if (filterCount) {
                if (totalFilters > 0) {
                    filterCount.textContent = totalFilters;
                    filterCount.style.display = 'inline-block';
                } else {
                    filterCount.textContent = '0';
                    filterCount.style.display = 'none';
                }
            }
        }

        // Clear all advanced filters
        function clearAllAdvancedFilters() {
            advancedFilters = {};

            // Uncheck all checkboxes in dropdown
            const checkboxes = document.querySelectorAll('#filter-dropdown input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            // Clear search input
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = '';
            }

            // Reset map view to initial position
            if (typeof map !== 'undefined' && map) {
                map.setView([26.0667, 50.5577], 2, {
                    animate: true,
                    duration: 1
                });

                // Close all popups
                map.closePopup();

                // Remove all individual markers and restore clusters
                map.eachLayer(function (layer) {
                    if (layer instanceof L.Marker && layer.options.icon?.options?.className === 'custom-marker-individual') {
                        map.removeLayer(layer);
                    }
                });

                // Restore all cluster markers
                if (typeof markers !== 'undefined') {
                    markers.forEach(({ marker }) => {
                        if (!map.hasLayer(marker)) {
                            marker.addTo(map);
                        }
                    });
                }
            }

            updateFilterCount();
            applyFilters();
        }

        // Check if partnership matches advanced filters
        function matchesAdvancedFilters(partnership) {
            return Object.keys(advancedFilters).every(filterId => {
                const filterConfig = FILTER_CONFIG.find(f => f.id === filterId);
                if (!filterConfig) return true;

                const fieldValue = partnership[filterConfig.field];
                if (!fieldValue) return false;

                if (filterConfig.type === 'boolean') {
                    const normalizedValue = (fieldValue === true || fieldValue === 'Yes' || fieldValue === 'TRUE' || fieldValue === 'yes') ? 'Yes' : 'No';
                    return advancedFilters[filterId].includes(normalizedValue);
                } else {
                    // Handle multiple values in the field
                    const values = String(fieldValue).split(/[,;]/).map(v => v.trim());
                    return advancedFilters[filterId].some(filterValue => {
                        return values.some(v => v === filterValue);
                    });
                }
            });
        }

        // ============================================
        // MAIN APPLICATION CODE
        // ============================================

        // Fetch partnerships data from API
        let partnershipsData = [];

        let map;
        let markers = [];

        // ============================================
        // COLOR CONFIGURATION 
        // ============================================
        const PRESET_COLORS = [
            '#003f7fdb',
            '#2a9d8fdb',
            '#9b59b6db',
            '#e74c3cdb',
            '#06b6d4db',
            '#84cc16db',
            '#ec4899db',
            '#f97316db',
            '#6366f1db',
            '#f77f00db'
        ];

        const dynamicTypeColors = {}; // built at runtime

        // Get color for any agreement type
        function getMarkerColor(type) {
            if (!dynamicTypeColors[type]) {
                const index = Object.keys(dynamicTypeColors).length;
                dynamicTypeColors[type] = PRESET_COLORS[index % PRESET_COLORS.length];
            }
            return dynamicTypeColors[type];
        }

        // Get label for agreement type
        function getTypeLabel(type) {
            return type;
        }

        function getCountryCode(country) {
            const codes = {
                'United States': 'us',
                'United Kingdom': 'gb',
                'Saudi Arabia': 'sa',
                'France': 'fr',
                'Germany': 'de',
                'Japan': 'jp',
                'Singapore': 'sg',
                'Australia': 'au',
                'United Arab Emirates': 'ae',
                'UAE': 'ae',
                'Qatar': 'qa',
                'Egypt': 'eg',
                'South Korea': 'kr',
                'Korea': 'kr',
                'China': 'cn',
                'Canada': 'ca',
                'India': 'in',
                'Malaysia': 'my',
                'Turkey': 'tr',
                'Brazil': 'br',
                'Spain': 'es',
                'Italy': 'it',
                'Netherlands': 'nl',
                'Belgium': 'be',
                'Switzerland': 'ch',
                'Sweden': 'se',
                'Norway': 'no',
                'Denmark': 'dk',
                'Finland': 'fi',
                'Poland': 'pl',
                'Russia': 'ru',
                'South Africa': 'za',
                'Morocco': 'ma',
                'Tunisia': 'tn',
                'Lebanon': 'lb',
                'Jordan': 'jo',
                'Oman': 'om',
                'Kuwait': 'kw',
                'Bahrain': 'bh',
                'Pakistan': 'pk',
                'Bangladesh': 'bd',
                'Thailand': 'th',
                'Vietnam': 'vn',
                'Indonesia': 'id',
                'Philippines': 'ph',
                'New Zealand': 'nz',
                'Greece': 'gr',
                'Portugal': 'pt',
                'Austria': 'at',
                'Ireland': 'ie',
                'Czech Republic': 'cz',
                'Hungary': 'hu',
                'Romania': 'ro',
                'Israel': 'il',
                'Mexico': 'mx',
                'Argentina': 'ar',
                'Chile': 'cl',
                'Colombia': 'co',
                'Peru': 'pe',
                'Venezuela': 've',
                'Iran': 'ir',
                'Iraq': 'iq',
                'Syria': 'sy',
                'Yemen': 'ye',
                'Afghanistan': 'af',
                'Sri Lanka': 'lk',
                'Nepal': 'np',
                'Myanmar': 'mm',
                'Cambodia': 'kh',
                'Laos': 'la',
                'Hong Kong': 'hk',
                'Taiwan': 'tw',
                'Macao': 'mo'
            };
            return codes[country] || 'xx';
        }

        function getRegion(country) {
            const regions = {
                // North America
                'United States': 'north-america',
                'Canada': 'north-america',
                'Mexico': 'north-america',

                // Europe
                'United Kingdom': 'europe',
                'France': 'europe',
                'Germany': 'europe',
                'Spain': 'europe',
                'Italy': 'europe',
                'Netherlands': 'europe',
                'Belgium': 'europe',
                'Switzerland': 'europe',
                'Sweden': 'europe',
                'Norway': 'europe',
                'Denmark': 'europe',
                'Finland': 'europe',
                'Poland': 'europe',
                'Russia': 'europe',
                'Greece': 'europe',
                'Portugal': 'europe',
                'Austria': 'europe',
                'Ireland': 'europe',
                'Czech Republic': 'europe',
                'Hungary': 'europe',
                'Romania': 'europe',

                // Middle East
                'Saudi Arabia': 'middle-east',
                'United Arab Emirates': 'middle-east',
                'UAE': 'middle-east',
                'Qatar': 'middle-east',
                'Bahrain': 'middle-east',
                'Kuwait': 'middle-east',
                'Oman': 'middle-east',
                'Lebanon': 'middle-east',
                'Jordan': 'middle-east',
                'Turkey': 'middle-east',
                'Israel': 'middle-east',
                'Iran': 'middle-east',
                'Iraq': 'middle-east',
                'Syria': 'middle-east',
                'Yemen': 'middle-east',

                // Africa
                'Egypt': 'africa',
                'South Africa': 'africa',
                'Morocco': 'africa',
                'Tunisia': 'africa',

                // Asia
                'Japan': 'asia',
                'China': 'asia',
                'South Korea': 'asia',
                'Korea': 'asia',
                'Singapore': 'asia',
                'Malaysia': 'asia',
                'Thailand': 'asia',
                'Vietnam': 'asia',
                'Indonesia': 'asia',
                'Philippines': 'asia',
                'India': 'asia',
                'Pakistan': 'asia',
                'Bangladesh': 'asia',
                'Afghanistan': 'asia',
                'Sri Lanka': 'asia',
                'Nepal': 'asia',
                'Myanmar': 'asia',
                'Cambodia': 'asia',
                'Laos': 'asia',
                'Hong Kong': 'asia',
                'Taiwan': 'asia',
                'Macao': 'asia',

                // Oceania
                'Australia': 'oceania',
                'New Zealand': 'oceania',

                // South America
                'Brazil': 'south-america',
                'Argentina': 'south-america',
                'Chile': 'south-america',
                'Colombia': 'south-america',
                'Peru': 'south-america',
                'Venezuela': 'south-america'
            };
            return regions[country] || 'other';
        }

        function normalizeType(agreementType) {
            return agreementType || 'Not specified';
        }

        // SDG Mapping with official colors
        const SDG_INFO = {
            'SDG 1': { name: 'No Poverty', color: '#E5243B' },
            'SDG 2': { name: 'Zero Hunger', color: '#DDA63A' },
            'SDG 3': { name: 'Good Health and Well-being', color: '#4C9F38' },
            'SDG 4': { name: 'Quality Education', color: '#C5192D' },
            'SDG 5': { name: 'Gender Equality', color: '#FF3A21' },
            'SDG 6': { name: 'Clean Water and Sanitation', color: '#26BDE2' },
            'SDG 7': { name: 'Affordable and Clean Energy', color: '#FCC30B' },
            'SDG 8': { name: 'Decent Work and Economic Growth', color: '#A21942' },
            'SDG 9': { name: 'Industry, Innovation and Infrastructure', color: '#FD6925' },
            'SDG 10': { name: 'Reduced Inequalities', color: '#DD1367' },
            'SDG 11': { name: 'Sustainable Cities and Communities', color: '#FD9D24' },
            'SDG 12': { name: 'Responsible Consumption and Production', color: '#BF8B2E' },
            'SDG 13': { name: 'Climate Action', color: '#3F7E44' },
            'SDG 14': { name: 'Life Below Water', color: '#0A97D9' },
            'SDG 15': { name: 'Life on Land', color: '#56C02B' },
            'SDG 16': { name: 'Peace, Justice and Strong Institutions', color: '#00689D' },
            'SDG 17': { name: 'Partnerships for the Goals', color: '#19486A' }
        };

        function parseSDGs(agreement) {
            const sdgs = [];
            if (agreement["SDGs"]) {
                const sdgValues = agreement["SDGs"]
                    .split(/[,;]/)
                    .map(s => s.trim())
                    .filter(s => s);

                // Convert each SDG number to full name with color
                sdgValues.forEach(sdgValue => {
                    // Extract SDG number (handles "SDG 4", "SDG4", "4", etc.)
                    const match = sdgValue.match(/SDG\s*(\d+)|^(\d+)$/i);
                    if (match) {
                        const sdgNumber = match[1] || match[2];
                        const sdgKey = `SDG ${sdgNumber}`;

                        if (SDG_INFO[sdgKey]) {
                            sdgs.push({
                                key: sdgKey,
                                number: parseInt(sdgNumber),  // Store number for sorting
                                name: SDG_INFO[sdgKey].name,
                                fullName: `${sdgKey}: ${SDG_INFO[sdgKey].name}`,
                                color: SDG_INFO[sdgKey].color
                            });
                        }
                    }
                });
            }

            // Sort by SDG number in ascending order
            sdgs.sort((a, b) => a.number - b.number);

            // Return sorted array
            return sdgs;
        }

        function convertExcelDate(excelDate) {
            // Check if it's already a formatted string
            if (typeof excelDate === 'string' && excelDate.includes('/')) {
                return excelDate; // Already formatted
            }

            // Check if it's a valid number (Excel serial date)
            if (typeof excelDate === 'number' && excelDate > 0) {
                // Excel date serial starts from 1900-01-01
                const excelEpoch = new Date(1899, 11, 30); // December 30, 1899
                const date = new Date(excelEpoch.getTime() + excelDate * 86400000); // 86400000 = milliseconds in a day

                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${day}/${month}/${year}`;
            }

            // If it's neither, return as-is or 'Not specified'
            return excelDate || 'Not specified';
        }

        // Fetch data from local file inside this folder (no Node.js server needed)
const apiUrl = 'agreements-api.php';
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to load partnerships data (Status: ${response.status})`);
                }
                return response.json();
            })
            .then(data => {
                // Transform Excel data to match website format
                partnershipsData = data.map(agreement => ({
                    id: String(agreement["Record ID"] || Math.random()),
                    agreementTitle: agreement["Agreement Title"] || agreement["Partner Institution"] || 'Untitled Agreement',
                    name: agreement["Partner Institution"] || 'Unknown Institution',
                    partnerType: agreement["Partner Type"] || 'Not specified',
                    country: agreement["Country"] || 'Unknown',
                    city: agreement["City"] || 'Unknown City',
                    countryCode: getCountryCode(agreement["Country"]),
                    region: getRegion(agreement["Country"]),
                    type: normalizeType(agreement["Agreement Type"]),
                    agreementType: agreement["Agreement Type"] || 'Partnership Agreement',
                    startDate: convertExcelDate(agreement["Start Date"]),
                    endDate: convertExcelDate(agreement["End Date"]),
                    implementingUnit: agreement["Implementing Unit"] || 'Not specified',
                    focusAreas: agreement["Focus Area"] || 'Various',
                    description: agreement["Agreement Summary"] || 'No description available',
                    sdgs: parseSDGs(agreement),
                    metrics: {
                        studentsExchanged: String(agreement["Students Exchanged"] || '0'),
                        facultyExchanged: String(agreement["Faculty Exchanged"] || '0'),
                        jointPrograms: String(agreement["Joint Programs"] || '0')
                    },
                    supportsQS: agreement["Supports QS Ranking"] || 'No',
                    supportsGreenMetric: agreement["Supports UI GreenMetric"] || 'No',
                    website: agreement["Partner Website"] || '',
                    agreementSigningLink: agreement["Agreement Signing Link"] || '',
                    lat: parseFloat(agreement["Latitude"]) || 0,
                    lng: parseFloat(agreement["Longitude"]) || 0,
                    // Store raw fields for advanced filtering
                    "Agreement Type": agreement["Agreement Type"],
                    "SDGs": agreement["SDGs"],
                    "Partner Type": agreement["Partner Type"],
                    "Implementing Unit": agreement["Implementing Unit"],
                    "Supports QS Ranking": agreement["Supports QS Ranking"],
                    "Supports UI GreenMetric": agreement["Supports UI GreenMetric"],
                    "Status": (() => {
                        const endDate = convertExcelDate(agreement["End Date"]);
                        if (!endDate || endDate === 'Not specified') return '';
                        const parts = endDate.split('/');
                        if (parts.length !== 3) return '';
                        const parsed = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
                        if (isNaN(parsed)) return '';
                        const today = new Date(); today.setHours(0, 0, 0, 0);
                        return parsed >= today ? 'Active' : 'Expired';
                    })()
                }));

                // Pre-build color map from all unique types
                const uniqueTypes = [...new Set(partnershipsData.map(p => p.type))];
                uniqueTypes.forEach((type, index) => {
                    dynamicTypeColors[type] = PRESET_COLORS[index % PRESET_COLORS.length];
                });

                // IMPORTANT: Generate filter options after data is loaded
                generateFilterOptions(partnershipsData);

                // Initialize the page after data is loaded
                initializePage();
            })
            .catch(error => {
                console.error('❌ Error loading partnerships:', error);
                console.error('Error details:', error.message);
                console.error('Stack:', error.stack);

                const detailContainer = document.getElementById('partnershipDetail');
                if (detailContainer) {
                    const isFileProtocol = window.location.protocol === 'file:';
                    detailContainer.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">⚠️</div>
                            <h3>Error Loading Data</h3>
                        </div>
                    `;
                }
            });

        function getAgreementStatus(endDate) {
            if (!endDate || endDate === 'Not specified') return null;

            // Parse dd/mm/yyyy
            const parts = endDate.split('/');
            if (parts.length !== 3) return null;

            const parsed = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
            if (isNaN(parsed)) return null;

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            return parsed >= today ? 'active' : 'expired';
        }

        // Initialize page after data is loaded
        function initializePage() {
            if (!document.getElementById('countriesSidebar')) return;

            let filteredPartnerships = [...partnershipsData];

            // IMPORTANT: Initialize advanced filters
            initializeAdvancedFilters();

            // Initialize map
            initializeMap();

            // Build sidebar
            buildSidebar();

            // Update stats
            updateStatistics();

            // Update legend
            updateMapLegend();

            // Setup event listeners
            setupFilters();
            setupMapToggle();

            function initializeMap() {
                map = L.map('partnershipMap', {
                    center: [26.0667, 50.5577],
                    zoom: 2,
                    minZoom: 2,
                    maxZoom: 18,
                    maxBounds: [[-85, -200], [85, 200]],
                    maxBoundsViscosity: 1.0,
                    worldCopyJump: false,
                    zoomControl: true
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(map);
                // Add reset button to the map
                const resetControl = L.Control.extend({
                    options: {
                        position: 'topleft'
                    },

                    onAdd: function (map) {
                        const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');

                        container.innerHTML = `
                            <a href="#" title="Reset Map" style="
                                background-color: white;
                                width: 30px;
                                height: 30px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 18px;
                                text-decoration: none;
                                color: #333;
                                border: 2px solid rgba(0,0,0,0.2);
                                border-radius: 4px;
                            ">⟲</a>
                        `;

                        container.onclick = function (e) {
                            e.preventDefault();

                            // Reset map view to initial position
                            map.setView([26.0667, 50.5577], 2, {
                                animate: true,
                                duration: 1
                            });

                            // Close all popups
                            map.closePopup();

                            // Remove all individual markers and restore clusters
                            map.eachLayer(function (layer) {
                                if (layer instanceof L.Marker && layer.options.icon?.options?.className === 'custom-marker-individual') {
                                    map.removeLayer(layer);
                                }
                            });

                            // Restore all cluster markers
                            markers.forEach(({ marker }) => {
                                if (!map.hasLayer(marker)) {
                                    marker.addTo(map);
                                }
                            });
                        };

                        return container;
                    }
                });

                // Add the reset button to the map
                map.addControl(new resetControl());

                addMarkers();

                setTimeout(() => map.invalidateSize(), 250);
            }

            function addMarkers() {
                markers = [];

                // Group partnerships by COUNTRY
                const partnershipsByCountry = {};
                partnershipsData.forEach(partnership => {
                    if (!partnershipsByCountry[partnership.country]) {
                        partnershipsByCountry[partnership.country] = [];
                    }
                    partnershipsByCountry[partnership.country].push(partnership);
                });

                // Create markers for each country
                Object.entries(partnershipsByCountry).forEach(([country, countryPartnerships]) => {
                    const firstPartnership = countryPartnerships[0];

                    // Skip if coordinates are invalid
                    if (!firstPartnership.lat || !firstPartnership.lng ||
                        firstPartnership.lat === 0 || firstPartnership.lng === 0) {
                        console.warn(`Invalid coordinates for ${country}`);
                        return;
                    }

                    const coords = { lat: firstPartnership.lat, lng: firstPartnership.lng };
                    const partnershipCount = countryPartnerships.length;
                    const color = getMarkerColor(firstPartnership.type);

                    // Create marker HTML based on count
                    const clusteredMarkerHTML = partnershipCount > 1
                        ? `<div style="background-color: ${color}; width: 28px; height: 28px; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">${partnershipCount}</div>`
                        : `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);"></div>`;

                    const iconSize = partnershipCount > 1 ? [35, 35] : [20, 20];
                    const iconAnchor = partnershipCount > 1 ? [17.5, 17.5] : [10, 10];

                    const clusteredIcon = L.divIcon({
                        className: 'custom-marker-cluster',
                        html: clusteredMarkerHTML,
                        iconSize: iconSize,
                        iconAnchor: iconAnchor
                    });

                    const clusteredMarker = L.marker([coords.lat, coords.lng], { icon: clusteredIcon }).addTo(map);

                    // Create popup content
                    const popupContent = partnershipCount > 1
                        ? `<div style="text-align: center; padding: 5px;"><strong style="color: #003f7f;">${country}</strong><br><span style="color: #666; font-size: 0.9em;">${partnershipCount} Agreements</span></div>`
                        : `<div style="text-align: center; padding: 5px;"><strong style="color: #003f7f;">${firstPartnership.name}</strong><br><span style="color: #666;">📍 ${country}</span></div>`;

                    clusteredMarker.bindPopup(popupContent);

                    // When clicked, zoom in and show individual markers
                    clusteredMarker.on('click', () => {
                        if (partnershipCount === 1) {
                            autoExpandCountry(country);
                            showPartnershipDetail(countryPartnerships[0].id);
                            return;
                        }

                        // Remove clustered marker
                        map.removeLayer(clusteredMarker);

                        // Create individual markers
                        const individualMarkers = [];
                        countryPartnerships.forEach((p, index) => {
                            const pColor = getMarkerColor(p.type);

                            const sameLocationCount = countryPartnerships.filter(other =>
                                Math.abs(other.lat - p.lat) < 0.0001 &&
                                Math.abs(other.lng - p.lng) < 0.0001
                            );

                            const sameLocationIndex = countryPartnerships
                                .filter(other =>
                                    Math.abs(other.lat - p.lat) < 0.0001 &&
                                    Math.abs(other.lng - p.lng) < 0.0001
                                )
                                .findIndex(other => other.id === p.id);

                            const tinyOffset = 0.0200;
                            const angle = (sameLocationIndex / sameLocationCount.length) * 2 * Math.PI;
                            const offsetLat = p.lat + (tinyOffset * Math.cos(angle));
                            const offsetLng = p.lng + (tinyOffset * Math.sin(angle));

                            const individualIcon = L.divIcon({
                                className: 'custom-marker-individual',
                                html: `<div style="background-color: ${pColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);"></div>`,
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            });

                            const individualMarker = L.marker([offsetLat, offsetLng], { icon: individualIcon }).addTo(map);
                            individualMarker.bindPopup(`
                                <div style="text-align: center; padding: 5px;">
                                    <strong style="color: #003f7f;">${p.name}</strong><br>
                                    <span style="color: #666;">📍 ${p.country}</span>
                                </div>
                            `);

                            individualMarker.on('click', () => {
                                autoExpandCountry(p.country);
                                showPartnershipDetail(p.id);
                            });

                            individualMarkers.push(individualMarker);
                        });

                        // Zoom to markers
                        const bounds = L.latLngBounds(individualMarkers.map(m => m.getLatLng()));
                        map.fitBounds(bounds, {
                            padding: [100, 100],
                            animate: false,
                            maxZoom: 10
                        });

                        // Restore cluster on zoom out
                        map.on('zoomend', function handleZoomOut() {
                            if (map.getZoom() < 5) {
                                individualMarkers.forEach(im => map.removeLayer(im));
                                clusteredMarker.addTo(map);
                                map.off('zoomend', handleZoomOut);
                            }
                        });
                    });

                    markers.push({
                        marker: clusteredMarker,
                        partnership: firstPartnership,
                        coords,
                        country: country,
                        partnerships: countryPartnerships
                    });
                });
            }

            function updateMapMarkers() {
                markers.forEach(({ marker, country }) => {
                    const hasVisiblePartnership = filteredPartnerships.some(p => p.country === country);

                    if (hasVisiblePartnership) {
                        if (!map.hasLayer(marker)) marker.addTo(map);
                    } else {
                        map.removeLayer(marker);
                    }
                });
            }

            function autoExpandCountry(countryName) {
                const countryGroups = document.querySelectorAll('.country-group');
                countryGroups.forEach(group => {
                    const countryHeader = group.querySelector('.country-header');
                    const countryNameSpan = countryHeader.querySelector('.country-name');

                    if (countryNameSpan && countryNameSpan.textContent === countryName) {
                        const partnershipsList = countryHeader.nextElementSibling;

                        document.querySelectorAll('.partnerships-list').forEach(list => {
                            list.style.display = 'none';
                        });

                        partnershipsList.style.display = 'block';

                        const sidebar = document.getElementById('countriesSidebar');
                        if (sidebar) {
                            const groupTop = group.offsetTop;
                            sidebar.scrollTop = groupTop - 20;
                        }
                    }
                });
            }

            function buildSidebar() {
                const sidebar = document.getElementById('countriesSidebar');
                const groupedByCountry = {};

                filteredPartnerships.forEach(p => {
                    if (!groupedByCountry[p.country]) {
                        groupedByCountry[p.country] = {
                            partnerships: [],
                            countryCode: p.countryCode
                        };
                    }
                    groupedByCountry[p.country].partnerships.push(p);
                });

                const sortedCountries = Object.keys(groupedByCountry).sort();

                sidebar.innerHTML = sortedCountries.map(country => {
                    const countryData = groupedByCountry[country];
                    const partnerships = countryData.partnerships;
                    const countryCode = countryData.countryCode;

                    return `
                        <div class="country-group">
                            <div class="country-header" onclick="toggleCountry(this)">
                                <span class="fi fi-${countryCode}" style="margin-right: 10px;"></span>
                                <span class="country-name">${country}</span>
                                <span class="country-count">${partnerships.length} Agreement${partnerships.length > 1 ? 's' : ''}</span>
                            </div>
                            <div class="partnerships-list" style="display: none;">
                                ${partnerships.map(p => `
                                    <div class="partnership-item" data-id="${p.id}" onclick="handlePartnershipClick('${p.id}')">
                                        <div class="partnership-name" style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                                            <span>${p.name}</span>
                                            ${(() => {
                                                const status = getAgreementStatus(p.endDate);
                                                if (status === 'active') return '<span style="font-size:0.72rem; font-weight:700; background:#d1fae5; color:#065f46; border-radius:20px; padding:2px 8px; white-space:nowrap;">Active</span>';
                                                if (status === 'expired') return '<span style="font-size:0.72rem; font-weight:700; background:#fee2e2; color:#991b1b; border-radius:20px; padding:2px 8px; white-space:nowrap;">Expired</span>';
                                                return '';
                                            })()}
                                        </div>
                                        <div class="partnership-type-badge" style="background-color: ${getMarkerColor(p.type)}22; color: ${getMarkerColor(p.type).replace('db','ff')}; border: 1px solid ${getMarkerColor(p.type)}66;">${p.type}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }).join('');

                updateMapMarkers();
                updateMapLegend();
            }

            window.handlePartnershipClick = function (id) {
                showPartnershipDetail(id);

                setTimeout(() => {
                    const partnershipsSection = document.querySelector('.partnerships-list-section');
                    if (partnershipsSection) {
                        const elementPosition = partnershipsSection.offsetTop;
                        const offsetPosition = elementPosition - 100;

                        const startPosition = window.pageYOffset;
                        const distance = offsetPosition - startPosition;
                        const duration = 800;
                        let start = null;

                        function animation(currentTime) {
                            if (start === null) start = currentTime;
                            const timeElapsed = currentTime - start;
                            const run = ease(timeElapsed, startPosition, distance, duration);
                            window.scrollTo(0, run);
                            if (timeElapsed < duration) requestAnimationFrame(animation);
                        }

                        function ease(t, b, c, d) {
                            t /= d / 2;
                            if (t < 1) return c / 2 * t * t + b;
                            t--;
                            return -c / 2 * (t * (t - 2) - 1) + b;
                        }

                        requestAnimationFrame(animation);
                    }
                }, 100);
            };

            window.toggleCountry = function (header) {
                const partnershipsList = header.nextElementSibling;
                const isVisible = partnershipsList.style.display === 'block';

                document.querySelectorAll('.partnerships-list').forEach(list => {
                    list.style.display = 'none';
                });

                if (!isVisible) {
                    partnershipsList.style.display = 'block';

                    const firstPartnershipItem = partnershipsList.querySelector('.partnership-item');
                    if (firstPartnershipItem) {
                        const firstPartnershipId = firstPartnershipItem.getAttribute('data-id');
                        handlePartnershipClick(firstPartnershipId);
                    }
                } else {
                    partnershipsList.style.display = 'none';
                }
            };

            function formatType(type) {
                return getTypeLabel(type);
            }

            window.showPartnershipDetail = function (id) {
                const partnership = partnershipsData.find(p => p.id === id);
                if (!partnership) return;

                document.querySelectorAll('.partnership-item').forEach(el => el.classList.remove('active'));
                const selectedItem = document.querySelector(`[data-id="${id}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('active');

                    const partnershipsList = selectedItem.closest('.partnerships-list');
                    if (partnershipsList) {
                        document.querySelectorAll('.partnerships-list').forEach(list => {
                            list.style.display = 'none';
                        });

                        partnershipsList.style.display = 'block';

                        const sidebar = document.getElementById('countriesSidebar');
                        if (sidebar) {
                            setTimeout(() => {
                                const itemTop = selectedItem.offsetTop;
                                const itemHeight = selectedItem.offsetHeight;
                                const sidebarHeight = sidebar.offsetHeight;

                                sidebar.scrollTo({
                                    top: itemTop - (sidebarHeight / 2) + (itemHeight / 2),
                                    behavior: 'smooth'
                                });
                            }, 100);
                        }
                    }
                }

                if (map && partnership.lat && partnership.lng) {
                    map.invalidateSize();

                    const countryMarkerData = markers.find(m => m.country === partnership.country);

                    if (countryMarkerData && countryMarkerData.partnerships.length > 1) {
                        map.removeLayer(countryMarkerData.marker);

                        const individualMarkers = [];
                        countryMarkerData.partnerships.forEach((p, index) => {
                            const pColor = getMarkerColor(p.type);

                            const sameLocationCount = countryMarkerData.partnerships.filter(other =>
                                Math.abs(other.lat - p.lat) < 0.0001 &&
                                Math.abs(other.lng - p.lng) < 0.0001
                            );

                            const sameLocationIndex = countryMarkerData.partnerships
                                .filter(other =>
                                    Math.abs(other.lat - p.lat) < 0.0001 &&
                                    Math.abs(other.lng - p.lng) < 0.0001
                                )
                                .findIndex(other => other.id === p.id);

                            const tinyOffset = 0.0200;
                            const angle = (sameLocationIndex / sameLocationCount.length) * 2 * Math.PI;
                            const offsetLat = p.lat + (tinyOffset * Math.cos(angle));
                            const offsetLng = p.lng + (tinyOffset * Math.sin(angle));

                            const individualIcon = L.divIcon({
                                className: 'custom-marker-individual',
                                html: `<div style="background-color: ${pColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);"></div>`,
                                iconSize: [20, 20],
                                iconAnchor: [10, 10]
                            });

                            const individualMarker = L.marker([offsetLat, offsetLng], { icon: individualIcon }).addTo(map);
                            individualMarker.bindPopup(`
                                <div style="text-align: center; padding: 5px;">
                                    <strong style="color: #003f7f;">${p.name}</strong><br>
                                    <span style="color: #666;">📍 ${p.country}</span>
                                </div>
                            `);

                            individualMarker.on('click', () => {
                                autoExpandCountry(p.country);
                                showPartnershipDetail(p.id);
                            });

                            individualMarkers.push({ marker: individualMarker, partnership: p });
                        });

                        const thisMarker = individualMarkers.find(im => im.partnership.id === partnership.id);
                        if (thisMarker) {
                            const markerLatLng = thisMarker.marker.getLatLng();

                            map.setView(markerLatLng, 12, {
                                animate: true,
                                duration: 1
                            });

                            setTimeout(() => {
                                thisMarker.marker.openPopup();
                            }, 1000);
                        }

                        map.on('zoomend', function handleZoomOut() {
                            if (map.getZoom() < 5) {
                                individualMarkers.forEach(im => map.removeLayer(im.marker));
                                countryMarkerData.marker.addTo(map);
                                map.off('zoomend', handleZoomOut);
                            }
                        });
                    } else {
                        map.setView([partnership.lat, partnership.lng], 12, {
                            animate: true,
                            duration: 1
                        });

                        setTimeout(() => {
                            if (countryMarkerData) {
                                countryMarkerData.marker.openPopup();
                            }
                        }, 1000);
                    }
                }

                const detailContainer = document.getElementById('partnershipDetail');
                const contentContainer = document.querySelector('.content-container');
                if (contentContainer) {
                    contentContainer.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }

                detailContainer.innerHTML = `
                    <div class="detail-header">
                        <span class="fi fi-${partnership.countryCode} detail-flag-icon"></span>
                        <div class="detail-title-section">
                            <h2>${partnership.agreementTitle || partnership.name}</h2>
                            <p class="detail-country">📍 ${partnership.city}, ${partnership.country}</p>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>Partner Institution</h3>
                        <div class="detail-info-row">
                            <span class="info-label">Institution Name</span>
                            <span class="info-value">${partnership.name}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Partner Type</span>
                            <span class="info-value">${partnership.partnerType}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">City</span>
                            <span class="info-value">${partnership.city}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Country</span>
                            <span class="info-value">${partnership.country}</span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>Agreement Details</h3>
                        <div class="detail-info-row">
                            <span class="info-label">Agreement Title</span>
                            <span class="info-value">${partnership.agreementTitle}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Agreement Type</span>
                            <span class="info-value">${partnership.agreementType}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Focus Area</span>
                            <span class="info-value">${partnership.focusAreas}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Implementing Unit</span>
                            <span class="info-value">${partnership.implementingUnit}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">Start Date</span>
                            <span class="info-value">${partnership.startDate}</span>
                        </div>
                        <div class="detail-info-row">
                            <span class="info-label">End Date</span>
                            <span class="info-value">${partnership.endDate}</span>
                        </div>
                    </div>

                    ${partnership.sdgs.length > 0 ? `
                        <div class="detail-section">
                            <h3>Sustainable Development Goals</h3>
                            <div class="sdg-list">
                                ${partnership.sdgs.map(sdg => `
                                    <div style="
                                        background-color: ${sdg.color}20;
                                        border-left: 4px solid ${sdg.color};
                                        padding: 12px 16px;
                                        margin-bottom: 10px;
                                        border-radius: 4px;
                                        display: flex;
                                        align-items: center;
                                    ">
                                        <span style="
                                            width: 8px;
                                            height: 8px;
                                            background-color: ${sdg.color};
                                            border-radius: 50%;
                                            margin-right: 12px;
                                        "></span>
                                        <span style="
                                            color: #2C3E50;
                                            font-size: 14px;
                                            font-weight: 500;
                                        ">${sdg.key}: ${sdg.name}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <div class="detail-section">
                        <h3>Agreement Summary</h3>
                        <p class="detail-description">${partnership.description}</p>
                    </div>

                    <div class="detail-section">
                        <h3>Rankings Support</h3>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="
                                    width: 20px;
                                    height: 20px;
                                    border: 2px solid ${partnership.supportsQS === 'Yes' || partnership.supportsQS === 'TRUE' || partnership.supportsQS === true ? '#28a745' : '#dee2e6'};
                                    border-radius: 4px;
                                    background-color: ${partnership.supportsQS === 'Yes' || partnership.supportsQS === 'TRUE' || partnership.supportsQS === true ? '#28a745' : 'white'};
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                ">
                                    ${partnership.supportsQS === 'Yes' || partnership.supportsQS === 'TRUE' || partnership.supportsQS === true ?
                        '<span style="color: white; font-size: 14px; font-weight: bold;">✓</span>' : ''}
                                </div>
                                <span style="font-size: 15px; color: #2C3E50;">Supports QS Ranking</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="
                                    width: 20px;
                                    height: 20px;
                                    border: 2px solid ${partnership.supportsGreenMetric === 'Yes' || partnership.supportsGreenMetric === 'TRUE' || partnership.supportsGreenMetric === true ? '#28a745' : '#dee2e6'};
                                    border-radius: 4px;
                                    background-color: ${partnership.supportsGreenMetric === 'Yes' || partnership.supportsGreenMetric === 'TRUE' || partnership.supportsGreenMetric === true ? '#28a745' : 'white'};
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                ">
                                    ${partnership.supportsGreenMetric === 'Yes' || partnership.supportsGreenMetric === 'TRUE' || partnership.supportsGreenMetric === true ?
                        '<span style="color: white; font-size: 14px; font-weight: bold;">✓</span>' : ''}
                                </div>
                                <span style="font-size: 15px; color: #2C3E50;">Supports UI GreenMetric</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>Exchange & Collaboration Metrics</h3>
                        <div class="detail-metrics">
                            <div class="metric-box">
                                <div class="metric-value">${partnership.metrics.studentsExchanged}</div>
                                <div class="metric-label">Students Exchanged</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-value">${partnership.metrics.facultyExchanged}</div>
                                <div class="metric-label">Faculty Exchanged</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-value">${partnership.metrics.jointPrograms}</div>
                                <div class="metric-label">Joint Programs</div>
                            </div>
                        </div>
                    </div>

                    ${partnership.agreementSigningLink ? `
                        <div class="detail-section" style="margin-top: 20px;">
                            <h3>Agreement News</h3>
                            <div style="
                                padding: 15px;
                                background-color: #f8f9fa;
                                border-left: 4px solid #003f7f;
                                border-radius: 4px;
                            ">
                                <a href="${partnership.agreementSigningLink}" target="_blank" style="
                                    color: #003f7f;
                                    text-decoration: none;
                                    font-weight: 500;
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                                    transition: color 0.3s ease;
                                "
                                onmouseover="this.style.color='#dc3545';" 
                                onmouseout="this.style.color='#003f7f';">
                                    📄 View Details
                                </a>
                            </div>
                        </div>
                        <div class="detail-section" style="margin-top: 20px;">
                            <h3>Initiatives</h3>
                            <div style="
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                padding: 16px 20px;
                                background-color: #f8f9fa;
                                border-left: 4px solid #003f7f;
                                border-radius: 4px;
                            ">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="
                                        width: 40px;
                                        height: 40px;
                                        border-radius: 8px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        font-size: 2.1rem;
                                        color: #003f7f;
                                        font-weight: 700;
                                        flex-shrink: 0;
                                    ">3</div>
                                    <div>
                                        <div style="font-weight: 600; color: #003366; font-size: 1.1rem;">Related Initiatives</div>
                                        <div style="font-size: 0.82rem; color: #666; margin-top: 2px;">Linked to ${partnership.name}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            };

            function setupFilters() {
                const searchInput = document.getElementById('searchInput');

                function applyFilters() {
                    const searchTerm = searchInput.value.toLowerCase();

                    filteredPartnerships = partnershipsData.filter(p => {
                        const matchesSearch = searchTerm === '' ||
                            p.name.toLowerCase().includes(searchTerm) ||
                            p.country.toLowerCase().includes(searchTerm) ||
                            p.agreementType.toLowerCase().includes(searchTerm) ||
                            p.focusAreas.toLowerCase().includes(searchTerm) ||
                            p.description.toLowerCase().includes(searchTerm) ||
                            p.region.toLowerCase().includes(searchTerm);

                        // Advanced filter matching
                        const matchesAdvanced = matchesAdvancedFilters(p);

                        return matchesSearch && matchesAdvanced;
                    });

                    buildSidebar();
                    updateStatistics();
                    updateMapMarkers();

                    const detailContainer = document.getElementById('partnershipDetail');
                    if (detailContainer) {
                        detailContainer.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">🎓</div>
                                <h3>Select a partnership to view details</h3>
                                <p>Click on any institution from the list on the left to see detailed information</p>
                            </div>
                        `;
                    }
                }

                searchInput.addEventListener('input', applyFilters);

                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        applyFilters();

                        setTimeout(() => {
                            const partnershipsSection = document.querySelector('.partnerships-list-section');
                            if (partnershipsSection) {
                                const elementPosition = partnershipsSection.offsetTop;
                                const offsetPosition = elementPosition - 100;

                                window.scrollTo({
                                    top: offsetPosition,
                                    behavior: 'smooth'
                                });
                            }
                        }, 100);
                    }
                });

                // Make applyFilters available globally
                window.applyFilters = applyFilters;
            }

            function updateStatistics() {
                const countries = new Set(filteredPartnerships.map(p => p.country)).size;
                const relations = filteredPartnerships.length;
                const programmes = new Set(filteredPartnerships.map(p => p.type)).size;

                document.getElementById('countriesCount').textContent = countries;
                document.getElementById('relationsCount').textContent = relations;
                document.getElementById('programmesCount').textContent = programmes;
            }

            function setupMapToggle() {
                const toggle = document.getElementById('showMapToggle');
                const mapContainerWrapper = document.getElementById('mapContainerWrapper');

                toggle?.addEventListener('change', function () {
                    if (this.checked) {
                        mapContainerWrapper.style.display = 'block';
                        setTimeout(() => map.invalidateSize(), 100);
                    } else {
                        mapContainerWrapper.style.display = 'none';
                    }
                });
            }

            function updateMapLegend() {
                const legendContainer = document.querySelector('.map-legend');
                if (!legendContainer) return;

                // Count partnerships by type
                const typeCounts = {};
                filteredPartnerships.forEach(p => {
                    typeCounts[p.type] = (typeCounts[p.type] || 0) + 1;
                });

                // Get all unique types that have partnerships
                const activeTypes = Object.keys(typeCounts).filter(type => typeCounts[type] > 0);

                // Build legend HTML
                let legendHTML = '<h4>Legend</h4>';

                activeTypes.forEach(type => {
                    const color = getMarkerColor(type);
                    const label = getTypeLabel(type);
                    const count = typeCounts[type];

                    legendHTML += `
            <div class="legend-item">
                <span class="legend-marker" style="background-color: ${color};"></span>
                <span>${label}</span>
            </div>
        `;
                });

                legendContainer.innerHTML = legendHTML;
            }
        }
    </script>
    <script src="script.js"></script>
  </main>
  </div>
<?php if (!$embeddedMap) require_once __DIR__ . '/../footer.php'; ?>