jQuery(document).ready(function($) {
    if (typeof city_district_data === 'undefined') {
        console.error('city_district_data is undefined');
        return;
    }

    console.log('Initializing with data:', city_district_data); // Debug log

    // Initialize Select2 on city and district selectors
    function initializeSelects() {
        $('.city-select, .district-select').select2({
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: false,
            width: '100%',
            minimumResultsForSearch: 5 // Show search after 5 items
        });
    }

    // Populate cities on page load
    function populateCities() {
        if (!city_district_data.cities || !city_district_data.cities.length) {
            console.error('No cities found in city_district_data');
            return;
        }

        console.log('Populating cities with:', city_district_data.cities); // Debug log

        // Find all city select fields
        $('select#billing_city, select#shipping_city').each(function() {
            var select = $(this);
            var currentVal = select.val();

            // Keep the placeholder option and remove other options
            select.find('option:not(:first)').remove();

            // Add city options
            $.each(city_district_data.cities, function(index, city) {
                select.append($('<option>', {
                    value: city.name,
                    text: city.name,
                    'data-city-id': city.id // Store ID as data attribute
                }));
            });

            // Restore value if it existed
            if (currentVal) {
                select.val(currentVal);
            }

            // Update Select2
            select.trigger('change');
        });
    }

    // Initialize selects first
    initializeSelects();

    // Then populate cities
    populateCities();

    // Handle city selection changes
    $(document).on('change', 'select#billing_city, select#shipping_city', function() {
        var cityName = $(this).val();
        if (!cityName) {
            return;
        }

        console.log('City selected:', cityName); // Debug log

        // Find the corresponding district select
        var districtSelect;
        if ($(this).attr('id') === 'billing_city') {
            districtSelect = $('#billing_address_1');
        } else {
            districtSelect = $('#shipping_address_1');
        }

        if (!districtSelect.length) {
            console.error('District select not found');
            return;
        }

        // Find city ID from selected option's data attribute
        var cityId = $(this).find('option:selected').data('city-id');

        if (!cityId) {
            // Fall back to searching by name
            var foundCity = null;
            $.each(city_district_data.cities, function(i, city) {
                if (city.name === cityName) {
                    foundCity = city;
                    return false; // Break loop
                }
            });

            if (foundCity) {
                cityId = foundCity.id;
            } else {
                console.error('Could not find city ID for:', cityName);
                return;
            }
        }

        console.log('Found city ID:', cityId); // Debug log

        // Clear existing districts except placeholder
        districtSelect.find('option:not(:first)').remove();
        districtSelect.val('').trigger('change');

        // Get districts for this city
        if (city_district_data.districts_by_city && city_district_data.districts_by_city[cityId]) {
            var districts = city_district_data.districts_by_city[cityId];
            console.log('Found districts:', districts); // Debug log

            $.each(districts, function(index, district) {
                districtSelect.append($('<option>', {
                    value: district.name,
                    text: district.name
                }));
            });

            districtSelect.trigger('change');
        } else {
            console.log('No districts found for city ID:', cityId);
        }
    });

    // Add custom CSS to fix the gray background issue
    $('<style>')
        .prop("type", "text/css")
        .html(`
            .select2-container--default .select2-results__option--selected {
                background-color: #e0e0e0;
            }
            .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
                background-color: #5897fb;
                color: white;
            }
            .select2-container--default.select2-container--focus .select2-selection--single {
                border-color: #aaa;
            }
        `)
        .appendTo("head");

    // Re-initialize after form updates
    $(document.body).on('updated_checkout', function() {
        console.log('Checkout updated, reinitializing selects'); // Debug log

        setTimeout(function() {
            // Destroy existing select2 instances first
            $('select#billing_city, select#shipping_city, select#billing_address_1, select#shipping_address_1').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });

            // Re-initialize and populate
            initializeSelects();
            populateCities();
        }, 200);
    });
});