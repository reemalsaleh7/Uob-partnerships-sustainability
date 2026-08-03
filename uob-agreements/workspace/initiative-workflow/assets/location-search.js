(function () {
    'use strict';

    const form = document.querySelector('[data-request-form]');

    if (!form) {
        return;
    }

    const config = window.InitiativeLocationSearchConfig || {};
    const language = config.language === 'ar' ? 'ar' : 'en';
    const direction = language === 'ar' ? 'rtl' : 'ltr';
    const countryInput = form.querySelector('[data-country-search]');
    const countryList = document.getElementById(
        'initiative-country-options'
    );
    const placeControl = form.querySelector(
        '[data-google-place-control]'
    );
    const placeHost = form.querySelector('[data-google-place-host]');
    const selectedPlaceInput = form.querySelector(
        '[data-selected-place-input]'
    );
    const selectedPlaceNameWrap = form.querySelector(
        '[data-selected-place-name-wrap]'
    );
    const selectedPlaceNameDisplay = form.querySelector(
        '[data-selected-place-name-display]'
    );
    const clearPlaceButton = form.querySelector(
        '[data-clear-selected-place]'
    );
    const placeIdInput = form.querySelector('[data-place-id]');
    const placeNameInput = form.querySelector('[data-place-name]');
    const placeLatitudeInput = form.querySelector(
        '[data-place-latitude]'
    );
    const placeLongitudeInput = form.querySelector(
        '[data-place-longitude]'
    );
    const placeCountryCodeInput = form.querySelector(
        '[data-place-country-code]'
    );
    const placeStatus = form.querySelector(
        '[data-google-place-status]'
    );
    const openMapButton = form.querySelector(
        '[data-open-map-picker]'
    );
    const mapModalElement = document.querySelector(
        '[data-map-picker-modal]'
    );
    const mapCanvas = document.querySelector('[data-map-canvas]');
    const mapPlaceHost = document.querySelector(
        '[data-map-place-host]'
    );
    const mapStatus = document.querySelector(
        '[data-map-picker-status]'
    );
    const mapCandidatePanel = document.querySelector(
        '[data-map-candidate]'
    );
    const mapCandidateTitle = document.querySelector(
        '[data-map-candidate-title]'
    );
    const mapCandidateCoordinates = document.querySelector(
        '[data-map-candidate-coordinates]'
    );
    const mapCandidateNameInput = document.querySelector(
        '[data-map-candidate-name]'
    );
    const mapCandidateAddress = document.querySelector(
        '[data-map-candidate-address]'
    );
    const confirmMapButton = document.querySelector(
        '[data-confirm-map-location]'
    );
    const countryCodes = ["AD", "AE", "AF", "AG", "AI", "AL", "AM", "AO", "AQ", "AR", "AS", "AT", "AU", "AW", "AX", "AZ", "BA", "BB", "BD", "BE", "BF", "BG", "BH", "BI", "BJ", "BL", "BM", "BN", "BO", "BQ", "BR", "BS", "BT", "BV", "BW", "BY", "BZ", "CA", "CC", "CD", "CF", "CG", "CH", "CI", "CK", "CL", "CM", "CN", "CO", "CR", "CU", "CV", "CW", "CX", "CY", "CZ", "DE", "DJ", "DK", "DM", "DO", "DZ", "EC", "EE", "EG", "EH", "ER", "ES", "ET", "FI", "FJ", "FK", "FM", "FO", "FR", "GA", "GB", "GD", "GE", "GF", "GG", "GH", "GI", "GL", "GM", "GN", "GP", "GQ", "GR", "GS", "GT", "GU", "GW", "GY", "HK", "HM", "HN", "HR", "HT", "HU", "ID", "IE", "IL", "IM", "IN", "IO", "IQ", "IR", "IS", "IT", "JE", "JM", "JO", "JP", "KE", "KG", "KH", "KI", "KM", "KN", "KP", "KR", "KW", "KY", "KZ", "LA", "LB", "LC", "LI", "LK", "LR", "LS", "LT", "LU", "LV", "LY", "MA", "MC", "MD", "ME", "MF", "MG", "MH", "MK", "ML", "MM", "MN", "MO", "MP", "MQ", "MR", "MS", "MT", "MU", "MV", "MW", "MX", "MY", "MZ", "NA", "NC", "NE", "NF", "NG", "NI", "NL", "NO", "NP", "NR", "NU", "NZ", "OM", "PA", "PE", "PF", "PG", "PH", "PK", "PL", "PM", "PN", "PR", "PS", "PT", "PW", "PY", "QA", "RE", "RO", "RS", "RU", "RW", "SA", "SB", "SC", "SD", "SE", "SG", "SH", "SI", "SJ", "SK", "SL", "SM", "SN", "SO", "SR", "SS", "ST", "SV", "SX", "SY", "SZ", "TC", "TD", "TF", "TG", "TH", "TJ", "TK", "TL", "TM", "TN", "TO", "TR", "TT", "TV", "TW", "TZ", "UA", "UG", "UM", "US", "UY", "UZ", "VA", "VC", "VE", "VG", "VI", "VN", "VU", "WF", "WS", "YE", "YT", "ZA", "ZM", "ZW", "XK"];

    let placeAutocomplete = null;
    let mapAutocomplete = null;
    let PlaceClass = null;
    let googleReady = false;
    let googleWidgetLoaded = false;
    let googleApiLoaded = false;
    let fallbackActive = false;
    let mapsTimeout = null;

    let mapModal = null;
    let mapInstance = null;
    let mapMarker = null;
    let mapGeocoder = null;
    let mapInitialized = false;
    let mapCandidate = null;
    let mapSelectionToken = 0;

    function translate(english, arabic) {
        return language === 'ar' ? arabic : english;
    }

    function displayNames(locale) {
        try {
            return new Intl.DisplayNames([locale], {
                type: 'region'
            });
        } catch (error) {
            return null;
        }
    }

    const englishNames = displayNames('en');
    const arabicNames = displayNames('ar');
    const localizedNames = displayNames(language);

    const countries = countryCodes.map((code) => {
        const english = englishNames?.of(code) || code;
        const arabic = arabicNames?.of(code) || english;
        const localized = localizedNames?.of(code) || english;

        return {
            code,
            english,
            arabic,
            localized
        };
    }).sort((left, right) =>
        left.localized.localeCompare(
            right.localized,
            language,
            { sensitivity: 'base' }
        )
    );

    const countryLookup = new Map();

    function normalize(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u064B-\u065F\u0670]/gu, '')
            .replace(/[إأآٱ]/gu, 'ا')
            .replace(/ى/gu, 'ي')
            .replace(/ة/gu, 'ه')
            .replace(/\s+/gu, ' ')
            .trim()
            .toLocaleLowerCase();
    }

    countries.forEach((country) => {
        [
            country.localized,
            country.english,
            country.arabic,
            country.code
        ].forEach((alias) => {
            const normalizedAlias = normalize(alias);

            if (normalizedAlias !== '') {
                countryLookup.set(normalizedAlias, country);
            }
        });
    });

    function populateCountries() {
        if (!countryList || !countryInput) {
            return;
        }

        const fragment = document.createDocumentFragment();

        countries.forEach((country) => {
            const aliases = language === 'ar'
                ? [
                    {
                        value: country.arabic,
                        label: `${country.english} · ${country.code}`
                    },
                    {
                        value: country.english,
                        label: `${country.arabic} · ${country.code}`
                    }
                ]
                : [
                    {
                        value: country.english,
                        label: `${country.arabic} · ${country.code}`
                    },
                    {
                        value: country.arabic,
                        label: `${country.english} · ${country.code}`
                    }
                ];

            const usedValues = new Set();

            aliases.forEach((alias) => {
                const value = String(alias.value || '').trim();
                const key = value.toLocaleLowerCase();

                if (value === '' || usedValues.has(key)) {
                    return;
                }

                usedValues.add(key);

                const option = document.createElement('option');
                option.value = value;
                option.label = alias.label;
                option.dataset.code = country.code;
                fragment.append(option);
            });
        });

        countryList.replaceChildren(fragment);
        countryInput.dir = 'auto';
        countryInput.setAttribute(
            'aria-description',
            translate(
                'Search by country name in English or Arabic.',
                'ابحث باسم الدولة بالعربية أو الإنجليزية.'
            )
        );
    }

    function selectedCountry() {
        if (!countryInput) {
            return null;
        }

        return countryLookup.get(normalize(countryInput.value)) || null;
    }

    function setCountry(country, dispatch = true) {
        if (!countryInput) {
            return;
        }

        if (!country) {
            delete countryInput.dataset.countryCode;
            delete countryInput.dataset.countryNameEnglish;
            countryInput.dataset.countrySelectionValid = 'false';
            return;
        }

        countryInput.value = country.localized;
        countryInput.dataset.countryCode = country.code;
        countryInput.dataset.countryNameEnglish = country.english;
        countryInput.dataset.countrySelectionValid = 'true';
        countryInput.setCustomValidity('');

        if (dispatch) {
            countryInput.dispatchEvent(
                new Event('input', { bubbles: true })
            );
            countryInput.dispatchEvent(
                new Event('change', { bubbles: true })
            );
        }

        applyCountryRestriction();
    }

    function syncCountryFromCurrentValue() {
        if (!countryInput) {
            return;
        }

        const country = selectedCountry();

        if (country) {
            setCountry(country, false);
            return;
        }

        const storedEnglish = String(
            countryInput.dataset.countryNameEnglish || ''
        ).trim();

        if (storedEnglish) {
            const storedCountry = countryLookup.get(
                normalize(storedEnglish)
            );

            if (storedCountry) {
                setCountry(storedCountry, false);
                return;
            }
        }

        countryInput.dataset.countrySelectionValid = 'false';
    }

    function countryFromPlace(place) {
        const components = Array.isArray(place.addressComponents)
            ? place.addressComponents
            : [];

        const countryComponent = components.find((component) =>
            Array.isArray(component.types)
            && component.types.includes('country')
        );

        if (!countryComponent) {
            return null;
        }

        const code = String(
            countryComponent.shortText || ''
        ).toUpperCase();

        return countries.find((country) => country.code === code)
            || countryLookup.get(
                normalize(countryComponent.longText || '')
            )
            || null;
    }

    function countryFromGeocoderResult(result) {
        const components = Array.isArray(result?.address_components)
            ? result.address_components
            : [];
        const countryComponent = components.find((component) =>
            Array.isArray(component.types)
            && component.types.includes('country')
        );

        if (!countryComponent) {
            return null;
        }

        const code = String(
            countryComponent.short_name || ''
        ).toUpperCase();

        return countries.find((country) => country.code === code)
            || countryLookup.get(
                normalize(countryComponent.long_name || '')
            )
            || null;
    }

    function applyCountryRestriction() {
        const code = countryInput?.dataset.countryCode;
        const restriction = code ? [code.toLowerCase()] : [];

        if (placeAutocomplete) {
            placeAutocomplete.includedRegionCodes = restriction;
        }

        if (mapAutocomplete) {
            mapAutocomplete.includedRegionCodes = restriction;
        }
    }

    function validCoordinate(value, minimum, maximum) {
        const number = Number(value);

        return Number.isFinite(number)
            && number >= minimum
            && number <= maximum;
    }

    function coordinateValue(location, methodName) {
        if (!location) {
            return null;
        }

        const candidate = location[methodName];
        const value = typeof candidate === 'function'
            ? candidate.call(location)
            : candidate;
        const numeric = Number(value);

        return Number.isFinite(numeric) ? numeric : null;
    }

    function coordinatesFromPosition(position) {
        return {
            latitude: coordinateValue(position, 'lat'),
            longitude: coordinateValue(position, 'lng')
        };
    }

    function coordinateLabel(latitude, longitude) {
        return `${Number(latitude).toFixed(7)}, ${Number(longitude).toFixed(7)}`;
    }

    function placeDisplayName(place) {
        const displayName = place?.displayName;

        if (typeof displayName === 'string') {
            return displayName.trim();
        }

        if (
            displayName
            && typeof displayName.text === 'string'
        ) {
            return displayName.text.trim();
        }

        return '';
    }

    function updateSelectedPlaceNameDisplay() {
        if (
            !selectedPlaceNameWrap
            || !selectedPlaceNameDisplay
            || !selectedPlaceInput
        ) {
            return;
        }

        const placeName = String(
            placeNameInput?.value || ''
        ).trim();
        const address = String(
            selectedPlaceInput.value || ''
        ).trim();
        const showName = (
            placeName !== ''
            && address !== ''
            && placeName.toLocaleLowerCase()
                !== address.toLocaleLowerCase()
        );

        selectedPlaceNameDisplay.textContent = showName
            ? placeName
            : '';
        selectedPlaceNameWrap.classList.toggle(
            'd-none',
            !showName
        );
        selectedPlaceNameWrap.setAttribute(
            'aria-hidden',
            showName ? 'false' : 'true'
        );
    }

    function updateClearPlaceButton() {
        if (!clearPlaceButton || !selectedPlaceInput) {
            return;
        }

        const hasSelectedPlace =
            String(selectedPlaceInput.value || '').trim() !== '';

        clearPlaceButton.classList.toggle(
            'd-none',
            !hasSelectedPlace
        );
        clearPlaceButton.disabled = !hasSelectedPlace;
        clearPlaceButton.setAttribute(
            'aria-hidden',
            hasSelectedPlace ? 'false' : 'true'
        );
    }

    function clearPlaceMetadata() {
        [
            placeIdInput,
            placeNameInput,
            placeLatitudeInput,
            placeLongitudeInput,
            placeCountryCodeInput
        ].forEach((input) => {
            if (input) {
                input.value = '';
            }
        });
    }

    function dispatchPlaceChange() {
        selectedPlaceInput?.dispatchEvent(
            new Event('input', { bubbles: true })
        );
        selectedPlaceInput?.dispatchEvent(
            new Event('change', { bubbles: true })
        );
    }

    function commitSelectedLocation(data, message) {
        if (!selectedPlaceInput) {
            return;
        }

        const latitude = Number(data.latitude);
        const longitude = Number(data.longitude);
        const hasCoordinates = (
            validCoordinate(latitude, -90, 90)
            && validCoordinate(longitude, -180, 180)
        );
        const address = String(
            data.address
            || (hasCoordinates
                ? coordinateLabel(latitude, longitude)
                : '')
        ).trim();

        if (address === '' || !hasCoordinates) {
            throw new Error('The selected map location is incomplete.');
        }

        const placeCountry = data.country
            || countries.find((country) =>
                country.code === String(
                    data.countryCode || ''
                ).toUpperCase()
            )
            || null;

        selectedPlaceInput.value = address;
        selectedPlaceInput.dataset.googlePlaceSelected = 'true';
        selectedPlaceInput.dataset.locationSource =
            data.source || 'GOOGLE';
        selectedPlaceInput.readOnly = true;
        selectedPlaceInput.removeAttribute(
            'data-manual-location-fallback'
        );
        selectedPlaceInput.setCustomValidity('');

        if (placeIdInput) {
            placeIdInput.value = String(data.placeId || '').trim();
        }
        if (placeNameInput) {
            placeNameInput.value = String(data.name || '').trim();
        }
        if (placeLatitudeInput) {
            placeLatitudeInput.value = latitude.toFixed(7);
        }
        if (placeLongitudeInput) {
            placeLongitudeInput.value = longitude.toFixed(7);
        }
        if (placeCountryCodeInput) {
            placeCountryCodeInput.value = placeCountry?.code
                || String(data.countryCode || '').toUpperCase();
        }

        updateSelectedPlaceNameDisplay();
        updateClearPlaceButton();

        if (placeCountry) {
            setCountry(placeCountry);
        }

        showPlaceStatus(
            message || translate(
                'Place selected from Google Maps.',
                'تم اختيار الموقع من خرائط Google.'
            )
        );
        dispatchPlaceChange();
    }

    function clearMapCandidate() {
        mapCandidate = null;
        mapCandidatePanel?.classList.add('d-none');
        if (mapCandidateTitle) {
            mapCandidateTitle.textContent = '';
        }
        if (mapCandidateCoordinates) {
            mapCandidateCoordinates.textContent = '';
        }
        if (mapCandidateAddress) {
            mapCandidateAddress.textContent = '';
        }
        if (mapCandidateNameInput) {
            mapCandidateNameInput.value = '';
        }
        if (confirmMapButton) {
            confirmMapButton.disabled = true;
        }
        if (mapMarker) {
            mapMarker.map = null;
        }
    }

    function clearSelectedPlace({
        announce = false,
        focusSearch = false
    } = {}) {
        if (!selectedPlaceInput) {
            return;
        }

        selectedPlaceInput.value = '';
        clearPlaceMetadata();
        delete selectedPlaceInput.dataset.googlePlaceSelected;
        delete selectedPlaceInput.dataset.locationSource;
        selectedPlaceInput.setCustomValidity('');
        updateSelectedPlaceNameDisplay();
        updateClearPlaceButton();
        clearMapCandidate();

        if (
            placeAutocomplete
            && 'value' in placeAutocomplete
        ) {
            placeAutocomplete.value = '';
        }

        dispatchPlaceChange();

        if (announce) {
            showPlaceStatus(
                translate(
                    'Selected location removed. Search or choose another location on the map.',
                    'تم حذف الموقع المحدد. ابحث أو اختر موقعًا آخر من الخريطة.'
                )
            );
        }

        if (focusSearch) {
            window.requestAnimationFrame(() => {
                if (
                    placeAutocomplete
                    && googleReady
                    && typeof placeAutocomplete.focus === 'function'
                ) {
                    placeAutocomplete.focus();
                } else if (openMapButton && !openMapButton.disabled) {
                    openMapButton.focus();
                } else {
                    selectedPlaceInput.focus();
                }
            });
        }
    }

    function showPlaceStatus(message, isError = false) {
        if (!placeStatus) {
            return;
        }

        placeStatus.textContent = message;
        placeStatus.classList.toggle('text-danger', isError);
    }

    function showMapStatus(message, isError = false) {
        if (!mapStatus) {
            return;
        }

        mapStatus.textContent = message;
        mapStatus.classList.toggle('text-danger', isError);
    }

    function updateMapButton() {
        if (!openMapButton) {
            return;
        }

        openMapButton.disabled = !googleApiLoaded;
        openMapButton.title = googleApiLoaded
            ? translate(
                'Open the interactive map.',
                'فتح الخريطة التفاعلية.'
            )
            : translate(
                'Google Maps is loading.',
                'جارٍ تحميل خرائط Google.'
            );
    }

    function enableManualFallback(message) {
        if (!selectedPlaceInput) {
            return;
        }

        fallbackActive = true;
        googleReady = false;
        placeHost?.classList.add('d-none');
        selectedPlaceInput.readOnly = false;
        selectedPlaceInput.placeholder = translate(
            'Enter the proposed venue or location.',
            'أدخل الموقع أو المكان المقترح.'
        );
        selectedPlaceInput.dataset.manualLocationFallback = 'true';

        if (!selectedPlaceInput.value) {
            clearPlaceMetadata();
        }

        showPlaceStatus(
            message || translate(
                'Enter the location manually or choose it on the map.',
                'أدخل الموقع يدويًا أو اختره من الخريطة.'
            )
        );
    }

    function installPlaceControlValidation() {
        if (!placeControl || !selectedPlaceInput) {
            return;
        }

        placeControl.setCustomValidity = (message) => {
            selectedPlaceInput.setCustomValidity(message);
        };
        placeControl.reportValidity = () =>
            selectedPlaceInput.reportValidity();
        placeControl.focus = () => {
            if (placeAutocomplete && googleReady) {
                placeAutocomplete.focus();
            } else if (openMapButton && !openMapButton.disabled) {
                openMapButton.focus();
            } else {
                selectedPlaceInput.focus();
            }
        };
    }

    async function ensurePlaceClass() {
        if (PlaceClass) {
            return PlaceClass;
        }

        const library = await google.maps.importLibrary('places');
        PlaceClass = library.Place;
        return PlaceClass;
    }

    async function fetchPlaceData(placeId) {
        const id = String(placeId || '').trim();

        if (id === '') {
            throw new Error('Google Maps did not return a Place ID.');
        }

        const CurrentPlaceClass = await ensurePlaceClass();
        const place = new CurrentPlaceClass({
            id,
            requestedLanguage: language,
            requestedRegion:
                countryInput?.dataset.countryCode || 'BH'
        });

        await place.fetchFields({
            fields: [
                'displayName',
                'formattedAddress',
                'location',
                'addressComponents'
            ]
        });

        const latitude = coordinateValue(place.location, 'lat');
        const longitude = coordinateValue(place.location, 'lng');
        const address = String(
            place.formattedAddress
            || placeDisplayName(place)
            || ''
        ).trim();

        if (
            address === ''
            || latitude === null
            || longitude === null
        ) {
            throw new Error(
                'The selected place has no reusable map location.'
            );
        }

        const country = countryFromPlace(place);

        return {
            placeId: id,
            name: placeDisplayName(place),
            address,
            latitude,
            longitude,
            country,
            countryCode: country?.code || '',
            source: 'GOOGLE_PLACE'
        };
    }

    async function selectGooglePlace(event) {
        try {
            const data = await fetchPlaceData(event.place?.id);

            commitSelectedLocation(
                data,
                translate(
                    'Place selected from Google Maps.',
                    'تم اختيار الموقع من خرائط Google.'
                )
            );

            if (mapInitialized) {
                setMapCandidate(data, {
                    center: true,
                    zoom: 17
                });
            }
        } catch (error) {
            console.error(error);
            showPlaceStatus(
                translate(
                    'The selected Google Maps place could not be loaded. Try another result or choose it on the map.',
                    'تعذر تحميل الموقع المحدد من خرائط Google. جرّب نتيجة أخرى أو اختره من الخريطة.'
                ),
                true
            );
        }
    }

    function reportPlacesError(event) {
        console.error(
            'Google Places UI Kit request failed.',
            event
        );

        showPlaceStatus(
            translate(
                'Place search is temporarily unavailable. Choose the location directly on the map.',
                'بحث الأماكن غير متاح مؤقتًا. اختر الموقع مباشرة من الخريطة.'
            )
        );
    }

    async function initializeGooglePlaces() {
        googleApiLoaded = Boolean(
            window.google?.maps?.importLibrary
        );
        updateMapButton();

        if (
            googleReady
            || !placeHost
            || !selectedPlaceInput
        ) {
            return;
        }

        if (!googleApiLoaded) {
            enableManualFallback();
            return;
        }

        try {
            const {
                BasicPlaceAutocompleteElement,
                Place
            } = await google.maps.importLibrary('places');

            PlaceClass = Place;
            fallbackActive = false;
            placeHost.classList.remove('d-none');
            placeAutocomplete =
                new BasicPlaceAutocompleteElement({
                    requestedLanguage: language,
                    requestedRegion: 'BH',
                    locationBias: {
                        radius: 49000,
                        center: {
                            lat: 26.0667,
                            lng: 50.5577
                        }
                    }
                });

            placeAutocomplete.placeholder = translate(
                'Search Google Maps places',
                'ابحث عن مكان في خرائط Google'
            );
            placeAutocomplete.setAttribute(
                'aria-label',
                translate(
                    'Search Google Maps places',
                    'البحث عن أماكن في خرائط Google'
                )
            );
            placeAutocomplete.dir = direction;
            placeAutocomplete.addEventListener(
                'gmp-select',
                selectGooglePlace
            );
            placeAutocomplete.addEventListener(
                'gmp-load',
                () => {
                    googleWidgetLoaded = true;
                    googleReady = true;

                    showPlaceStatus(
                        selectedPlaceInput.value
                            ? translate(
                                'The saved place is shown below. Search to replace it or open the map to adjust it.',
                                'الموقع المحفوظ ظاهر أدناه. ابحث لاستبداله أو افتح الخريطة لتعديله.'
                            )
                            : translate(
                                'Search for a place or choose the exact point on the map.',
                                'ابحث عن مكان أو اختر النقطة الدقيقة من الخريطة.'
                            )
                    );
                }
            );
            placeAutocomplete.addEventListener(
                'gmp-error',
                reportPlacesError
            );
            placeAutocomplete.addEventListener(
                'gmp-requesterror',
                reportPlacesError
            );

            placeHost.replaceChildren(placeAutocomplete);
            applyCountryRestriction();
            selectedPlaceInput.readOnly = true;
            selectedPlaceInput.removeAttribute(
                'data-manual-location-fallback'
            );

            showPlaceStatus(
                translate(
                    'Connecting to Google Maps place search…',
                    'جارٍ الاتصال ببحث الأماكن في خرائط Google…'
                )
            );

            if (mapsTimeout) {
                window.clearTimeout(mapsTimeout);
                mapsTimeout = null;
            }
        } catch (error) {
            console.error(error);
            enableManualFallback(
                translate(
                    'Enter the location manually or choose it on the map.',
                    'أدخل الموقع يدويًا أو اختره من الخريطة.'
                )
            );
        }
    }

    function mapDataFromSavedLocation() {
        const latitude = Number(placeLatitudeInput?.value);
        const longitude = Number(placeLongitudeInput?.value);

        if (
            !validCoordinate(latitude, -90, 90)
            || !validCoordinate(longitude, -180, 180)
        ) {
            return null;
        }

        return {
            placeId: String(placeIdInput?.value || '').trim(),
            name: String(placeNameInput?.value || '').trim(),
            address: String(selectedPlaceInput?.value || '').trim()
                || coordinateLabel(latitude, longitude),
            latitude,
            longitude,
            countryCode: String(
                placeCountryCodeInput?.value
                || countryInput?.dataset.countryCode
                || ''
            ).toUpperCase(),
            source: String(
                selectedPlaceInput?.dataset.locationSource
                || 'SAVED_LOCATION'
            )
        };
    }

    function setMapCandidate(data, {
        center = true,
        zoom = null
    } = {}) {
        const latitude = Number(data.latitude);
        const longitude = Number(data.longitude);

        if (
            !validCoordinate(latitude, -90, 90)
            || !validCoordinate(longitude, -180, 180)
        ) {
            return;
        }

        const fallbackAddress = coordinateLabel(
            latitude,
            longitude
        );
        const candidateCountry = data.country
            || countries.find((country) =>
                country.code === String(
                    data.countryCode || ''
                ).toUpperCase()
            )
            || null;

        mapCandidate = {
            placeId: String(data.placeId || '').trim(),
            name: String(data.name || '').trim(),
            address: String(data.address || fallbackAddress).trim(),
            latitude,
            longitude,
            country: candidateCountry,
            countryCode: candidateCountry?.code
                || String(data.countryCode || '').toUpperCase(),
            source: data.source || 'GOOGLE_MAP_PIN'
        };

        if (mapMarker && mapInstance) {
            mapMarker.position = { lat: latitude, lng: longitude };
            mapMarker.map = mapInstance;
            mapMarker.title = mapCandidate.name
                || translate('Selected map point', 'النقطة المحددة على الخريطة');
        }

        if (center && mapInstance) {
            mapInstance.panTo({ lat: latitude, lng: longitude });
        }

        if (zoom !== null && mapInstance) {
            mapInstance.setZoom(zoom);
        }

        if (mapCandidateTitle) {
            mapCandidateTitle.textContent = mapCandidate.name
                || translate('Pinned location', 'موقع محدد بالدبوس');
        }
        if (mapCandidateCoordinates) {
            mapCandidateCoordinates.textContent = fallbackAddress;
        }
        if (mapCandidateAddress) {
            mapCandidateAddress.textContent = mapCandidate.address;
        }
        if (mapCandidateNameInput) {
            mapCandidateNameInput.value = mapCandidate.name;
        }

        mapCandidatePanel?.classList.remove('d-none');
        if (confirmMapButton) {
            confirmMapButton.disabled = false;
        }
    }

    async function reverseGeocodePoint(latitude, longitude) {
        const fallback = {
            placeId: '',
            name: '',
            address: coordinateLabel(latitude, longitude),
            latitude,
            longitude,
            country: selectedCountry(),
            countryCode:
                selectedCountry()?.code
                || countryInput?.dataset.countryCode
                || '',
            source: 'GOOGLE_MAP_PIN'
        };

        if (!mapGeocoder) {
            return fallback;
        }

        try {
            const { results } = await mapGeocoder.geocode({
                location: { lat: latitude, lng: longitude }
            });
            const result = results?.[0];

            if (!result) {
                return fallback;
            }

            const country = countryFromGeocoderResult(result);

            return {
                ...fallback,
                placeId: String(result.place_id || '').trim(),
                address: String(
                    result.formatted_address
                    || fallback.address
                ).trim(),
                country,
                countryCode: country?.code || fallback.countryCode
            };
        } catch (error) {
            console.warn(
                'Reverse geocoding was unavailable; coordinates were kept.',
                error
            );
            return fallback;
        }
    }

    async function handleMapPoint(latitude, longitude, placeId = '') {
        const token = ++mapSelectionToken;
        const basicCandidate = {
            placeId: '',
            name: '',
            address: coordinateLabel(latitude, longitude),
            latitude,
            longitude,
            country: selectedCountry(),
            countryCode:
                selectedCountry()?.code
                || countryInput?.dataset.countryCode
                || '',
            source: 'GOOGLE_MAP_PIN'
        };

        setMapCandidate(basicCandidate, {
            center: false
        });
        showMapStatus(
            translate(
                'Loading the selected map point…',
                'جارٍ تحميل النقطة المحددة على الخريطة…'
            )
        );

        let resolved = null;

        if (placeId !== '') {
            try {
                resolved = await fetchPlaceData(placeId);
                resolved.source = 'GOOGLE_MAP_POI';
            } catch (error) {
                console.warn(
                    'Place details were unavailable; keeping the map point.',
                    error
                );
            }
        }

        if (!resolved) {
            resolved = await reverseGeocodePoint(
                latitude,
                longitude
            );
        }

        if (token !== mapSelectionToken) {
            return;
        }

        setMapCandidate(resolved, {
            center: true,
            zoom: placeId !== '' ? 17 : null
        });
        showMapStatus(
            translate(
                'Review the selected point, then confirm the location.',
                'راجع النقطة المحددة ثم اعتمد الموقع.'
            )
        );
    }

    async function handleMapClick(event) {
        if (!event?.latLng) {
            return;
        }

        if (event.placeId && typeof event.stop === 'function') {
            event.stop();
        }

        const latitude = event.latLng.lat();
        const longitude = event.latLng.lng();

        await handleMapPoint(
            latitude,
            longitude,
            String(event.placeId || '').trim()
        );
    }

    async function handleMarkerDrag() {
        if (!mapMarker?.position) {
            return;
        }

        const { latitude, longitude } = coordinatesFromPosition(
            mapMarker.position
        );

        if (latitude === null || longitude === null) {
            return;
        }

        await handleMapPoint(latitude, longitude);
    }

    async function selectMapSearchPlace(event) {
        try {
            const data = await fetchPlaceData(event.place?.id);
            setMapCandidate(data, {
                center: true,
                zoom: 17
            });
            showMapStatus(
                translate(
                    'Place found. Review it and confirm the location.',
                    'تم العثور على المكان. راجعه ثم اعتمد الموقع.'
                )
            );
        } catch (error) {
            console.error(error);
            showMapStatus(
                translate(
                    'This result could not be loaded. Click the place directly on the map.',
                    'تعذر تحميل هذه النتيجة. اضغط على المكان مباشرة في الخريطة.'
                )
            );
        }
    }

    function reportMapSearchError(event) {
        console.error('Map place search failed.', event);
        showMapStatus(
            translate(
                'Map search is temporarily unavailable. Click directly on the map to choose the location.',
                'البحث داخل الخريطة غير متاح مؤقتًا. اضغط مباشرة على الخريطة لاختيار الموقع.'
            )
        );
    }

    async function initializeMapPicker() {
        if (mapInitialized) {
            const saved = mapDataFromSavedLocation();

            if (saved) {
                setMapCandidate(saved, {
                    center: true,
                    zoom: 17
                });
            }

            window.setTimeout(() => {
                if (mapInstance && window.google?.maps?.event) {
                    google.maps.event.trigger(
                        mapInstance,
                        'resize'
                    );
                }
            }, 80);
            return;
        }

        if (
            !googleApiLoaded
            || !window.google?.maps?.importLibrary
            || !mapCanvas
        ) {
            showMapStatus(
                translate(
                    'The interactive map is unavailable. Enter the venue manually.',
                    'الخريطة التفاعلية غير متاحة. أدخل الموقع يدويًا.'
                ),
                true
            );
            return;
        }

        showMapStatus(
            translate(
                'Loading the interactive map…',
                'جارٍ تحميل الخريطة التفاعلية…'
            )
        );

        try {
            const [mapsLibrary, markerLibrary, placesLibrary] =
                await Promise.all([
                    google.maps.importLibrary('maps'),
                    google.maps.importLibrary('marker'),
                    google.maps.importLibrary('places')
                ]);

            const { Map: GoogleMap } = mapsLibrary;
            const { AdvancedMarkerElement } = markerLibrary;
            const { BasicPlaceAutocompleteElement, Place } =
                placesLibrary;

            PlaceClass = PlaceClass || Place;

            try {
                const { Geocoder } = await google.maps.importLibrary(
                    'geocoding'
                );
                mapGeocoder = new Geocoder();
            } catch (error) {
                console.warn(
                    'Geocoding library is unavailable; map coordinates will still work.',
                    error
                );
            }

            const saved = mapDataFromSavedLocation();
            const defaultCenter = saved
                ? {
                    lat: saved.latitude,
                    lng: saved.longitude
                }
                : {
                    lat: 26.0667,
                    lng: 50.5577
                };

            mapInstance = new GoogleMap(mapCanvas, {
                center: defaultCenter,
                zoom: saved ? 17 : 10,
                mapId: 'DEMO_MAP_ID',
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
                clickableIcons: true,
                draggableCursor: 'crosshair',
                gestureHandling: 'greedy'
            });

            mapMarker = new AdvancedMarkerElement({
                gmpDraggable: true,
                title: translate(
                    'Selected map point',
                    'النقطة المحددة على الخريطة'
                )
            });
            mapMarker.addListener('dragend', handleMarkerDrag);
            mapInstance.addListener('click', handleMapClick);

            if (mapPlaceHost) {
                mapAutocomplete =
                    new BasicPlaceAutocompleteElement({
                        requestedLanguage: language,
                        requestedRegion: 'BH',
                        locationBias: {
                            radius: 49000,
                            center: defaultCenter
                        }
                    });
                mapAutocomplete.placeholder = translate(
                    'Search inside Google Maps',
                    'ابحث داخل خرائط Google'
                );
                mapAutocomplete.setAttribute(
                    'aria-label',
                    translate(
                        'Search inside Google Maps',
                        'البحث داخل خرائط Google'
                    )
                );
                mapAutocomplete.dir = direction;
                mapAutocomplete.addEventListener(
                    'gmp-select',
                    selectMapSearchPlace
                );
                mapAutocomplete.addEventListener(
                    'gmp-error',
                    reportMapSearchError
                );
                mapAutocomplete.addEventListener(
                    'gmp-requesterror',
                    reportMapSearchError
                );
                mapPlaceHost.replaceChildren(mapAutocomplete);
            }

            applyCountryRestriction();
            mapInitialized = true;

            if (saved) {
                setMapCandidate(saved, {
                    center: true,
                    zoom: 17
                });
                showMapStatus(
                    translate(
                        'The saved location is pinned. Move the pin or choose another place, then confirm.',
                        'الموقع المحفوظ محدد بالدبوس. حرّك الدبوس أو اختر مكانًا آخر ثم اعتمد الموقع.'
                    )
                );
            } else {
                showMapStatus(
                    translate(
                        'Search for a place, click a labelled place, or drop the pin on any point.',
                        'ابحث عن مكان أو اضغط على مكان ظاهر أو ضع الدبوس على أي نقطة.'
                    )
                );
            }
        } catch (error) {
            console.error(error);
            showMapStatus(
                translate(
                    'The interactive map could not be loaded. Enter the venue manually.',
                    'تعذر تحميل الخريطة التفاعلية. أدخل الموقع يدويًا.'
                ),
                true
            );
        }
    }

    function syncLoadedData() {
        syncCountryFromCurrentValue();
        updateSelectedPlaceNameDisplay();
        updateClearPlaceButton();

        if (
            selectedPlaceInput?.value
            && placeLatitudeInput?.value
            && placeLongitudeInput?.value
        ) {
            selectedPlaceInput.dataset.googlePlaceSelected = 'true';
        }

        if (mapInitialized) {
            const saved = mapDataFromSavedLocation();

            if (saved) {
                setMapCandidate(saved, {
                    center: true,
                    zoom: 17
                });
            }
        }

        if (selectedPlaceInput?.value) {
            showPlaceStatus(
                googleReady
                    ? translate(
                        'The saved place is shown below. Search to replace it or open the map to adjust it.',
                        'الموقع المحفوظ ظاهر أدناه. ابحث لاستبداله أو افتح الخريطة لتعديله.'
                    )
                    : translate(
                        'Saved venue or location.',
                        'الموقع أو المكان المحفوظ.'
                    )
            );
        }
    }

    populateCountries();
    installPlaceControlValidation();
    syncLoadedData();
    updateMapButton();

    countryInput?.addEventListener('input', () => {
        const country = selectedCountry();

        if (country) {
            countryInput.dataset.countryCode = country.code;
            countryInput.dataset.countryNameEnglish =
                country.english;
            countryInput.dataset.countrySelectionValid = 'true';
            countryInput.setCustomValidity('');
        } else {
            delete countryInput.dataset.countryCode;
            delete countryInput.dataset.countryNameEnglish;
            countryInput.dataset.countrySelectionValid = 'false';
        }
    });

    countryInput?.addEventListener('change', () => {
        const previousCode =
            placeCountryCodeInput?.value
            || countryInput.dataset.countryCode
            || '';
        const country = selectedCountry();

        if (!country) {
            countryInput.dataset.countrySelectionValid = 'false';
            countryInput.setCustomValidity(
                translate(
                    'Select a country from the list.',
                    'اختر دولة من القائمة.'
                )
            );
            return;
        }

        setCountry(country, false);

        if (
            previousCode
            && previousCode !== country.code
            && selectedPlaceInput?.dataset.googlePlaceSelected === 'true'
        ) {
            clearSelectedPlace();
            showPlaceStatus(
                translate(
                    'Country changed. Search or choose the venue on the map again.',
                    'تم تغيير الدولة. ابحث أو اختر الموقع من الخريطة مرة أخرى.'
                )
            );
        }
    });

    form.addEventListener('change', (event) => {
        const target = event.target;

        if (
            target instanceof HTMLInputElement
            && target.name === 'implementation_scope'
            && !['OUTSIDE_UOB', 'HYBRID'].includes(target.value)
        ) {
            clearSelectedPlace();
        }
    });

    selectedPlaceInput?.addEventListener('input', () => {
        if (
            selectedPlaceInput.dataset.manualLocationFallback === 'true'
        ) {
            clearPlaceMetadata();
            delete selectedPlaceInput.dataset.googlePlaceSelected;
            delete selectedPlaceInput.dataset.locationSource;
        }

        updateSelectedPlaceNameDisplay();
        updateClearPlaceButton();
    });

    clearPlaceButton?.addEventListener('click', () => {
        clearSelectedPlace({
            announce: true,
            focusSearch: true
        });
    });

    mapCandidateNameInput?.addEventListener('input', () => {
        if (!mapCandidate) {
            return;
        }

        mapCandidate.name = mapCandidateNameInput.value.trim();
        if (mapCandidateTitle) {
            mapCandidateTitle.textContent = mapCandidate.name
                || translate('Pinned location', 'موقع محدد بالدبوس');
        }
        if (mapMarker) {
            mapMarker.title = mapCandidate.name
                || translate('Selected map point', 'النقطة المحددة على الخريطة');
        }
    });

    openMapButton?.addEventListener('click', () => {
        if (!googleApiLoaded || !mapModalElement) {
            return;
        }

        if (!window.bootstrap?.Modal) {
            showPlaceStatus(
                translate(
                    'The map window could not be opened.',
                    'تعذر فتح نافذة الخريطة.'
                ),
                true
            );
            return;
        }

        mapModal = bootstrap.Modal.getOrCreateInstance(
            mapModalElement
        );
        mapModal.show();
    });

    mapModalElement?.addEventListener(
        'shown.bs.modal',
        initializeMapPicker
    );

    confirmMapButton?.addEventListener('click', () => {
        if (!mapCandidate) {
            return;
        }

        const confirmed = {
            ...mapCandidate,
            name: String(
                mapCandidateNameInput?.value
                || mapCandidate.name
                || ''
            ).trim(),
            source: mapCandidate.source || 'GOOGLE_MAP_PIN'
        };

        try {
            commitSelectedLocation(
                confirmed,
                translate(
                    'Location selected from the interactive map.',
                    'تم اختيار الموقع من الخريطة التفاعلية.'
                )
            );
            mapModal?.hide();
        } catch (error) {
            console.error(error);
            showMapStatus(
                translate(
                    'Choose a valid point on the map before confirming.',
                    'اختر نقطة صحيحة على الخريطة قبل الاعتماد.'
                ),
                true
            );
        }
    });

    form.addEventListener(
        'initiative:form-data-loaded',
        syncLoadedData
    );

    window.initiativeLocationSearchReady =
        initializeGooglePlaces;

    const apiKeyConfigured = Boolean(
        config.apiKeyConfigured
    );

    if (!apiKeyConfigured) {
        enableManualFallback(
            translate(
                'Enter the location manually.',
                'أدخل الموقع يدويًا.'
            )
        );
    } else {
        mapsTimeout = window.setTimeout(() => {
            if (!googleWidgetLoaded) {
                showPlaceStatus(
                    translate(
                        'Place search is taking longer than expected. You can use the map when it becomes available.',
                        'يستغرق بحث الأماكن وقتًا أطول من المتوقع. يمكنك استخدام الخريطة عند توفرها.'
                    )
                );
            }
        }, 12000);
    }

    const previousAuthFailure = window.gm_authFailure;

    window.gm_authFailure = function () {
        if (typeof previousAuthFailure === 'function') {
            previousAuthFailure();
        }

        googleApiLoaded = false;
        updateMapButton();
        enableManualFallback(
            translate(
                'Enter the location manually.',
                'أدخل الموقع يدويًا.'
            )
        );
    };
}());
